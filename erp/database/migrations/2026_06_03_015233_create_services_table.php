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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('sku')->unique();
            $table->string('cfop')->nullable();
            $table->string('cst')->nullable();
            $table->decimal('iss_rate', 5, 2)->default(0.00);
            $table->boolean('iss_withheld')->default(false);
            $table->decimal('pis_retention_rate', 5, 2)->default(0.00);
            $table->decimal('cofins_retention_rate', 5, 2)->default(0.00);
            $table->decimal('csll_retention_rate', 5, 2)->default(0.00);
            $table->decimal('inss_retention_rate', 5, 2)->default(0.00);
            $table->string('municipal_service_code')->nullable();
            $table->decimal('price', 15, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
