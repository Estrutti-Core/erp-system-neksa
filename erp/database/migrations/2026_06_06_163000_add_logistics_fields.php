<?php
// erp/database/migrations/2026_06_06_163000_add_logistics_fields.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('weight_gross', 10, 4)->nullable();
            $table->decimal('weight_net', 10, 4)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('length', 10, 2)->nullable();
            $table->decimal('volume', 10, 4)->nullable();
            $table->string('logistic_unit', 50)->nullable();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('carrier')->nullable();
            $table->decimal('freight_price', 15, 2)->default(0.00);
            $table->decimal('volume', 10, 4)->nullable();
            $table->decimal('weight_gross', 10, 4)->nullable();
            $table->decimal('weight_net', 10, 4)->nullable();
            $table->integer('freight_type')->default(9); // 0-Emitente, 1-Destinatário, 2-Terceiros, 3-Próprio Emitente, 4-Próprio Destinatário, 9-Sem frete
            $table->string('delivery_deadline')->nullable();
            $table->string('warranty')->nullable();
            $table->string('validity')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'carrier',
                'freight_price',
                'volume',
                'weight_gross',
                'weight_net',
                'freight_type',
                'delivery_deadline',
                'warranty',
                'validity'
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'weight_gross',
                'weight_net',
                'height',
                'width',
                'length',
                'volume',
                'logistic_unit'
            ]);
        });
    }
};
