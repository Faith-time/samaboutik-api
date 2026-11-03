<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Approvisionnement;
use App\Models\LigneApprovisionnement;
use App\Models\Produit;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovisionnementController extends Controller
{
    public function index(Request $request)
    {
        return Approvisionnement::with('lignes')
            ->where('boutique_id', $request->user()->boutique->id)
            ->latest('cree_le')->latest('id')
            ->limit(50)->get();
    }

    /**
     * Enregistre un achat chez le fournisseur.
     * Exemple : 4 paquets de 24 biscuits à 2 400 FCFA le paquet
     *   → +96 unités, coût d'une unité = 100 FCFA, coût moyen mis à jour.
     */
    public function store(Request $request, StockService $stock)
    {
        $data = $request->validate([
            'fournisseur' => ['nullable', 'string', 'max:255'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.produit_id' => ['required', 'integer'],
            'lignes.*.nb_lots' => ['required', 'integer', 'min:1'],
            'lignes.*.unites_par_lot' => ['nullable', 'integer', 'min:1'],
            'lignes.*.prix_lot_fcfa' => ['required', 'integer', 'min:0'],
        ]);

        $boutiqueId = $request->user()->boutique->id;

        $appro = DB::transaction(function () use ($data, $boutiqueId, $stock) {
            $appro = Approvisionnement::create([
                'boutique_id' => $boutiqueId,
                'fournisseur' => $data['fournisseur'] ?? null,
                'depense_fcfa' => 0,
            ]);
            $depenseTotale = 0;

            foreach ($data['lignes'] as $l) {
                $produit = Produit::where('boutique_id', $boutiqueId)
                    ->whereKey($l['produit_id'])->lockForUpdate()->first();
                if (! $produit) {
                    throw ValidationException::withMessages([
                        'lignes' => ["Produit {$l['produit_id']} introuvable."],
                    ]);
                }

                $upl = $l['unites_par_lot'] ?? ($produit->unites_par_lot ?: 1);
                $unites = $l['nb_lots'] * $upl;
                $coutUnitaire = $l['prix_lot_fcfa'] / $upl;

                // Coût moyen pondéré : (valeur du stock restant + nouvel achat) / (unités restantes + nouvelles)
                $restant = max($produit->stock_actuel, 0);
                $ancienCout = $produit->cout_moyen_fcfa ?? $coutUnitaire;
                $nouveauMoyen = ($restant * $ancienCout + $unites * $coutUnitaire) / ($restant + $unites);

                $produit->unites_par_lot = $upl;
                $produit->suivre_stock = true;
                $produit->cout_moyen_fcfa = $nouveauMoyen;
                $produit->cout_fcfa = (int) round($nouveauMoyen);
                // le stock est mis à jour (et le produit enregistré) par entrer()
                $stock->entrer($produit, $unites, 'achat', 'approvisionnement', $appro->id);

                LigneApprovisionnement::create([
                    'approvisionnement_id' => $appro->id,
                    'produit_id' => $produit->id,
                    'nom_produit' => $produit->nom,
                    'nb_lots' => $l['nb_lots'],
                    'unites_par_lot' => $upl,
                    'prix_lot_fcfa' => $l['prix_lot_fcfa'],
                    'cout_unitaire_fcfa' => $coutUnitaire,
                ]);

                $depenseTotale += $l['nb_lots'] * $l['prix_lot_fcfa'];
            }

            $appro->update(['depense_fcfa' => $depenseTotale]);

            return $appro;
        });

        return response()->json($appro->load('lignes'), 201);
    }
}
