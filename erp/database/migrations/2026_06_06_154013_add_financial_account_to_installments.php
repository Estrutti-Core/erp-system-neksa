<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receivable_installments', function (Blueprint $table) {
            $table->foreignId('financial_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('net_amount', 15, 2)->default(0.00)->after('paid_amount');
            $table->text('notes')->nullable()->after('status');
        });

        Schema::table('payable_installments', function (Blueprint $table) {
            $table->foreignId('financial_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('net_amount', 15, 2)->default(0.00)->after('paid_amount');
            $table->text('notes')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('receivable_installments', function (Blueprint $table) {
            $table->dropForeign(['financial_account_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn(['financial_account_id', 'user_id', 'net_amount', 'notes']);
        });

        Schema::table('payable_installments', function (Blueprint $table) {
            $table->dropForeign(['financial_account_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn(['financial_account_id', 'user_id', 'net_amount', 'notes']);
        });
    }
};
