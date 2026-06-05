<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_order_status_transitions', function (Blueprint $table) {
            $table->foreignId('from_status_id')->constrained('service_order_statuses')->onDelete('cascade');
            $table->foreignId('to_status_id')->constrained('service_order_statuses')->onDelete('cascade');
            $table->primary(['from_status_id', 'to_status_id']);
        });

        // Retrieve status IDs dynamically
        $statuses = DB::table('service_order_statuses')->pluck('id', 'slug');

        if ($statuses->isNotEmpty()) {
            DB::table('service_order_status_transitions')->insert([
                // open -> in_route, in_service, awaiting_parts, cancelled
                ['from_status_id' => $statuses['open'], 'to_status_id' => $statuses['in_route']],
                ['from_status_id' => $statuses['open'], 'to_status_id' => $statuses['in_service']],
                ['from_status_id' => $statuses['open'], 'to_status_id' => $statuses['awaiting_parts']],
                ['from_status_id' => $statuses['open'], 'to_status_id' => $statuses['cancelled']],

                // in_route -> in_service, open, cancelled
                ['from_status_id' => $statuses['in_route'], 'to_status_id' => $statuses['in_service']],
                ['from_status_id' => $statuses['in_route'], 'to_status_id' => $statuses['open']],
                ['from_status_id' => $statuses['in_route'], 'to_status_id' => $statuses['cancelled']],

                // in_service -> awaiting_parts, completed, cancelled
                ['from_status_id' => $statuses['in_service'], 'to_status_id' => $statuses['awaiting_parts']],
                ['from_status_id' => $statuses['in_service'], 'to_status_id' => $statuses['completed']],
                ['from_status_id' => $statuses['in_service'], 'to_status_id' => $statuses['cancelled']],

                // awaiting_parts -> in_service, cancelled
                ['from_status_id' => $statuses['awaiting_parts'], 'to_status_id' => $statuses['in_service']],
                ['from_status_id' => $statuses['awaiting_parts'], 'to_status_id' => $statuses['cancelled']],

                // cancelled -> open
                ['from_status_id' => $statuses['cancelled'], 'to_status_id' => $statuses['open']],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_status_transitions');
    }
};
