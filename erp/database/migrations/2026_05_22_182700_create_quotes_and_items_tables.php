<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('client_address_id')->nullable()->constrained('client_addresses')->nullOnDelete();
            $table->string('status')->default('draft'); // draft, sent, approved, rejected, converted
            $table->string('type')->nullable(); // sale, service_order
            $table->date('valid_until')->nullable();
            
            $table->text('notes')->nullable(); // observações públicas/comerciais
            $table->text('internal_notes')->nullable(); // notas operacionais
            
            $table->decimal('discount_amount', 15, 2)->default(0.00);
            $table->decimal('items_amount', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
            
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 15, 3)->default(1.000);
            $table->string('unit', 10)->default('UN');
            $table->decimal('unit_price', 15, 2)->default(0.00);
            $table->decimal('total_price', 15, 2)->default(0.00);
            $table->string('type')->default('product'); // 'product' ou 'service'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
    }
};
