<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('service_order_photos', 'service_order_attachments');

        Schema::table('service_order_attachments', function (Blueprint $table) {
            $table->string('original_name')->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('service_order_attachments', function (Blueprint $table) {
            $table->dropColumn('original_name');
        });

        Schema::rename('service_order_attachments', 'service_order_photos');
    }
};
