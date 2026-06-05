<?php

namespace Database\Seeders;

use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@neksa.com.br'],
            [
                'name'     => 'Administrador Neksa',
                'password' => Hash::make('password'),
            ]
        );
        $admin->assignRole('admin');

        // Operador
        $operator = User::firstOrCreate(
            ['email' => 'operador@neksa.com.br'],
            [
                'name'     => 'Maria Operadora',
                'password' => Hash::make('password'),
            ]
        );
        $operator->assignRole('operator');

        // Técnicos
        $technicians = [
            ['name' => 'Carlos Silva',    'email' => 'carlos@neksa.com.br',  'region' => 'Zona Sul',   'phone' => '(11) 98765-0001'],
            ['name' => 'João Santos',     'email' => 'joao@neksa.com.br',    'region' => 'Zona Norte', 'phone' => '(11) 98765-0002'],
            ['name' => 'Pedro Almeida',   'email' => 'pedro@neksa.com.br',   'region' => 'Zona Leste', 'phone' => '(11) 98765-0003'],
            ['name' => 'Ana Rodrigues',   'email' => 'ana@neksa.com.br',     'region' => 'Zona Oeste', 'phone' => '(11) 98765-0004'],
        ];

        foreach ($technicians as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'     => $data['name'],
                    'password' => Hash::make('password'),
                ]
            );
            $user->assignRole('technician');

            TechnicianProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'phone'          => $data['phone'],
                    'service_region' => $data['region'],
                    'is_active'      => true,
                ]
            );
        }
    }
}
