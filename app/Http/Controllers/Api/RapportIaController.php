<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Produit;
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

    private function fcfa(float|int $n): string
    {
        return number_format((int) round($n), 0, ',', ' ') . ' FCFA';
    }

    /** Analyse construite à partir des vraies données : ventes, marges et stock des 30 derniers jours. */
    public function courant(Request $request)
    {$duree =
        $boutiqueId = DB::table('boutiques')
            ->where('utilisateur_id', $request->user()->id)->value('id');
        $debut = now()->subDays(29)->startOfDay();
        $cout = 'COALESCE(lignes_vente.cout_unitaire_fcfa, produits.cout_moyen_fcfa, produits.cout_fcfa)';

        // Ventes par produit
        $ventes = DB::table('lignes_vente')
            ->join('ventes', 'ventes.id', '=', 'lignes_vente.vente_id')
            ->leftJoin('produits', 'produits.id', '=', 'lignes_vente.produit_id')
            ->where('ventes.boutique_id', $boutiqueId)
            ->where('ventes.statut', 'payee')
            ->where('ventes.cree_le', '>=', $debut)
            ->whereNotNull('lignes_vente.produit_id')
            ->groupBy('lignes_vente.produit_id')
            ->selectRaw("
                lignes_vente.produit_id as id,
                MAX(lignes_vente.nom_produit) as nom,
                SUM(lignes_vente.quantite) as qte,
                SUM(lignes_vente.prix_unitaire_fcfa * lignes_vente.quantite) as ca,
                SUM(CASE WHEN $cout IS NULL THEN 0 ELSE (lignes_vente.prix_unitaire_fcfa - $cout) * lignes_vente.quantite END) as benefice,
                SUM(CASE WHEN $cout IS NULL THEN 1 ELSE 0 END) as sans_cout,
                COUNT(*) as nb_lignes
            ")
            ->orderByDesc('qte')->get();

        $produits = Produit::where('boutique_id', $boutiqueId)->where('actif', true)->get()->keyBy('id');
        $conseils = [];

        // 1. Produits à racheter (épuisés ou presque)
        $aRacheter = [];
        foreach ($produits as $p) {
            if (! $p->suivre_stock || ! in_array($p->statut_stock, ['bas', 'epuise'], true)) {
                continue;
            }
            $v = $ventes->firstWhere('id', $p->id);
            $qte30 = $v ? (int) $v->qte : 0;
            $rang = $v ? $ventes->search(fn ($x) => (int) $x->id === (int) $p->id) + 1 : null;
            $parJour = $qte30 / 30;
            $jours = $parJour > 0 ? (int) floor(max($p->stock_actuel, 0) / $parJour) : null;
            $aRacheter[] = compact('p', 'rang', 'jours', 'qte30');
        }
        usort($aRacheter, fn ($a, $b) => ($a['jours'] ?? 9999) <=> ($b['jours'] ?? 9999));
        foreach (array_slice($aRacheter, 0, 3) as $x) {
            $p = $x['p'];
            if ($p->statut_stock === 'epuise') {
                $conseils[] = "{$p->nom} est épuisé."
                    . ($x['rang'] ? " C'est votre n°{$x['rang']} des ventes : rachetez-le vite pour ne pas perdre de ventes." : ' Rachetez-le si vous voulez continuer à le vendre.');
            } else {
                $duree = $x['jours'] !== null ? " (environ {$x['jours']} jour" . ($x['jours'] > 1 ? 's' : '') . ' de ventes)' : '';
                $unites = $p->stock_actuel . ' unité' . ($p->stock_actuel > 1 ? 's' : '');
                $conseils[] = "{$p->nom} : il ne reste que {$unites}{$duree}. Pensez à le racheter.";
            }
        }

        // 2. Le produit le plus rentable
        $rentables = $ventes->filter(fn ($v) => (int) $v->sans_cout < (int) $v->nb_lignes)->sortByDesc('benefice');
        if ($rentables->isNotEmpty() && $rentables->first()->benefice > 0) {
            $r = $rentables->first();
            $conseils[] = "{$r->nom} est votre produit le plus rentable : {$this->fcfa($r->benefice)} de bénéfice sur 30 jours.";
        }

        // 3. Marges faibles (moins de 15 % du prix de vente)
        foreach ($ventes->take(10) as $v) {
            $p = $produits->get($v->id);
            if (! $p || $p->cout_moyen_fcfa === null || $p->prix_fcfa <= 0) {
                continue;
            }
            $marge = ($p->prix_fcfa - $p->cout_moyen_fcfa) / $p->prix_fcfa * 100;
            if ($marge < 15) {
                $conseils[] = 'La marge sur ' . $p->nom . ' est faible (' . round($marge) . ' %). Négociez le prix d\'achat ou ajustez votre prix de vente.';
                break;
            }
        }

        // 4. Produits qui ne se vendent plus (et qui immobilisent de l'argent)
        $dormants = $produits->filter(function ($p) use ($ventes) {
            return $ventes->firstWhere('id', $p->id) === null
                && $p->cree_le && $p->cree_le->lt(now()->subDays(30))
                && ($p->suivre_stock ? $p->stock_actuel > 0 : true);
        })->take(2);
        foreach ($dormants as $p) {
            $valeur = $p->suivre_stock && $p->cout_moyen_fcfa
                ? ' Cela immobilise environ ' . $this->fcfa($p->stock_actuel * $p->cout_moyen_fcfa) . ' de stock.' : '';
            $conseils[] = "{$p->nom} ne s'est pas vendu depuis 30 jours.{$valeur}";
        }

        // 5. Jour fort
        $jourFort = DB::table('ventes')
            ->where('boutique_id', $boutiqueId)
            ->where('statut', 'payee')
            ->where('cree_le', '>=', $debut)
            ->selectRaw('DAYOFWEEK(cree_le) as j, SUM(total_fcfa) as total')
            ->groupBy('j')->orderByDesc('total')->first();
        $noms = [1 => 'dimanche', 2 => 'lundi', 3 => 'mardi', 4 => 'mercredi',
            5 => 'jeudi', 6 => 'vendredi', 7 => 'samedi'];
        if ($jourFort) {
            $conseils[] = 'Vos ventes sont souvent plus élevées le ' . $noms[$jourFort->j] . '. Vérifiez votre stock la veille.';
        }

        $star = $ventes->first();
        $resume = $star
            ? "Votre produit star du mois : {$star->nom} ({$star->qte} vendus, {$this->fcfa($star->ca)})."
            : 'Enregistrez quelques ventes pour recevoir une analyse.';

        return response()->json([
            'resume' => $resume,
            'conseils' => array_slice($conseils, 0, 8),
            'cree_le' => now()->toIso8601String(),
        ]);
    }
}
