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
        Schema::table('clients', function (Blueprint $table) {
            $table->string('social_name')->nullable()->after('name');
            $table->string('trade_name')->nullable()->after('social_name');
            $table->string('sector')->nullable()->after('trade_name');
            $table->date('opening_date')->nullable()->after('sector');
            $table->decimal('capital_social', 15, 2)->nullable()->after('opening_date');
            $table->string('company_size')->nullable()->after('capital_social');
            $table->string('legal_nature')->nullable()->after('company_size');
            $table->string('registration_status')->nullable()->after('legal_nature');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'social_name',
                'trade_name',
                'sector',
                'opening_date',
                'capital_social',
                'company_size',
                'legal_nature',
                'registration_status',
            ]);
        });
    }
};
