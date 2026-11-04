<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(42); // mêmes données à chaque exécution
        $maintenant = Carbon::now();

        // ---------- 1. Compte et boutique ----------
        // On utilise le premier compte existant (le tien). S'il n'y en a aucun,
        // on crée un compte de démonstration : 770000000 / password123
        $utilisateur = DB::table('utilisateurs')->orderBy('id')->first();
        if (!$utilisateur) {
            $id = DB::table('utilisateurs')->insertGetId([
                'nom' => 'Awa Diop',
                'telephone' => '770000000',
                'mot_de_passe' => Hash::make('password123'),
                'cree_le' => $maintenant,
                'modifie_le' => $maintenant,
            ]);
            \App\Models\Utilisateur::find($utilisateur->id)->assignRole('commercant');

            $utilisateur = DB::table('utilisateurs')->find($id);
        }

        $boutique = DB::table('boutiques')->where('utilisateur_id', $utilisateur->id)->first();
        if (!$boutique) {
            $id = DB::table('boutiques')->insertGetId([
                'utilisateur_id' => $utilisateur->id,
                'nom' => 'Boutique Awa',
                'cree_le' => $maintenant,
                'modifie_le' => $maintenant,
            ]);
            $boutique = DB::table('boutiques')->find($id);
        }
        $boutiqueId = $boutique->id;

        // ---------- 2. Nettoyage (pour pouvoir relancer le seeder) ----------
        // Les lignes de vente et les paiements disparaissent avec les ventes (cascade).
        DB::table('ventes')->where('boutique_id', $boutiqueId)->delete();
        DB::table('produits')->where('boutique_id', $boutiqueId)->delete();
        DB::table('retraits')->where('boutique_id', $boutiqueId)->delete();
        DB::table('rapports_ia')->where('boutique_id', $boutiqueId)->delete();

        $catalogue = [
            // nom, prix de vente, coût d'une unité, actif
            ['Biscuits crème',     150,  100, true],
            ['Jus de bissap',      300,  200, true],
            ['Café Touba',         200,  140, true],
            ['Riz brisé 1 kg',     350,  270, true],
            ['Huile 1 L',         1100, 1000, true],
            ['Sucre 1 kg',         600,  560, true],
            ['Savon',              250,  190, true],
            ['Eau minérale 1,5 L', 400,  250, true],
            ['Lait en poudre',    2500, 2200, false],
        ];

        $produits = [];
        foreach ($catalogue as [$nom, $prix, $cout, $actif]) {
            $id = DB::table('produits')->insertGetId([
                'boutique_id' => $boutiqueId,
                'nom' => $nom,
                'prix_fcfa' => $prix,
                'cout_fcfa' => $cout,
                'actif' => $actif,
                'cree_le' => $maintenant->copy()->subDays(95),
                'modifie_le' => $maintenant->copy()->subDays(95),
            ]);
            if ($actif) {
                $produits[] = ['id' => $id, 'nom' => $nom, 'prix' => $prix];
            }
        }

        // ---------- 4. Ventes, lignes et paiements sur 90 jours ----------
        $poidsMethodes = [
            'especes' => 50, 'lightning' => 25, 'wave' => 12,
            'orange_money' => 8, 'free_money' => 5,
        ];
        $tirer = function (array $poids) {
            $r = mt_rand(1, array_sum($poids));
            foreach ($poids as $cle => $p) {
                $r -= $p;
                if ($r <= 0) return $cle;
            }
            return array_key_first($poids);
        };

        for ($jour = 89; $jour >= 0; $jour--) {
            $date = $maintenant->copy()->subDays($jour);
            $nb = $jour === 0
                ? 4
                : (in_array($date->dayOfWeekIso, [5, 6]) ? mt_rand(1, 3) : mt_rand(0, 2));
            if ($jour < 30) $nb += mt_rand(0, 1); // l'activité progresse

            for ($i = 0; $i < $nb; $i++) {
                if ($jour === 0) {
                    $minutes = max(1, $maintenant->hour * 60 + $maintenant->minute);
                    $moment = $maintenant->copy()->startOfDay()->addMinutes(mt_rand(0, $minutes - 1));
                } else {
                    $moment = $date->copy()->setTime(mt_rand(8, 20), mt_rand(0, 59));
                }

                // Aujourd'hui : la dernière vente reste « en attente »
                if ($jour === 0) {
                    $statut = ($i === $nb - 1) ? 'en_attente' : 'payee';
                } else {
                    $statut = mt_rand(1, 100) <= 9 ? 'annulee' : 'payee';
                }

                // Lignes de la vente
                $cles = (array) array_rand($produits, mt_rand(1, 3));
                $lignes = [];
                $total = 0;
                foreach ($cles as $cle) {
                    $p = $produits[$cle];
                    $qte = mt_rand(1, 3);
                    $total += $p['prix'] * $qte;
                    $lignes[] = [
                        'produit_id' => $p['id'],
                        'nom_produit' => $p['nom'],
                        'prix_unitaire_fcfa' => $p['prix'],
                        'quantite' => $qte,
                    ];
                }

                $venteId = DB::table('ventes')->insertGetId([
                    'boutique_id' => $boutiqueId,
                    'total_fcfa' => $total,
                    'statut' => $statut,
                    'cree_le' => $moment,
                    'modifie_le' => $moment,
                ]);

                foreach ($lignes as $l) {
                    DB::table('lignes_vente')->insert($l + ['vente_id' => $venteId]);
                }

                if ($statut === 'payee') {
                    $methode = $tirer($poidsMethodes);
                    $sats = null;
                    $taux = null;
                    $reference = null;
                    if ($methode === 'lightning') {
                        $taux = 62000000 + mt_rand(-1500000, 1500000); // FCFA pour 1 BTC
                        $sats = (int) round($total / $taux * 100000000);
                        $reference = 'seed-ln-' . $venteId;
                    } elseif ($methode !== 'especes') {
                        $reference = 'seed-pay-' . $venteId;
                    }

                    DB::table('paiements')->insert([
                        'vente_id' => $venteId,
                        'methode' => $methode,
                        'montant_fcfa' => $total,
                        'montant_sats' => $sats,
                        'taux_btc_fcfa' => $taux,
                        'reference_prestataire' => $reference,
                        'statut' => 'paye',
                        'expire_le' => null,
                        'paye_le' => $moment->copy()->addMinute(),
                        'cree_le' => $moment,
                        'modifie_le' => $moment->copy()->addMinute(),
                    ]);
                }
            }
        }

        // ---------- 5. Taux de change ----------
        DB::table('taux_change')->truncate();
        foreach ([240, 180, 120, 60, 0] as $minutesAvant) {
            DB::table('taux_change')->insert([
                'btc_fcfa' => 62000000 + mt_rand(-300000, 300000),
                'recupere_le' => $maintenant->copy()->subMinutes($minutesAvant),
            ]);
        }

        // ---------- 6. Retraits ----------
        foreach ([
                     [30000, 'wave', 12],
                     [20000, 'orange_money', 5],
                     [15000, 'free_money', 2],
                 ] as [$montant, $operateur, $joursAvant]) {
            DB::table('retraits')->insert([
                'boutique_id' => $boutiqueId,
                'montant_fcfa' => $montant,
                'operateur' => $operateur,
                'cree_le' => $maintenant->copy()->subDays($joursAvant),
            ]);
        }

        // ---------- 7. Rapports IA ----------
        $rapports = [
            [
                'debut' => $maintenant->copy()->subDays(7),
                'fin' => $maintenant->copy(),
                'contenu' => [
                    'resume' => 'Bonne nouvelle ! Les ventes de biscuits sont en hausse ce week-end.',
                    'conseils' => [
                        'Pensez à augmenter votre stock de biscuits.',
                        'Vos ventes sont souvent plus élevées le samedi.',
                        'Proposez le jus de bissap en promotion en milieu de semaine.',
                    ],
                ],
            ],
            [
                'debut' => $maintenant->copy()->subDays(14),
                'fin' => $maintenant->copy()->subDays(7),
                'contenu' => [
                    'resume' => 'Votre chiffre d\'affaires est stable cette semaine.',
                    'conseils' => [
                        'Le riz et l\'huile se vendent régulièrement : gardez-en toujours en stock.',
                    ],
                ],
            ],
        ];
        foreach ($rapports as $r) {
            DB::table('rapports_ia')->insert([
                'boutique_id' => $boutiqueId,
                'periode_debut' => $r['debut']->toDateString(),
                'periode_fin' => $r['fin']->toDateString(),
                'contenu' => json_encode($r['contenu'], JSON_UNESCAPED_UNICODE),
                'cree_le' => $r['fin'],
            ]);
        }

        $this->command?->info("Données de démonstration créées pour la boutique « {$boutique->nom} » (compte {$utilisateur->telephone}).");
    }
}
