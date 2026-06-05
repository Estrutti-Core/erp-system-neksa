<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_order_status_history', function (Blueprint $table) {
            $table->boolean('sla_alert_sent')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('service_order_status_history', function (Blueprint $table) {
            $table->dropColumn('sla_alert_sent');
        });
    }
};
