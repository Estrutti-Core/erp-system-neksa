<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ADR-006: Modelagem explícita de check-in para rastreabilidade operacional.
     * Permite múltiplos eventos (check-in / check-out) por OS e por técnico,
     * viabilizando futuro cálculo de tempo em campo e relatórios operacionais.
     */
    public function up(): void
    {
        Schema::create('service_order_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')
                ->constrained('service_orders')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('type')->default('checkin')
                ->comment('checkin ou checkout');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('checked_at');

            $table->timestamps();

            $table->index('service_order_id');
            $table->index(['service_order_id', 'user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_checkins');
    }
};
