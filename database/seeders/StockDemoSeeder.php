<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// À lancer APRÈS DemoSeeder : php artisan db:seed --class=StockDemoSeeder
// Donne un stock réaliste aux produits de démonstration, à partir des ventes déjà créées.
class StockDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // unités par paquet, et stock qu'il doit rester aujourd'hui (pour avoir des alertes variées)
        $reglages = [
            'Biscuits' => [24, 30],
            'Jus de bissap' => [12, 10],   // bas
            'Café Touba' => [50, 0],       // épuisé
            'Riz 1 kg' => [25, 40],
            'Huile 1 L' => [12, 4],        // bas
            'Sucre 1 kg' => [20, 18],
            'Savon' => [36, 55],
            'Eau minérale' => [6, 14],
        ];

        foreach (DB::table('boutiques')->pluck('id') as $boutiqueId) {
            // Nettoyage pour pouvoir relancer
            DB::table('approvisionnements')->where('boutique_id', $boutiqueId)->delete();
            DB::table('mouvements_stock')->where('boutique_id', $boutiqueId)->delete();

            $produits = DB::table('produits')->where('boutique_id', $boutiqueId)->where('actif', true)->get();
            if ($produits->isEmpty()) {
                continue;
            }

            $a1 = DB::table('approvisionnements')->insertGetId([
                'boutique_id' => $boutiqueId, 'fournisseur' => 'Grossiste Sandaga',
                'depense_fcfa' => 0, 'cree_le' => $now->copy()->subDays(95),
            ]);
            $a2 = DB::table('approvisionnements')->insertGetId([
                'boutique_id' => $boutiqueId, 'fournisseur' => 'Marché HLM',
                'depense_fcfa' => 0, 'cree_le' => $now->copy()->subDays(12),
            ]);
            $depense = [$a1 => 0, $a2 => 0];

            foreach ($produits as $p) {
                [$upl, $reste] = $reglages[$p->nom] ?? [1, 20];
                $coutUnitaire = $p->cout_fcfa ?? (int) round($p->prix_fcfa * 0.7);

                $vendus = (int) DB::table('lignes_vente')
                    ->join('ventes', 'ventes.id', '=', 'lignes_vente.vente_id')
                    ->where('ventes.statut', 'payee')
                    ->where('lignes_vente.produit_id', $p->id)
                    ->sum('lignes_vente.quantite');

                // Deux achats : le 1er couvre ~90 % des ventes, le 2e (un peu plus cher) complète
                $lots1 = max(1, (int) ceil(0.9 * $vendus / $upl));
                $unites1 = $lots1 * $upl;
                $manque = max(0, $vendus + $reste - $unites1);
                $lots2 = (int) ceil($manque / $upl);
                $unites2 = $lots2 * $upl;

                $cout2 = $coutUnitaire * 1.05;
                $achats = [[$a1, $lots1, $coutUnitaire, 95]];
                if ($lots2 > 0) {
                    $achats[] = [$a2, $lots2, $cout2, 12];
                }

                $valeur = 0;
                $unitesTotal = 0;
                foreach ($achats as [$appro, $lots, $cu, $joursAvant]) {
                    $prixLot = (int) round($cu * $upl);
                    DB::table('lignes_approvisionnement')->insert([
                        'approvisionnement_id' => $appro, 'produit_id' => $p->id, 'nom_produit' => $p->nom,
                        'nb_lots' => $lots, 'unites_par_lot' => $upl,
                        'prix_lot_fcfa' => $prixLot, 'cout_unitaire_fcfa' => $prixLot / $upl,
                    ]);
                    $depense[$appro] += $lots * $prixLot;
                    $unitesTotal += $lots * $upl;
                    $valeur += $lots * $prixLot;
                    DB::table('mouvements_stock')->insert([
                        'boutique_id' => $boutiqueId, 'produit_id' => $p->id, 'type' => 'achat',
                        'quantite' => $lots * $upl, 'stock_apres' => $unitesTotal,
                        'reference_type' => 'approvisionnement', 'reference_id' => $appro,
                        'cree_le' => $now->copy()->subDays($joursAvant),
                    ]);
                }

                // Écart d'inventaire : on force le stock réel voulu
                $calcule = $unitesTotal - $vendus;
                if ($calcule !== $reste) {
                    DB::table('mouvements_stock')->insert([
                        'boutique_id' => $boutiqueId, 'produit_id' => $p->id, 'type' => 'correction',
                        'quantite' => $reste - $calcule, 'stock_apres' => $reste,
                        'note' => 'Inventaire', 'cree_le' => $now->copy()->subDays(1),
                    ]);
                }

                $coutMoyen = $unitesTotal > 0 ? $valeur / $unitesTotal : $coutUnitaire;
                DB::table('produits')->where('id', $p->id)->update([
                    'suivre_stock' => true,
                    'unites_par_lot' => $upl,
                    'stock_actuel' => $reste,
                    'seuil_alerte' => null,
                    'cout_moyen_fcfa' => $coutMoyen,
                    'cout_fcfa' => (int) round($coutMoyen),
                ]);

                // Les anciennes ventes gardent le coût de l'époque
                DB::table('lignes_vente')->where('produit_id', $p->id)
                    ->whereNull('cout_unitaire_fcfa')->update(['cout_unitaire_fcfa' => $coutUnitaire]);
            }

            foreach ($depense as $id => $montant) {
                DB::table('approvisionnements')->where('id', $id)->update(['depense_fcfa' => $montant]);
            }
        }

        $this->command?->info('Stock de démonstration créé (achats, coûts, alertes).');
    }
}
