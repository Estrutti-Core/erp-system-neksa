<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('offline_id')->unique()->nullable(); // For POS sync
            $table->foreignUuid('cashier_session_id')->nullable()->constrained()->onDelete('restrict');
            $table->foreignUuid('customer_id')->nullable()->constrained()->onDelete('restrict');
            $table->foreignUuid('user_id')->constrained()->onDelete('restrict');
            $table->foreignUuid('payment_method_id')->constrained()->onDelete('restrict');
            $table->decimal('subtotal', 12, 4);
            $table->decimal('discount', 12, 4)->default(0);
            $table->decimal('total', 12, 4);
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('completed');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
