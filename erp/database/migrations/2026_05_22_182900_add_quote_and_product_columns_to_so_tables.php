<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->foreignId('quote_id')->nullable()->after('client_address_id')->constrained('quotes')->nullOnDelete();
        });

        Schema::table('service_order_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('service_order_id')->constrained('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });

        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropForeign(['quote_id']);
            $table->dropColumn('quote_id');
        });
    }
};
