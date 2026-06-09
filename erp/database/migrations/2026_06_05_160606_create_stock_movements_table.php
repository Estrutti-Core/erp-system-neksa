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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->unsignedBigInteger('warehouse_id')->nullable(); // Preparado para depósitos múltiplos (ADR-013)
            $table->decimal('quantity', 12, 3); // Positivo para entradas, negativo para saídas
            $table->decimal('stock_before', 12, 3); // Trilha de auditoria (ADR-011)
            $table->decimal('stock_after', 12, 3); // Trilha de auditoria (ADR-011)
            $table->decimal('unit_cost', 12, 2)->default(0); // Custeio histórico da movimentação (ADR-010)
            $table->string('type'); // input, output
            $table->string('source_type'); // service_order, sale, purchase_order, manual, inventory_conference
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
