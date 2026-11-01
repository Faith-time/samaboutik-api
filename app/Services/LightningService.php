<?php

namespace App\Services;

use App\Exceptions\PaiementIndisponibleException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Factures Bitcoin Lightning via l'API LNbits.
 * (Pour un autre prestataire : garder ces deux méthodes publiques et changer l'intérieur.)
 */
class LightningService
{
    private function http(): PendingRequest
    {
        $url = config('paiement.lightning.url');
        $cle = config('paiement.lightning.cle');

        if (! $url || ! $cle) {
            throw new PaiementIndisponibleException(
                'Le paiement Bitcoin n\'est pas encore configuré.'
            );
        }

        return Http::baseUrl(rtrim($url, '/'))
            ->withHeaders(['X-Api-Key' => $cle])
            ->acceptJson()
            ->timeout(15);
    }

    /**
     * @return array{payment_hash:string, facture:string}
     */
    public function creerFacture(int $sats, string $memo, int $expirationSecondes): array
    {
        $corps = [
            'out' => false,
            'amount' => $sats,
            'memo' => $memo,
            'expiry' => $expirationSecondes,
        ];

        $secret = config('paiement.lightning.secret_webhook');
        if (! app()->isLocal() && $secret) {
            $corps['webhook'] = url('/api/webhooks/lightning') . '?secret=' . urlencode($secret);
        }

        try {
            $r = $this->http()->post('/api/v1/payments', $corps);
        } catch (PaiementIndisponibleException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Lightning : ' . $e->getMessage());
            throw new PaiementIndisponibleException(
                'Le paiement Bitcoin est momentanément indisponible. Réessayez.'
            );
        }

        $hash = $r->json('payment_hash');
        $facture = $r->json('payment_request') ?? $r->json('bolt11');

        if (! $r->successful() || ! $hash || ! $facture) {
            Log::error('Lightning : réponse inattendue', ['status' => $r->status(), 'corps' => $r->body()]);
            throw new PaiementIndisponibleException(
                'Impossible de créer la facture Bitcoin. Réessayez.'
            );
        }

        return ['payment_hash' => $hash, 'facture' => $facture];
    }

    /** @return 'paye'|'en_attente' */
    public function statut(string $paymentHash): string
    {
        try {
            $r = $this->http()->get('/api/v1/payments/' . $paymentHash);
        } catch (\Throwable $e) {
            Log::warning('Lightning (statut) : ' . $e->getMessage());

            return 'en_attente';
        }

        return ($r->successful() && $r->json('paid') === true) ? 'paye' : 'en_attente';
    }
}
