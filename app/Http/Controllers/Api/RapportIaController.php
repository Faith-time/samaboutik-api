<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RapportIaController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->boutique->rapportsIa()->latest('cree_le')->get();
    }

    public function show(Request $request, $id)
    {
        return $request->user()->boutique->rapportsIa()->findOrFail($id);
    }

    public function courant(Request $request)
    {
        $boutiqueId = DB::table('boutiques')
            ->where('utilisateur_id', $request->user()->id)->value('id');
        $debut = now()->subDays(29)->startOfDay();

        $top = DB::table('lignes_vente')
            ->join('ventes', 'ventes.id', '=', 'lignes_vente.vente_id')
            ->where('ventes.boutique_id', $boutiqueId)
            ->where('ventes.statut', 'payee')
            ->where('ventes.cree_le', '>=', $debut)
            ->groupBy('lignes_vente.nom_produit')
            ->selectRaw('lignes_vente.nom_produit as nom, SUM(lignes_vente.quantite) as qte')
            ->orderByDesc('qte')->limit(3)->get();

        $jourFort = DB::table('ventes')
            ->where('boutique_id', $boutiqueId)
            ->where('statut', 'payee')
            ->where('cree_le', '>=', $debut)
            ->selectRaw('DAYOFWEEK(cree_le) as j, SUM(total_fcfa) as total')
            ->groupBy('j')->orderByDesc('total')->first();

        $noms = [1 => 'dimanche', 2 => 'lundi', 3 => 'mardi', 4 => 'mercredi',
            5 => 'jeudi', 6 => 'vendredi', 7 => 'samedi'];

        $conseils = [];
        if ($top->isNotEmpty()) {
            $conseils[] = 'Pensez à vérifier votre stock de ' . $top->first()->nom . ', c\'est votre produit le plus vendu.';
        }
        if ($jourFort) {
            $conseils[] = 'Vos ventes sont souvent plus élevées le ' . $noms[$jourFort->j] . '.';
        }

        return response()->json([
            'resume' => $top->isNotEmpty()
                ? 'Votre produit star du mois : ' . $top->first()->nom . '.'
                : 'Enregistrez quelques ventes pour recevoir une analyse.',
            'conseils' => $conseils,
            'cree_le' => now()->toIso8601String(),
        ]);
    }
}
