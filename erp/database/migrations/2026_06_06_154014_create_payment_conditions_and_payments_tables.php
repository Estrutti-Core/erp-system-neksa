<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_conditions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // 'cash', 'installments', 'custom'
            $table->integer('installments_count')->default(1);
            $table->integer('interval_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->string('payment_method');
            $table->decimal('amount', 15, 2);
            $table->integer('installments_count')->default(1);
            $table->date('first_due_date');
            $table->foreignId('financial_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('service_order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->string('payment_method');
            $table->decimal('amount', 15, 2);
            $table->integer('installments_count')->default(1);
            $table->date('first_due_date');
            $table->foreignId('financial_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_payments');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('payment_conditions');
    }
};
