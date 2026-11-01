<?php

namespace Database\Seeders;

use App\Models\Utilisateur;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['commercant', 'client', 'admin'] as $nom) {
            Role::firstOrCreate(['name' => $nom, 'guard_name' => 'web']);
        }

        // Les comptes créés avant les rôles deviennent des commerçants
        Utilisateur::doesntHave('roles')->each(fn (Utilisateur $u) => $u->assignRole('commercant'));

        $this->command?->info('Rôles prêts : commercant, client, admin.');
    }
}
