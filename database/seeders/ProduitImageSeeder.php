<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// php artisan db:seed --class=ProduitImageSeeder
//
// Lit les images dans database/seeders/images/produits/ ; chaque fichier porte le nom du produit
// « slugifié » (ex. "Biscuits crème" -> biscuits-creme.jpg). Les copie dans storage/app/public/produits
// et renseigne produits.image_path. Les produits qui ont déjà une image ne sont pas touchés
// (on peut donc relancer le seeder sans risque).
class ProduitImageSeeder extends Seeder
{
    public function run(): void
    {
        $dossier = database_path('seeders/images/produits');
        if (! is_dir($dossier)) {
            $this->command?->warn("Dossier introuvable : $dossier");

            return;
        }

        $fichiers = [];
        foreach (glob($dossier.'/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE) ?: [] as $f) {
            $fichiers[Str::slug(pathinfo($f, PATHINFO_FILENAME))] = $f;
        }

        $ajoutees = 0;
        $manquantes = [];

        foreach (DB::table('produits')->get(['id', 'nom', 'image_path']) as $p) {
            if ($p->image_path) {
                continue;
            }
            $slug = Str::slug($p->nom);
            if (! isset($fichiers[$slug])) {
                $manquantes[$slug] = $p->nom;

                continue;
            }

            $source = $fichiers[$slug];
            $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
            // Un fichier par produit (id dans le nom) : supprimer l'image d'un produit n'affecte pas les autres
            $chemin = "produits/seed-{$p->id}-{$slug}.{$ext}";

            Storage::disk('public')->put($chemin, file_get_contents($source));
            DB::table('produits')->where('id', $p->id)->update(['image_path' => $chemin]);
            $ajoutees++;
        }

        $this->command?->info("$ajoutees image(s) associée(s).");
        if ($manquantes) {
            $this->command?->warn('Pas d\'image trouvée pour : '.implode(', ', array_map(
                fn ($slug, $nom) => "$nom (attendu : $slug.jpg)", array_keys($manquantes), $manquantes
            )));
        }
    }
}
