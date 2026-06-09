<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_conditions', function (Blueprint $table) {
            $table->string('default_payment_method')->nullable();
            $table->foreignId('default_financial_account_id')
                ->nullable()
                ->constrained('financial_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_conditions', function (Blueprint $table) {
            $table->dropForeign(['default_financial_account_id']);
            $table->dropColumn(['default_payment_method', 'default_financial_account_id']);
        });
    }
};
