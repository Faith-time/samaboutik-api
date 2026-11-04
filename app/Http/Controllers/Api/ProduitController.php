<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
        $produit = $request->user()->boutique->produits()->findOrFail($id);
        $image = $produit->image_path;
        $produit->delete();
        if ($image) {
            Storage::disk('public')->delete($image);
        }

        return response()->noContent();
    }

    /** Ajoute ou remplace la photo d'un produit (multipart, champ « image »). */
    public function televerserImage(Request $request, $id)
    {
        $produit = $request->user()->boutique->produits()->findOrFail($id);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], // 5 Mo
        ], [
            'image.image' => 'Le fichier choisi n\'est pas une image.',
            'image.mimes' => 'Formats acceptés : JPG, PNG ou WebP.',
            'image.max' => 'L\'image est trop lourde (5 Mo maximum).',
            'image.uploaded' => 'L\'image n\'a pas pu être envoyée (trop lourde pour le serveur).',
        ]);

        $ancienne = $produit->image_path;
        $chemin = $request->file('image')->store('produits', 'public'); // nom aléatoire
        $produit->update(['image_path' => $chemin]);

        if ($ancienne) {
            Storage::disk('public')->delete($ancienne);
        }

        return $produit->refresh();
    }

    /** Retire la photo d'un produit. */
    public function supprimerImage(Request $request, $id)
    {
        $produit = $request->user()->boutique->produits()->findOrFail($id);

        if ($produit->image_path) {
            Storage::disk('public')->delete($produit->image_path);
            $produit->update(['image_path' => null]);
        }

        return $produit->refresh();
    }
}
