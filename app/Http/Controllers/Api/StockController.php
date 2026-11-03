<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LigneApprovisionnement;
use App\Models\Produit;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    // Coût d'une unité au moment de la vente (sinon coût moyen actuel, sinon coût saisi)
    private const COUT = 'COALESCE(lignes_vente.cout_unitaire_fcfa, produits.cout_moyen_fcfa, produits.cout_fcfa)';

    /** Ventes payées (lignes) d'une boutique depuis une date, avec CA, bénéfice et nombre de lignes sans coût. */
    private function lignesVendues(int $boutiqueId, $debut)
    {
        $cout = self::COUT;

        return DB::table('lignes_vente')
            ->join('ventes', 'ventes.id', '=', 'lignes_vente.vente_id')
            ->leftJoin('produits', 'produits.id', '=', 'lignes_vente.produit_id')
            ->where('ventes.boutique_id', $boutiqueId)
            ->where('ventes.statut', 'payee')
            ->where('ventes.cree_le', '>=', $debut)
            ->whereNotNull('lignes_vente.produit_id')
            ->selectRaw("
                SUM(lignes_vente.quantite) as quantite,
                SUM(lignes_vente.prix_unitaire_fcfa * lignes_vente.quantite) as ca,
                SUM(CASE WHEN $cout IS NULL THEN 0 ELSE (lignes_vente.prix_unitaire_fcfa - $cout) * lignes_vente.quantite END) as benefice,
                SUM(CASE WHEN $cout IS NULL THEN 1 ELSE 0 END) as sans_cout
            ");
    }

    /** État du stock de tous les produits + résumé financier sur 30 jours. */
    public function index(Request $request)
    {
        $boutiqueId = $request->user()->boutique->id;
        $debut30 = now()->subDays(29)->startOfDay();

        $vendus30 = DB::table('lignes_vente')
            ->join('ventes', 'ventes.id', '=', 'lignes_vente.vente_id')
            ->where('ventes.boutique_id', $boutiqueId)
            ->where('ventes.statut', 'payee')
            ->where('ventes.cree_le', '>=', $debut30)
            ->whereNotNull('lignes_vente.produit_id')
            ->groupBy('lignes_vente.produit_id')
            ->selectRaw('lignes_vente.produit_id as id, SUM(lignes_vente.quantite) as q')
            ->pluck('q', 'id');

        $produits = Produit::where('boutique_id', $boutiqueId)->orderBy('nom')->get();

        $liste = $produits->map(function (Produit $p) use ($vendus30) {
            $q30 = (int) ($vendus30[$p->id] ?? 0);
            $parJour = $q30 / 30;
            $jours = ($p->suivre_stock && $parJour > 0)
                ? (int) floor(max($p->stock_actuel, 0) / $parJour)
                : null;

            return [
                'produit_id' => $p->id,
                'nom' => $p->nom,
                'actif' => $p->actif,
                'prix_fcfa' => $p->prix_fcfa,
                'cout_moyen_fcfa' => $p->cout_moyen_fcfa !== null ? round($p->cout_moyen_fcfa, 2) : null,
                'suivre_stock' => $p->suivre_stock,
                'stock_actuel' => $p->stock_actuel,
                'unites_par_lot' => $p->unites_par_lot,
                'lots_restants' => $p->lots_restants,
                'seuil_alerte' => $p->seuil_alerte,
                'seuil_effectif' => $p->seuil_effectif,
                'statut_stock' => $p->statut_stock,
                'vendus_30j' => $q30,
                'jours_restants' => $jours,
                'valeur_stock_fcfa' => (int) round(max($p->stock_actuel, 0) * ($p->cout_moyen_fcfa ?? 0)),
            ];
        })->values();

        $fin = $this->lignesVendues($boutiqueId, $debut30)->first();
        $depenses = (int) DB::table('approvisionnements')
            ->where('boutique_id', $boutiqueId)->where('cree_le', '>=', $debut30)->sum('depense_fcfa');

        return response()->json([
            'resume' => [
                'valeur_stock_fcfa' => (int) $liste->sum('valeur_stock_fcfa'),
                'depenses_30j_fcfa' => $depenses,
                'ca_30j_fcfa' => (int) ($fin->ca ?? 0),
                'benefice_30j_fcfa' => (int) round($fin->benefice ?? 0),
                'lignes_sans_cout' => (int) ($fin->sans_cout ?? 0),
                'nb_bas' => $liste->where('statut_stock', 'bas')->count(),
                'nb_epuises' => $liste->where('statut_stock', 'epuise')->count(),
            ],
            'produits' => $liste,
        ]);
    }

    /**
     * Classement de TOUS les produits, y compris ceux qui n'ont rien vendu.
     * periode : jour | semaine (7 j) | mois (30 j) | annee (365 j)
     * tri : quantite | ca | benefice
     */
    public function classement(Request $request)
    {
        $request->validate([
            'periode' => ['nullable', 'in:jour,semaine,mois,annee'],
            'tri' => ['nullable', 'in:quantite,ca,benefice'],
        ]);
        $periode = $request->input('periode', 'semaine');
        $tri = $request->input('tri', 'quantite');
        $boutiqueId = $request->user()->boutique->id;

        $debut = match ($periode) {
            'jour' => now()->startOfDay(),
            'mois' => now()->subDays(29)->startOfDay(),
            'annee' => now()->subDays(364)->startOfDay(),
            default => now()->subDays(6)->startOfDay(),
        };

        $cout = self::COUT;
        $ventes = DB::table('lignes_vente')
            ->join('ventes', 'ventes.id', '=', 'lignes_vente.vente_id')
            ->leftJoin('produits', 'produits.id', '=', 'lignes_vente.produit_id')
            ->where('ventes.boutique_id', $boutiqueId)
            ->where('ventes.statut', 'payee')
            ->where('ventes.cree_le', '>=', $debut)
            ->whereNotNull('lignes_vente.produit_id')
            ->groupBy('lignes_vente.produit_id')
            ->selectRaw("
                lignes_vente.produit_id as produit_id,
                SUM(lignes_vente.quantite) as quantite,
                SUM(lignes_vente.prix_unitaire_fcfa * lignes_vente.quantite) as ca,
                SUM(CASE WHEN $cout IS NULL THEN 0 ELSE (lignes_vente.prix_unitaire_fcfa - $cout) * lignes_vente.quantite END) as benefice,
                SUM(CASE WHEN $cout IS NULL THEN 1 ELSE 0 END) as sans_cout,
                COUNT(*) as nb_lignes
            ")
            ->get()->keyBy('produit_id');

        $produits = Produit::where('boutique_id', $boutiqueId)->get();

        // Dernier achat de chaque produit : sert de repère pour la barre de stock
        $derniers = LigneApprovisionnement::whereIn('produit_id', $produits->pluck('id'))
            ->orderByDesc('id')->get()->unique('produit_id')->keyBy('produit_id');

        $lignes = $produits->map(function (Produit $p) use ($ventes, $derniers) {
            $v = $ventes->get($p->id);
            $dernier = $derniers->get($p->id);
            $capacite = max($p->stock_actuel, $dernier ? $dernier->nb_lots * $dernier->unites_par_lot : 0);

            return [
                'produit_id' => $p->id,
                'nom' => $p->nom,
                'quantite' => (int) ($v->quantite ?? 0),
                'ca_fcfa' => (int) ($v->ca ?? 0),
                // null = on ne connaît pas le prix d'achat : pas de faux bénéfice
                'benefice_fcfa' => ! $v ? 0
                    : ((int) $v->sans_cout >= (int) $v->nb_lignes ? null : (int) round($v->benefice)),
                'suivre_stock' => $p->suivre_stock,
                'stock_actuel' => $p->stock_actuel,
                'lots_restants' => $p->lots_restants,
                'statut_stock' => $p->statut_stock,
                'niveau_stock' => ($p->suivre_stock && $capacite > 0)
                    ? round(min(1, max($p->stock_actuel, 0) / $capacite), 3) : null,
            ];
        });

        $cle = ['quantite' => 'quantite', 'ca' => 'ca_fcfa', 'benefice' => 'benefice_fcfa'][$tri];
        $trie = $lignes->sort(function ($a, $b) use ($cle) {
            return [$b[$cle] ?? 0, $a['nom']] <=> [$a[$cle] ?? 0, $b['nom']];
        })->values();

        $totalCa = (int) $trie->sum('ca_fcfa');
        $trie = $trie->map(function ($l, $i) use ($totalCa) {
            $l['rang'] = $i + 1;
            $l['part_pct'] = $totalCa > 0 ? round($l['ca_fcfa'] / $totalCa * 100, 1) : 0;

            return $l;
        })->values();

        return response()->json([
            'periode' => $periode,
            'tri' => $tri,
            'total_quantite' => (int) $trie->sum('quantite'),
            'total_ca_fcfa' => $totalCa,
            'total_benefice_fcfa' => (int) $trie->sum(fn ($l) => $l['benefice_fcfa'] ?? 0),
            'produits' => $trie,
        ]);
    }

    /** Inventaire : le commerçant compte son stock réel et corrige. */
    public function correction(Request $request, $id, StockService $stock)
    {
        $data = $request->validate([
            'stock_reel' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $produit = DB::transaction(function () use ($request, $id, $data, $stock) {
            $produit = Produit::where('boutique_id', $request->user()->boutique->id)
                ->whereKey($id)->lockForUpdate()->firstOrFail();

            $produit->suivre_stock = true;
            $ecart = $data['stock_reel'] - $produit->stock_actuel;
            if ($ecart !== 0) {
                $stock->entrer($produit, $ecart, 'correction', null, null, $data['note'] ?? 'Inventaire');
            } else {
                $produit->save();
            }

            return $produit;
        });

        return $produit->refresh();
    }
}
