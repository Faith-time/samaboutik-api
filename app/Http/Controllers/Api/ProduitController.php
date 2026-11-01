<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProduitController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->boutique->produits()->orderBy('nom')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'prix_fcfa' => ['required', 'integer', 'min:1'],
            'cout_fcfa' => ['nullable', 'integer', 'min:0'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $produit = $request->user()->boutique->produits()->create($data);

        return response()->json($produit, 201);
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
        ]);

        $produit->update($data);

        return $produit;
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->boutique->produits()->findOrFail($id)->delete();

        return response()->noContent();
    }
}
