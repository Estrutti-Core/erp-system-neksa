<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_order_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->string('signer_name')->comment('Nome de quem assinou');
            $table->string('signer_document')->nullable()->comment('CPF/RG de quem assinou');
            $table->string('path')->comment('Caminho da imagem da assinatura');
            $table->string('disk')->default('public');
            $table->decimal('signed_latitude', 10, 7)->nullable();
            $table->decimal('signed_longitude', 10, 7)->nullable();
            $table->timestamp('signed_at');
            $table->timestamps();

            $table->index('service_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_signatures');
    }
};
