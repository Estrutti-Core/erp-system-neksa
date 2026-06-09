<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('carrier')->nullable();
            $table->decimal('freight_price', 15, 2)->default(0.00);
            $table->integer('freight_type')->default(9); // 0-Emitente, 1-Destinatário, 2-Terceiros, 3-Próprio Emitente, 4-Próprio Destinatário, 9-Sem frete
            $table->decimal('volume', 10, 4)->nullable();
            $table->decimal('weight_gross', 10, 4)->nullable();
            $table->decimal('weight_net', 10, 4)->nullable();
            $table->string('delivery_deadline')->nullable();
            $table->string('warranty')->nullable();
            $table->string('validity')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn([
                'carrier',
                'freight_price',
                'freight_type',
                'volume',
                'weight_gross',
                'weight_net',
                'delivery_deadline',
                'warranty',
                'validity'
            ]);
        });
    }
};
