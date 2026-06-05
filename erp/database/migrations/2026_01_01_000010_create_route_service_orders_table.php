<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_service_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('routes')->cascadeOnDelete();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->integer('sequence')->default(0)->comment('Ordem de visita');
            $table->decimal('distance_from_prev_km', 8, 2)->nullable();
            $table->integer('estimated_minutes_from_prev')->nullable();
            $table->timestamp('estimated_arrival_at')->nullable();
            $table->timestamps();

            $table->unique(['route_id', 'service_order_id']);
            $table->index('route_id');
            $table->index('service_order_id');
            $table->index('sequence');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_service_orders');
    }
};
