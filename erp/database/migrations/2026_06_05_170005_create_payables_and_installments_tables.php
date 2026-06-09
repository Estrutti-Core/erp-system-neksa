<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->date('competence_date');
            $table->string('description');
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->decimal('discount_amount', 15, 2)->default(0.00);
            $table->decimal('interest_amount', 15, 2)->default(0.00);
            $table->decimal('net_amount', 15, 2)->default(0.00);
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Índices recomendados
            $table->index('company_id');
            $table->index('status');
            $table->index('competence_date');
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('payable_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payable_id')->constrained('payables')->cascadeOnDelete();
            $table->integer('installment_number');
            $table->date('due_date');
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->decimal('discount_amount', 15, 2)->default(0.00);
            $table->decimal('interest_amount', 15, 2)->default(0.00);
            $table->decimal('paid_amount', 15, 2)->default(0.00);
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            // Índices recomendados
            $table->index('due_date');
            $table->index('paid_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payable_installments');
        Schema::dropIfExists('payables');
    }
};
