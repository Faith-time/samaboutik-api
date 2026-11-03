<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PaiementIndisponibleException;
use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Models\Vente;
use App\Services\LightningService;
use App\Services\PaiementService;
use App\Services\PayDunyaService;
use App\Services\TauxChangeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaiementController extends Controller
{
    public function __construct(
        private PaiementService $paiements,
        private LightningService $lightning,
        private PayDunyaService $paydunya,
        private TauxChangeService $taux,
    ) {
    }

    private function venteEnAttente(Request $request, $venteId): Vente
    {
        $vente = $request->user()->boutique->ventes()->findOrFail($venteId);

        if ($vente->statut !== 'en_attente') {
            throw ValidationException::withMessages([
                'statut' => ['Cette vente n\'est pas en attente de paiement.'],
            ]);
        }

        return $vente;
    }

    public function especes(Request $request, $venteId)
    {
        $vente = $this->venteEnAttente($request, $venteId);

        $paiement = DB::transaction(function () use ($vente) {
            $paiement = $vente->paiements()->create([
                'methode' => 'especes',
                'montant_fcfa' => $vente->total_fcfa,
                'statut' => 'paye',
                'paye_le' => now(),
            ]);

            $vente->update(['statut' => 'payee']);

            // Le stock baisse maintenant que le paiement est confirmé
            app(\App\Services\StockService::class)->sortirPourVente($vente);

            return $paiement;
        });


        return response()->json($paiement, 201);
    }

    public function lightning(Request $request, $venteId)
    {
        $vente = $this->venteEnAttente($request, $venteId);

        // Une facture encore valide est réutilisée (pas de doublon si on revient sur l'écran)
        $existant = $vente->paiements()
            ->where('methode', 'lightning')
            ->where('statut', 'en_attente')
            ->where('expire_le', '>', now())
            ->latest('id')
            ->first();
        if ($existant) {
            return response()->json($existant);
        }

        // Les anciennes factures périmées sont fermées
        $vente->paiements()->where('methode', 'lightning')
            ->where('statut', 'en_attente')->update(['statut' => 'expire']);

        try {
            [$sats, $taux] = $this->taux->fcfaEnSats($vente->total_fcfa);
            $minutes = config('paiement.lightning.expiration_minutes');
            $facture = $this->lightning->creerFacture(
                $sats,
                'SamaBoutik - vente n°' . $vente->id,
                $minutes * 60
            );
        } catch (PaiementIndisponibleException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        $paiement = $vente->paiements()->create([
            'methode' => 'lightning',
            'montant_fcfa' => $vente->total_fcfa,
            'montant_sats' => $sats,
            'taux_btc_fcfa' => $taux,
            'reference_prestataire' => $facture['payment_hash'],
            'facture_lightning' => $facture['facture'],
            'statut' => 'en_attente',
            'expire_le' => now()->addMinutes($minutes),
        ]);

        return response()->json($paiement, 201);
    }

    public function mobile(Request $request, $venteId)
    {
        $data = $request->validate([
            'methode' => ['nullable', Rule::in(['wave', 'orange_money', 'free_money'])],
        ]);
        $methode = $data['methode'] ?? 'wave';

        $vente = $this->venteEnAttente($request, $venteId);

        $existant = $vente->paiements()
            ->where('methode', $methode)
            ->where('statut', 'en_attente')
            ->whereNotNull('url_paiement')
            ->where('expire_le', '>', now())
            ->latest('id')
            ->first();
        if ($existant) {
            return response()->json($existant);
        }

        $paiement = $vente->paiements()->create([
            'methode' => $methode,
            'montant_fcfa' => $vente->total_fcfa,
            'statut' => 'en_attente',
            'expire_le' => now()->addMinutes(30),
        ]);

        try {
            $facture = $this->paydunya->creerFacture(
                $vente->total_fcfa,
                'Vente n°' . $vente->id,
                $request->user()->boutique->nom,
                ['vente_id' => $vente->id, 'paiement_id' => $paiement->id],
            );
        } catch (PaiementIndisponibleException $e) {
            $paiement->delete();

            return response()->json(['message' => $e->getMessage()], 503);
        }

        $paiement->update([
            'reference_prestataire' => $facture['token'],
            'url_paiement' => $facture['url'],
        ]);

        return response()->json($paiement->refresh(), 201);
    }

    public function show(Request $request, $id)
    {
        $paiement = Paiement::whereHas('vente', function ($q) use ($request) {
            $q->where('boutique_id', $request->user()->boutique->id);
        })->findOrFail($id);

        // L'application interroge toutes les 3 s : on ne questionne le prestataire que toutes les 3 s aussi.
        if ($paiement->statut === 'en_attente' && Cache::add('rafraichir_paiement_' . $paiement->id, 1, 3)) {
            $paiement = $this->paiements->rafraichir($paiement);
        }

        return $paiement;
    }
}
