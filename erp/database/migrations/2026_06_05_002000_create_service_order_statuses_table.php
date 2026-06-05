<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_order_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('color')->default('blue');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_open_state')->default(false);
            $table->boolean('is_completed_state')->default(false);
            $table->boolean('is_cancelled_state')->default(false);
            $table->integer('expected_time_minutes')->nullable();
            $table->integer('max_stay_minutes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Seed initial standard statuses
        $now = now();
        DB::table('service_order_statuses')->insert([
            [
                'slug' => 'open',
                'name' => 'Aberta',
                'color' => 'blue',
                'is_system' => true,
                'is_open_state' => true,
                'is_completed_state' => false,
                'is_cancelled_state' => false,
                'expected_time_minutes' => null,
                'max_stay_minutes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'in_route',
                'name' => 'Em Rota',
                'color' => 'amber',
                'is_system' => true,
                'is_open_state' => true,
                'is_completed_state' => false,
                'is_cancelled_state' => false,
                'expected_time_minutes' => null,
                'max_stay_minutes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'in_service',
                'name' => 'Em Atendimento',
                'color' => 'violet',
                'is_system' => true,
                'is_open_state' => true,
                'is_completed_state' => false,
                'is_cancelled_state' => false,
                'expected_time_minutes' => null,
                'max_stay_minutes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'awaiting_parts',
                'name' => 'Aguardando Peças',
                'color' => 'zinc',
                'is_system' => true,
                'is_open_state' => true,
                'is_completed_state' => false,
                'is_cancelled_state' => false,
                'expected_time_minutes' => null,
                'max_stay_minutes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'completed',
                'name' => 'Concluída',
                'color' => 'green',
                'is_system' => true,
                'is_open_state' => false,
                'is_completed_state' => true,
                'is_cancelled_state' => false,
                'expected_time_minutes' => null,
                'max_stay_minutes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'cancelled',
                'name' => 'Cancelada',
                'color' => 'red',
                'is_system' => true,
                'is_open_state' => false,
                'is_completed_state' => false,
                'is_cancelled_state' => true,
                'expected_time_minutes' => null,
                'max_stay_minutes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_statuses');
    }
};
