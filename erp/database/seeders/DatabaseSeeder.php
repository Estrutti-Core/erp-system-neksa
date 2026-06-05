<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            ClientSeeder::class,
            ServiceOrderSeeder::class,
            CompanySeeder::class,
            MockDataSeeder::class,
        ]);
    }
}
