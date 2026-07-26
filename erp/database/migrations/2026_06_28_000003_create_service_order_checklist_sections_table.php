<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot imutável de seções instanciadas por OS.
     *
     * ADR-004 (extendido): Além das perguntas, as seções também são
     * copiadas no momento da criação do checklist na OS, garantindo
     * que reorganizações futuras no template não afetem OS já abertas.
     */
    public function up(): void
    {
        Schema::create('service_order_checklist_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_checklist_id')
                ->constrained('service_order_checklists')
                ->cascadeOnDelete();

            // Referência ao original apenas para rastreabilidade
            $table->foreignId('checklist_section_id')
                ->nullable()
                ->constrained('checklist_sections')
                ->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index('service_order_checklist_id');
        });

        // Vincular pergunta snapshot à seção snapshot
        Schema::table('service_order_checklist_questions', function (Blueprint $table) {
            $table->foreignId('service_order_checklist_section_id')
                ->nullable()
                ->after('service_order_checklist_id')
                ->constrained('service_order_checklist_sections')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_order_checklist_questions', function (Blueprint $table) {
            $table->dropForeign(['service_order_checklist_section_id']);
            $table->dropColumn('service_order_checklist_section_id');
        });

        Schema::dropIfExists('service_order_checklist_sections');
    }
};
