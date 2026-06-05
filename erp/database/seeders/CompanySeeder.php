<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (\App\Models\Company::count() === 0) {
            \App\Models\Company::create([
                'name' => 'Neksa ERP',
                'email' => 'contato@neksa.com.br',
            ]);
        }
    }
}
