<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->string('name')->nullable()->comment('Nome do roteiro');
            $table->date('route_date');
            $table->string('status')->default('pending')
                ->comment('pending, in_progress, completed, cancelled');
            $table->decimal('total_distance_km', 8, 2)->nullable();
            $table->integer('estimated_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->json('optimized_order')->nullable()->comment('Ordem otimizada de IDs de OS');
            $table->timestamps();

            $table->index('technician_id');
            $table->index('route_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};
