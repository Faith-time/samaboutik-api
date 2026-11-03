<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TableauBordController extends Controller
{
    public function index(Request $request)
    {
        $boutiqueId = DB::table('boutiques')
            ->where('utilisateur_id', $request->user()->id)->value('id');
        if (!$boutiqueId) {
            return response()->json(['message' => 'Boutique introuvable.'], 404);
        }

        // Paiements payés de cette boutique
        $base = fn () => DB::table('paiements')
            ->join('ventes', 'ventes.id', '=', 'paiements.vente_id')
            ->where('ventes.boutique_id', $boutiqueId)
            ->where('paiements.statut', 'paye');

        $debutJour = Carbon::now()->startOfDay();
        $debutSemaine = Carbon::now()->subDays(6)->startOfDay();
        $debutSemainePrec = $debutSemaine->copy()->subDays(7);
        $debut30j = Carbon::now()->subDays(29)->startOfDay();

        $jour = (int) $base()->where('paiements.paye_le', '>=', $debutJour)->sum('paiements.montant_fcfa');
        $nbJour = $base()->where('paiements.paye_le', '>=', $debutJour)->distinct()->count('paiements.vente_id');

        $semaine = (int) $base()->where('paiements.paye_le', '>=', $debutSemaine)->sum('paiements.montant_fcfa');
        $nbSemaine = $base()->where('paiements.paye_le', '>=', $debutSemaine)->distinct()->count('paiements.vente_id');
        $semainePrec = (int) $base()->whereBetween('paiements.paye_le', [$debutSemainePrec, $debutSemaine])->sum('paiements.montant_fcfa');

        $total30 = (int) $base()->where('paiements.paye_le', '>=', $debut30j)->sum('paiements.montant_fcfa');
        $bitcoin30 = (int) $base()->where('paiements.paye_le', '>=', $debut30j)->where('paiements.methode', 'lightning')->sum('paiements.montant_fcfa');
        $bitcoin30Sats = (int) $base()->where('paiements.paye_le', '>=', $debut30j)->where('paiements.methode', 'lightning')->sum('paiements.montant_sats');

        // Courbe des 7 derniers jours
        $parJour = $base()->where('paiements.paye_le', '>=', $debutSemaine)
            ->selectRaw('DATE(paiements.paye_le) as jour, SUM(paiements.montant_fcfa) as total')
            ->groupBy('jour')->pluck('total', 'jour');
        $serie = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $debutSemaine->copy()->addDays($i)->toDateString();
            $serie[] = ['date' => $d, 'total_fcfa' => (int) ($parJour[$d] ?? 0)];
        }

        // Répartition par moyen de paiement (30 jours)
        $parMethode = $base()->where('paiements.paye_le', '>=', $debut30j)
            ->selectRaw('paiements.methode as methode, SUM(paiements.montant_fcfa) as total_fcfa, SUM(paiements.montant_sats) as total_sats')
            ->groupBy('paiements.methode')->get()
            ->map(fn ($m) => [
                'methode' => $m->methode,
                'total_fcfa' => (int) $m->total_fcfa,
                'total_sats' => $m->total_sats !== null ? (int) $m->total_sats : null,
            ])->values();

        // Bénéfice réel : (prix de vente - coût du moment) x quantité, sur les 30 derniers jours
        $coutSql = 'COALESCE(lignes_vente.cout_unitaire_fcfa, produits.cout_moyen_fcfa, produits.cout_fcfa)';
        $b = DB::table('lignes_vente')
            ->join('ventes', 'ventes.id', '=', 'lignes_vente.vente_id')
            ->leftJoin('produits', 'produits.id', '=', 'lignes_vente.produit_id')
            ->where('ventes.boutique_id', $boutiqueId)
            ->where('ventes.statut', 'payee')
            ->where('ventes.cree_le', '>=', $debut30j)
            ->whereRaw("$coutSql IS NOT NULL")
            ->selectRaw("SUM((lignes_vente.prix_unitaire_fcfa - $coutSql) * lignes_vente.quantite) as b")
            ->value('b');

        return response()->json([
            'ventes_jour_fcfa'     => $jour,
            'ventes_semaine_fcfa'  => $semaine,
            'nb_ventes_jour'       => $nbJour,
            'panier_moyen_fcfa'    => $nbSemaine > 0 ? (int) round($semaine / $nbSemaine) : 0,
            'total_30j_fcfa'       => $total30,
            'bitcoin_30j_fcfa'     => $bitcoin30,
            'bitcoin_30j_sats'     => $bitcoin30Sats,
            'variation_pct'        => $semainePrec > 0 ? round(($semaine - $semainePrec) / $semainePrec * 100, 1) : null,
            'benefice_estime_fcfa' => $b !== null ? (int) round($b) : null,
            'serie'                => $serie,
            'par_methode'          => $parMethode,
        ]);
    }
}
