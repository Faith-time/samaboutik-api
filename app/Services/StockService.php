<?php

namespace App\Services;

use App\Models\MouvementStock;
use App\Models\Produit;
use App\Models\Vente;
use Illuminate\Support\Facades\DB;

/**
 * Le stock est compté en UNITÉS (un biscuit), même si on l'achète en paquets.
 * Il baisse uniquement quand le paiement est confirmé, jamais à la simple création de la vente.
 */
class StockService
{
    /** Ajoute (ou retire, si négatif) des unités et écrit le journal. */
    public function entrer(
        Produit $produit,
        int $unites,
        string $type,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null,
    ): void {
        $produit->stock_actuel = $produit->stock_actuel + $unites;
        $produit->save();

        MouvementStock::create([
            'boutique_id' => $produit->boutique_id,
            'produit_id' => $produit->id,
            'type' => $type,
            'quantite' => $unites,
            'stock_apres' => $produit->stock_actuel,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'note' => $note,
        ]);
    }

    /** Sortie de stock d'une vente payée. Sans danger si appelée deux fois. */
    public function sortirPourVente(Vente $vente): void
    {
        DB::transaction(function () use ($vente) {
            // Verrou : deux appels simultanés (webhook + suivi) ne comptent pas deux fois
            Vente::whereKey($vente->id)->lockForUpdate()->first();

            $dejaFait = MouvementStock::where('reference_type', 'vente')
                ->where('reference_id', $vente->id)->exists();
            if ($dejaFait) {
                return;
            }

            foreach ($vente->lignes()->get() as $ligne) {
                if (! $ligne->produit_id) {
                    continue;
                }
                $produit = Produit::whereKey($ligne->produit_id)->lockForUpdate()->first();
                if (! $produit || ! $produit->suivre_stock) {
                    continue;
                }

                // Le stock ne descend jamais sous zéro (le client a déjà payé)
                $apres = max(0, $produit->stock_actuel - $ligne->quantite);
                $produit->stock_actuel = $apres;
                $produit->save();

                MouvementStock::create([
                    'boutique_id' => $produit->boutique_id,
                    'produit_id' => $produit->id,
                    'type' => 'vente',
                    'quantite' => -$ligne->quantite,
                    'stock_apres' => $apres,
                    'reference_type' => 'vente',
                    'reference_id' => $vente->id,
                ]);
            }
        });
    }
}
