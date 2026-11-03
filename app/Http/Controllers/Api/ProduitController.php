<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProduitController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->boutique->produits()->orderBy('nom')->get();
    }

    public function store(Request $request, StockService $stock)
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'prix_fcfa' => ['required', 'integer', 'min:1'],
            'cout_fcfa' => ['nullable', 'integer', 'min:0'],
            'actif' => ['sometimes', 'boolean'],
            'suivre_stock' => ['sometimes', 'boolean'],
            'unites_par_lot' => ['sometimes', 'integer', 'min:1'],
            'seuil_alerte' => ['nullable', 'integer', 'min:0'],
            'stock_initial' => ['nullable', 'integer', 'min:0'],
        ]);

        $stockInitial = (int) ($data['stock_initial'] ?? 0);
        unset($data['stock_initial']);
        if (array_key_exists('cout_fcfa', $data)) {
            $data['cout_moyen_fcfa'] = $data['cout_fcfa'];
        }

        $produit = DB::transaction(function () use ($request, $data, $stockInitial, $stock) {
            $produit = $request->user()->boutique->produits()->create($data);
            if ($produit->suivre_stock && $stockInitial > 0) {
                $stock->entrer($produit, $stockInitial, 'stock_initial', null, null, 'Stock de départ');
            }

            return $produit;
        });

        return response()->json($produit->refresh(), 201);
    }

    public function show(Request $request, $id)
    {
        return $request->user()->boutique->produits()->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $produit = $request->user()->boutique->produits()->findOrFail($id);

        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:255'],
            'prix_fcfa' => ['sometimes', 'integer', 'min:1'],
            'cout_fcfa' => ['nullable', 'integer', 'min:0'],
            'actif' => ['sometimes', 'boolean'],
            'suivre_stock' => ['sometimes', 'boolean'],
            'unites_par_lot' => ['sometimes', 'integer', 'min:1'],
            'seuil_alerte' => ['nullable', 'integer', 'min:0'],
        ]);

        // Un coût saisi à la main devient le coût moyen
        if (array_key_exists('cout_fcfa', $data)) {
            $data['cout_moyen_fcfa'] = $data['cout_fcfa'];
        }

        $produit->update($data);

        return $produit->refresh();
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->boutique->produits()->findOrFail($id)->delete();

        return response()->noContent();
    }
}
