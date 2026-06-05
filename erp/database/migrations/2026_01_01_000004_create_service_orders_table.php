<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('Código único da OS: OS-2024-00001');
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('client_address_id')->nullable()->constrained('client_addresses')->nullOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('status')->default('open')
                ->comment('open, in_route, in_service, completed, cancelled');
            $table->string('priority')->default('normal')->comment('low, normal, high, urgent');
            $table->text('description')->comment('Descrição do problema/serviço');
            $table->text('services_performed')->nullable()->comment('Serviços executados');
            $table->text('internal_notes')->nullable();
            $table->decimal('total_amount', 10, 2)->default(0)->comment('Valor total da OS');
            $table->decimal('service_amount', 10, 2)->default(0)->comment('Valor mão de obra');
            $table->decimal('parts_amount', 10, 2)->default(0)->comment('Valor peças');
            $table->timestamp('scheduled_at')->nullable()->comment('Data/hora agendada');
            $table->timestamp('started_at')->nullable()->comment('Início do atendimento');
            $table->timestamp('completed_at')->nullable()->comment('Conclusão do atendimento');
            $table->decimal('checkin_latitude', 10, 7)->nullable();
            $table->decimal('checkin_longitude', 10, 7)->nullable();
            $table->timestamp('checkin_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('code');
            $table->index('status');
            $table->index('priority');
            $table->index('client_id');
            $table->index('technician_id');
            $table->index('scheduled_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
