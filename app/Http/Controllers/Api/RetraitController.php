<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RetraitController extends Controller
{
    private function solde($boutique): int
    {
        $encaisse = Paiement::where('statut', 'paye')
            ->where('methode', '!=', 'especes')
            ->whereHas('vente', fn ($q) => $q->where('boutique_id', $boutique->id))
            ->sum('montant_fcfa');

        return (int) $encaisse - (int) $boutique->retraits()->sum('montant_fcfa');
    }

    public function index(Request $request)
    {
        $boutique = $request->user()->boutique;

        return [
            'solde_fcfa' => $this->solde($boutique),
            'retraits' => $boutique->retraits()->latest('cree_le')->get(),
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'montant_fcfa' => ['required', 'integer', 'min:1'],
            'operateur' => ['required', 'in:wave,orange_money,free_money'],
        ]);

        $boutique = $request->user()->boutique;

        if ($data['montant_fcfa'] > $this->solde($boutique)) {
            throw ValidationException::withMessages([
                'montant_fcfa' => ['Solde insuffisant.'],
            ]);
        }

        $retrait = $boutique->retraits()->create($data);

        return response()->json($retrait, 201);
    }
}
