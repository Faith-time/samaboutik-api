<?php

namespace App\Services;

use App\Exceptions\PaiementIndisponibleException;
use App\Models\TauxChange;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Prix d'un bitcoin en FCFA. Le téléphone n'a jamais besoin de le connaître :
 * c'est Laravel qui convertit les FCFA en sats.
 */
class TauxChangeService
{
    public function btcEnFcfa(): float
    {
        return (float) Cache::remember(
            'taux_btc_fcfa',
            config('paiement.taux.cache_secondes'),
            function () {
                try {
                    $r = Http::timeout(10)->acceptJson()->get(config('paiement.taux.url'), [
                        'ids' => 'bitcoin',
                        'vs_currencies' => 'eur',
                    ]);
                    $eur = $r->json('bitcoin.eur');

                    if ($r->successful() && is_numeric($eur) && $eur > 0) {
                        // Le FCFA (XOF) a une parité fixe avec l'euro
                        $taux = $eur * config('paiement.taux.xof_par_eur');
                        TauxChange::create(['btc_fcfa' => $taux, 'recupere_le' => now()]);

                        return $taux;
                    }
                } catch (\Throwable $e) {
                    Log::warning('Taux de change indisponible : ' . $e->getMessage());
                }

                // Repli : dernier taux enregistré
                $dernier = TauxChange::orderByDesc('id')->value('btc_fcfa');
                if ($dernier) {
                    return (float) $dernier;
                }

                throw new PaiementIndisponibleException(
                    'Le prix du Bitcoin est indisponible pour le moment. Réessayez dans un instant.'
                );
            }
        );
    }

    /**
     * @return array{0:int,1:float} [montant en sats, taux utilisé]
     */
    public function fcfaEnSats(int $fcfa): array
    {
        $taux = $this->btcEnFcfa();
        $marge = 1 + config('paiement.taux.marge_pourcent') / 100;
        $sats = (int) ceil($fcfa / $taux * 100_000_000 * $marge);

        return [max($sats, 1), $taux];
    }
}
