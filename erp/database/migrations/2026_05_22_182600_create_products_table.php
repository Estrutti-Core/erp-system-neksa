<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            
            // Identificação
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->index();
            
            // Fiscal
            $table->string('ncm', 10)->nullable();
            $table->string('cfop', 5)->nullable();
            $table->string('cst', 5)->nullable();
            $table->string('csosn', 5)->nullable();
            $table->unsignedTinyInteger('fiscal_origin')->default(0);
            $table->string('commercial_unit', 10)->default('UN');
            $table->string('taxable_unit', 10)->default('UN');
            
            // Comercial
            $table->decimal('cost_price', 15, 2)->default(0.00);
            $table->decimal('sale_price', 15, 2)->default(0.00);
            $table->decimal('stock', 15, 3)->nullable();
            $table->string('category')->nullable();
            $table->boolean('is_active')->default(true);
            
            // Operacional
            $table->text('internal_notes')->nullable();
            $table->string('type')->default('product'); // 'product' ou 'service'
            $table->boolean('is_stock_controlled')->default(false);
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
