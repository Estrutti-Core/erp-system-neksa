<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->string('type')->default('service')->comment('service, part, material');
            $table->string('description');
            $table->decimal('quantity', 10, 3)->default(1);
            $table->string('unit', 20)->default('un');
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->string('product_code')->nullable()->comment('Código do produto/serviço');
            $table->timestamps();

            $table->index('service_order_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_items');
    }
};
