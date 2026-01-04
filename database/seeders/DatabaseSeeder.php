<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user for testing
        $admin = User::create([
            'name' => 'Admin Mercado',
            'email' => 'admin@admin.com',
            'password' => Hash::make('password'),
        ]);

        // Categories
        $cat1 = Category::create(['name' => 'Bebidas', 'description' => 'Refrigerantes e sucos']);
        $cat2 = Category::create(['name' => 'Mercearia', 'description' => 'Produtos secos']);

        // Suppliers
        $sup1 = Supplier::create(['name' => 'Distribuidora São João', 'active' => true]);

        // Products
        Product::create([
            'barcode' => '7891000100103',
            'name' => 'Coca-Cola 2L',
            'category_id' => $cat1->id,
            'supplier_id' => $sup1->id,
            'cost_price' => 5.50,
            'sale_price' => 9.90,
            'stock_balance' => 100,
            'unit' => 'UN',
        ]);

        Product::create([
            'barcode' => '7891000100104',
            'name' => 'Arroz 5kg',
            'category_id' => $cat2->id,
            'supplier_id' => $sup1->id,
            'cost_price' => 18.00,
            'sale_price' => 24.50,
            'stock_balance' => 50,
            'unit' => 'UN',
        ]);

        // Payment Methods
        PaymentMethod::create(['name' => 'Dinheiro', 'slug' => 'cash', 'type' => 'cash']);
        PaymentMethod::create(['name' => 'Cartão de Débito', 'slug' => 'debit_card', 'type' => 'debit_card']);
        PaymentMethod::create(['name' => 'Cartão de Crédito', 'slug' => 'credit_card', 'type' => 'credit_card']);
        PaymentMethod::create(['name' => 'PIX', 'slug' => 'pix', 'type' => 'pix']);
    }
}
