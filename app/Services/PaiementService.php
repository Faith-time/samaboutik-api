<?php

namespace App\Services;

use App\Models\Paiement;
use Illuminate\Support\Facades\DB;

/**
 * Seul endroit qui décide qu'un paiement est « payé ».
 * On ne croit jamais le téléphone ni le contenu d'un webhook : on redemande au prestataire.
 */
class PaiementService
{
    public function __construct(
        private LightningService $lightning,
        private PayDunyaService $paydunya,
        private StockService $stock,
    ) {
    }

    public function rafraichir(Paiement $paiement): Paiement
    {
        if (in_array($paiement->statut, ['paye', 'echoue'], true)) {
            return $paiement;
        }

        $statut = match ($paiement->methode) {
            'lightning' => $paiement->reference_prestataire
                ? $this->lightning->statut($paiement->reference_prestataire)
                : 'en_attente',
            'wave', 'orange_money', 'free_money' => $paiement->reference_prestataire
                ? $this->paydunya->statut($paiement->reference_prestataire, $paiement->montant_fcfa)
                : 'en_attente',
            default => 'en_attente',
        };

        if ($statut === 'paye') {
            $this->regler($paiement);
        } elseif ($statut === 'echoue') {
            $paiement->update(['statut' => 'echoue']);
        } elseif ($paiement->statut === 'en_attente'
            && $paiement->expire_le && $paiement->expire_le->isPast()) {
            $paiement->update(['statut' => 'expire']);
        }

        return $paiement->refresh();
    }

    /** Marque le paiement payé, la vente payée, et fait baisser le stock (sans danger si appelé deux fois). */
    public function regler(Paiement $paiement): void
    {
        DB::transaction(function () use ($paiement) {
            $p = Paiement::whereKey($paiement->id)->lockForUpdate()->first();
            if (! $p || $p->statut === 'paye') {
                return;
            }

            $p->update(['statut' => 'paye', 'paye_le' => now()]);
            $p->vente()->where('statut', 'en_attente')->update(['statut' => 'payee']);

            $vente = $p->vente()->first();
            if ($vente && $vente->statut === 'payee') {
                $this->stock->sortirPourVente($vente);
            }
        });
    }
}
