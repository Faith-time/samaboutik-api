<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class, // toujours en premier
            DemoSeeder::class,
            ProduitImageSeeder::class,
        ]);
    }
}
