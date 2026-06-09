<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xml_imports', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('access_key')->unique();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->string('status')->default('pending'); // 'pending', 'confirmed'
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('xml_import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('xml_import_id')->constrained('xml_imports')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('supplier_product_code');
            $table->string('supplier_product_name');
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('total_price', 15, 2);
            $table->string('cfop')->nullable();
            $table->string('ncm')->nullable();
            $table->boolean('resolved')->default(false);
            $table->timestamps();
        });

        Schema::create('product_supplier_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('supplier_code');
            $table->timestamps();

            $table->unique(['supplier_id', 'supplier_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_supplier_codes');
        Schema::dropIfExists('xml_import_items');
        Schema::dropIfExists('xml_imports');
    }
};
