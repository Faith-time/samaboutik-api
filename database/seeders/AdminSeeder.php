<?php

namespace Database\Seeders;

use App\Models\Utilisateur;
use Illuminate\Database\Seeder;

// À lancer à la main : php artisan db:seed --class=AdminSeeder
// CHANGE le mot de passe avant toute mise en ligne.
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Utilisateur::firstOrCreate(
            ['telephone' => '770000001'],
            ['nom' => 'Administrateur', 'mot_de_passe' => 'Admin#12345']
        );
        $admin->syncRoles(['admin']);

        $this->command?->info('Admin : 770000001 / Admin#12345');
    }
}
