<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VenteController extends Controller
{
    public function index(Request $request)
    {
        // Appel de l'application mobile : résumé par période (7j, 30j, 3m)
        if ($request->filled('periode')) {
            return response()->json($this->resume($request));
        }

        $requete = $request->user()->boutique->ventes()
            ->with(['lignes', 'paiements'])
            ->latest('cree_le');

        if ($request->filled('statut')) {
            $requete->where('statut', $request->input('statut'));
        }

        return $requete->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.produit_id' => ['required', 'integer'],
            'lignes.*.quantite' => ['required', 'integer', 'min:1'],
        ]);

        $boutique = $request->user()->boutique;

        $vente = DB::transaction(function () use ($data, $boutique) {
            $produits = $boutique->produits()
                ->where('actif', true)
                ->whereIn('id', collect($data['lignes'])->pluck('produit_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Stock suffisant ? (on additionne si un produit apparaît sur plusieurs lignes)
            foreach (collect($data['lignes'])->groupBy('produit_id') as $produitId => $groupe) {
                $produit = $produits->get((int) $produitId);
                if ($produit && $produit->suivre_stock && $groupe->sum('quantite') > $produit->stock_actuel) {
                    $reste = max($produit->stock_actuel, 0);
                    throw ValidationException::withMessages([
                        'lignes' => ["Stock insuffisant pour {$produit->nom} : il en reste {$reste}."],
                    ]);
                }
            }

            $vente = $boutique->ventes()->create(['total_fcfa' => 0, 'statut' => 'en_attente']);
            $total = 0;

            foreach ($data['lignes'] as $ligne) {
                $produit = $produits->get($ligne['produit_id']);

                if (! $produit) {
                    throw ValidationException::withMessages([
                        'lignes' => ["Produit {$ligne['produit_id']} introuvable ou inactif."],
                    ]);
                }

                $vente->lignes()->create([
                    'produit_id' => $produit->id,
                    'nom_produit' => $produit->nom,
                    'prix_unitaire_fcfa' => $produit->prix_fcfa,
                    // Le coût du moment : le bénéfice reste juste même si les prix d'achat changent
                    'cout_unitaire_fcfa' => $produit->cout_moyen_fcfa ?? $produit->cout_fcfa,
                    'quantite' => $ligne['quantite'],
                ]);

                $total += $produit->prix_fcfa * $ligne['quantite'];
            }

            $vente->update(['total_fcfa' => $total]);

            return $vente;
        });

        return response()->json($vente->load('lignes'), 201);
    }

    public function show(Request $request, $id)
    {
        return $request->user()->boutique->ventes()
            ->with(['lignes', 'paiements'])
            ->findOrFail($id);
    }

    public function annuler(Request $request, $id)
    {
        $vente = $request->user()->boutique->ventes()->findOrFail($id);

        if ($vente->statut !== 'en_attente') {
            throw ValidationException::withMessages([
                'statut' => ['Seule une vente en attente peut être annulée.'],
            ]);
        }

        $vente->update(['statut' => 'annulee']);

        return $vente;
    }

    private function resume(Request $request): array
    {
        $boutiqueId = $request->user()->boutique->id;
        $jours = match ($request->input('periode')) {
            '30j' => 30,
            '3m' => 90,
            default => 7,
        };

        $debut = now()->subDays($jours - 1)->startOfDay();
        $debutPrecedent = $debut->copy()->subDays($jours);

        $payees = fn () => \App\Models\Vente::where('boutique_id', $boutiqueId)
            ->where('statut', 'payee');

        $total = (int) $payees()->where('cree_le', '>=', $debut)->sum('total_fcfa');
        $totalPrecedent = (int) $payees()
            ->where('cree_le', '>=', $debutPrecedent)
            ->where('cree_le', '<', $debut)
            ->sum('total_fcfa');

        $variation = $totalPrecedent > 0
            ? round((($total - $totalPrecedent) / $totalPrecedent) * 100, 1)
            : null;

        // Une valeur par jour (0 les jours sans vente)
        $parJour = $payees()->where('cree_le', '>=', $debut)
            ->selectRaw('DATE(cree_le) as jour, SUM(total_fcfa) as total')
            ->groupBy('jour')
            ->pluck('total', 'jour');

        $serie = [];
        for ($i = 0; $i < $jours; $i++) {
            $jour = $debut->copy()->addDays($i)->toDateString();
            $serie[] = [
                'date' => $jour,
                'total_fcfa' => (int) ($parJour[$jour] ?? 0),
            ];
        }

        $parMethode = DB::table('paiements')
            ->join('ventes', 'ventes.id', '=', 'paiements.vente_id')
            ->where('ventes.boutique_id', $boutiqueId)
            ->where('paiements.statut', 'paye')
            ->where('ventes.cree_le', '>=', $debut)
            ->groupBy('paiements.methode')
            ->selectRaw('paiements.methode as methode, SUM(paiements.montant_fcfa) as total_fcfa, SUM(paiements.montant_sats) as total_sats')
            ->orderByDesc('total_fcfa')
            ->get()
            ->map(fn ($m) => [
                'methode' => $m->methode,
                'total_fcfa' => (int) $m->total_fcfa,
                'total_sats' => $m->total_sats !== null ? (int) $m->total_sats : null,
            ])
            ->values();

        $ventes = \App\Models\Vente::with(['lignes', 'paiements'])
            ->where('boutique_id', $boutiqueId)
            ->where('cree_le', '>=', $debut)
            ->latest('cree_le')
            ->limit(20)
            ->get();

        return [
            'total_fcfa' => $total,
            'variation_pct' => $variation,
            'serie' => $serie,
            'par_methode' => $parMethode,
            'ventes' => $ventes,
        ];
    }
}
