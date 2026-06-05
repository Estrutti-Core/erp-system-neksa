<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained('service_orders')->onDelete('cascade');
            $table->foreignId('from_status_id')->nullable()->constrained('service_order_statuses')->onDelete('set null');
            $table->foreignId('to_status_id')->constrained('service_order_statuses')->onDelete('cascade');
            $table->foreignId('changed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('entered_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['service_order_id', 'entered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_status_history');
    }
};
