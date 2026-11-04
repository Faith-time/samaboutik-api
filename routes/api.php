<?php

use App\Http\Controllers\Api\ApprovisionnementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PaiementController;
use App\Http\Controllers\Api\ProduitController;
use App\Http\Controllers\Api\RapportIaController;
use App\Http\Controllers\Api\RetraitController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\TableauBordController;
use App\Http\Controllers\Api\TauxChangeController;
use App\Http\Controllers\Api\VenteController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => ['message' => 'SamaBoutik API OK']);

// Public
Route::post('/inscription', [AuthController::class, 'inscription']);
Route::post('/connexion', [AuthController::class, 'connexion']);

// Webhooks des prestataires (pas de token : ils viennent de l'extérieur)
Route::post('/webhooks/lightning', [WebhookController::class, 'lightning']);
Route::post('/webhooks/paydunya', [WebhookController::class, 'paydunya']);

// Connecté (tous les rôles)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/moi', [AuthController::class, 'moi']);
    Route::post('/deconnexion', [AuthController::class, 'deconnexion']);
    Route::get('/taux-change', [TauxChangeController::class, 'dernier']);

    // Réservé aux commerçants (ils ont une boutique)
    Route::middleware('role:commercant')->group(function () {
        Route::apiResource('produits', ProduitController::class);
        Route::post('produits/{id}/image', [ProduitController::class, 'televerserImage']);
        Route::delete('produits/{id}/image', [ProduitController::class, 'supprimerImage']);

        Route::get('/ventes', [VenteController::class, 'index']);
        Route::post('/ventes', [VenteController::class, 'store']);
        Route::get('/ventes/{id}', [VenteController::class, 'show']);
        Route::post('/ventes/{id}/annuler', [VenteController::class, 'annuler']);

        Route::post('/ventes/{id}/paiements/especes', [PaiementController::class, 'especes']);
        Route::post('/ventes/{id}/paiements/lightning', [PaiementController::class, 'lightning']);
        Route::post('/ventes/{id}/paiements/mobile', [PaiementController::class, 'mobile']);
        Route::get('/paiements/{id}', [PaiementController::class, 'show']);

        Route::get('/retraits', [RetraitController::class, 'index']);
        Route::post('/retraits', [RetraitController::class, 'store']);

        Route::get('/tableau-de-bord', [TableauBordController::class, 'index']);
        Route::get('/rapport-ia', [RapportIaController::class, 'courant']);
        Route::get('/rapports-ia', [RapportIaController::class, 'index']);
        Route::get('/rapports-ia/{id}', [RapportIaController::class, 'show']);

        Route::get('/stock', [StockController::class, 'index']);
        Route::get('/classement', [StockController::class, 'classement']);
        Route::post('/produits/{id}/stock/correction', [StockController::class, 'correction']);

        Route::get('/approvisionnements', [ApprovisionnementController::class, 'index']);
        Route::post('/approvisionnements', [ApprovisionnementController::class, 'store']);

    });
});
