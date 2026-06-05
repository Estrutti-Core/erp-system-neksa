<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add status_id as nullable first
        Schema::table('service_orders', function (Blueprint $table) {
            $table->foreignId('status_id')->nullable()->after('created_by')->constrained('service_order_statuses');
        });

        // 2. Fetch the statuses slug to id map
        $statuses = DB::table('service_order_statuses')->pluck('id', 'slug');

        if ($statuses->isNotEmpty()) {
            // 3. Map and backfill existing service orders
            $serviceOrders = DB::table('service_orders')->get();
            foreach ($serviceOrders as $so) {
                $oldStatus = $so->status ?? 'open';
                $statusId = $statuses[$oldStatus] ?? $statuses['open'];

                DB::table('service_orders')
                    ->where('id', $so->id)
                    ->update(['status_id' => $statusId]);

                // Backfill initial status history entry
                DB::table('service_order_status_history')->insert([
                    'service_order_id' => $so->id,
                    'from_status_id' => null,
                    'to_status_id' => $statusId,
                    'changed_by' => $so->created_by,
                    'entered_at' => $so->created_at ?? now(),
                    'left_at' => null,
                    'duration_minutes' => null,
                    'notes' => 'Histórico inicial migrado automaticamente.',
                    'created_at' => $so->created_at ?? now(),
                    'updated_at' => $so->created_at ?? now(),
                ]);
            }
        }

        // 4. Change status_id to be non-nullable and drop the old status string column
        Schema::table('service_orders', function (Blueprint $table) {
            $table->foreignId('status_id')->nullable(false)->change();
            $table->dropColumn('status');
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->string('status')->default('open')->after('created_by');
        });

        // Recover status string from status_id
        $statuses = DB::table('service_order_statuses')->pluck('slug', 'id');
        $serviceOrders = DB::table('service_orders')->get();
        foreach ($serviceOrders as $so) {
            $slug = $statuses[$so->status_id] ?? 'open';
            DB::table('service_orders')
                ->where('id', $so->id)
                ->update(['status' => $slug]);
        }

        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropColumn('status_id');
        });
    }
};
