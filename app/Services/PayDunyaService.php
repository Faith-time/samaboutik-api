<?php

namespace App\Services;

use App\Exceptions\PaiementIndisponibleException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Paiements mobiles (Wave, Orange Money, Free Money) via PayDunya « Checkout Invoice ».
 * Le client choisit son opérateur sur la page de paiement PayDunya (ouverte dans l'application).
 */
class PayDunyaService
{
    private function http(): PendingRequest
    {
        $c = config('paiement.paydunya');

        if (! $c['master_key'] || ! $c['private_key'] || ! $c['token']) {
            throw new PaiementIndisponibleException(
                'Le paiement mobile n\'est pas encore configuré.'
            );
        }

        $base = $c['mode'] === 'live'
            ? 'https://app.paydunya.com/api/v1'
            : 'https://app.paydunya.com/sandbox-api/v1';

        return Http::baseUrl($base)
            ->withHeaders([
                'PAYDUNYA-MASTER-KEY' => $c['master_key'],
                'PAYDUNYA-PRIVATE-KEY' => $c['private_key'],
                'PAYDUNYA-PUBLIC-KEY' => $c['public_key'] ?? '',
                'PAYDUNYA-TOKEN' => $c['token'],
            ])
            ->acceptJson()
            ->timeout(20);
    }

    /**
     * @return array{token:string, url:string}
     */
    public function creerFacture(int $montantFcfa, string $description, string $nomBoutique, array $donnees): array
    {
        $corps = [
            'invoice' => [
                'total_amount' => $montantFcfa,
                'description' => $description,
            ],
            'store' => ['name' => $nomBoutique],
            'custom_data' => $donnees,
        ];

        // En local, PayDunya ne peut pas joindre ton PC : le statut est alors vérifié par interrogation.
        if (! app()->isLocal()) {
            $corps['actions'] = ['callback_url' => url('/api/webhooks/paydunya')];
        }

        try {
            $r = $this->http()->post('/checkout-invoice/create', $corps);
        } catch (PaiementIndisponibleException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('PayDunya : ' . $e->getMessage());
            throw new PaiementIndisponibleException(
                'Le paiement mobile est momentanément indisponible. Réessayez.'
            );
        }

        if ($r->json('response_code') !== '00' || ! $r->json('token')) {
            Log::error('PayDunya : création refusée', ['corps' => $r->body()]);
            throw new PaiementIndisponibleException(
                'Impossible de lancer le paiement mobile. Réessayez.'
            );
        }

        return ['token' => $r->json('token'), 'url' => $r->json('response_text')];
    }

    /** @return 'paye'|'echoue'|'en_attente' */
    public function statut(string $token, int $montantAttendu): string
    {
        try {
            $r = $this->http()->get('/checkout-invoice/confirm/' . $token);
        } catch (\Throwable $e) {
            Log::warning('PayDunya (statut) : ' . $e->getMessage());

            return 'en_attente';
        }

        if ($r->json('response_code') !== '00') {
            return 'en_attente';
        }

        switch ($r->json('status')) {
            case 'completed':
                // On vérifie que le montant payé est bien celui de la vente
                $paye = (int) round((float) $r->json('invoice.total_amount'));
                if ($paye !== $montantAttendu) {
                    Log::error('PayDunya : montant différent', compact('paye', 'montantAttendu', 'token'));

                    return 'en_attente';
                }

                return 'paye';
            case 'cancelled':
            case 'failed':
                return 'echoue';
            default:
                return 'en_attente';
        }
    }
}
