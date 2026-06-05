<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_order_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('path');
            $table->string('disk')->default('public');
            $table->string('type')->default('general')->comment('before, after, general');
            $table->string('caption')->nullable();
            $table->bigInteger('size')->nullable()->comment('Tamanho em bytes');
            $table->string('mime_type')->nullable();
            $table->timestamps();

            $table->index('service_order_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_photos');
    }
};
