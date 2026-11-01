<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EvenementWebhook;
use App\Models\Paiement;
use App\Services\PaiementService;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(private PaiementService $paiements)
    {
    }

    public function lightning(Request $request)
    {
        // Les webhooks LNbits ne sont pas signés : l'adresse contient un secret (?secret=...)
        $secret = config('paiement.lightning.secret_webhook');
        if ($secret && ! hash_equals($secret, (string) $request->query('secret'))) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $hash = $request->input('payment_hash') ?? $request->input('checking_id');

        return $this->traiter($request, 'lightning', $hash, $hash);
    }

    public function paydunya(Request $request)
    {
        // PayDunya envoie data.hash = SHA-512 de la clé maître
        $attendu = hash('sha512', (string) config('paiement.paydunya.master_key'));
        if (! hash_equals($attendu, (string) $request->input('data.hash'))) {
            return response()->json(['message' => 'Signature invalide.'], 403);
        }

        $token = $request->input('data.invoice.token');
        $evenementId = $token ? $token . ':' . $request->input('data.status') : null;

        return $this->traiter($request, 'paydunya', $evenementId, $token);
    }

    private function traiter(Request $request, string $prestataire, ?string $evenementId, ?string $reference)
    {
        if (! $evenementId || ! $reference) {
            return response()->json(['message' => 'Identifiant manquant.'], 422);
        }

        $evenement = EvenementWebhook::firstOrCreate(
            ['prestataire' => $prestataire, 'evenement_id' => $evenementId],
            ['donnees' => $request->all()]
        );

        if (! $evenement->wasRecentlyCreated && $evenement->traite_le) {
            return response()->json(['message' => 'Déjà reçu.']);
        }

        // Le webhook n'est qu'un signal : le vrai statut est redemandé au prestataire.
        $paiement = Paiement::where('reference_prestataire', $reference)->first();
        if ($paiement) {
            $this->paiements->rafraichir($paiement);
        }

        $evenement->update(['traite_le' => now()]);

        return response()->json(['message' => 'Reçu.']);
    }
}
