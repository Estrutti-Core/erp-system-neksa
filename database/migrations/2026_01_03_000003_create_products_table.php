<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sku')->unique()->nullable();
            $table->string('barcode')->unique()->index();
            $table->string('name');
            $table->foreignUuid('category_id')->constrained()->onDelete('restrict');
            $table->foreignUuid('supplier_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('cost_price', 12, 4)->default(0);
            $table->decimal('sale_price', 12, 4)->default(0);
            $table->decimal('stock_balance', 12, 4)->default(0);
            $table->decimal('min_stock', 12, 4)->default(0);
            $table->string('unit', 10)->default('UN');
            $table->boolean('active')->default(true);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
