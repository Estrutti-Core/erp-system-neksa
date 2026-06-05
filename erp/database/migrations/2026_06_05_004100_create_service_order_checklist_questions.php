<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot imutável de perguntas instanciadas por OS.
     * 
     * Estratégia: ao criar um ServiceOrderChecklist, copiamos todas as perguntas
     * do template para esta tabela. A OS nunca mais lê diretamente do template,
     * garantindo imutabilidade histórica mesmo que o template seja editado.
     * 
     * ADR-004: Estratégia de Snapshot de Checklists
     */
    public function up(): void
    {
        Schema::create('service_order_checklist_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_checklist_id')
                ->constrained('service_order_checklists')
                ->cascadeOnDelete();

            // Referência opcional ao original (para rastreabilidade, não para leitura)
            $table->foreignId('checklist_question_id')
                ->nullable()
                ->constrained('checklist_questions')
                ->nullOnDelete();

            // Cópia snapshot dos campos da pergunta
            $table->string('question_text');
            $table->string('question_type')->default('text')
                ->comment('text, checkbox, select, photo, drawing, label');
            $table->json('options_json')->nullable()
                ->comment('Opções do select, snapshot do momento da instanciação');
            $table->boolean('is_required')->default(false);
            $table->integer('order')->default(0);

            $table->timestamps();

            $table->index('service_order_checklist_id');
        });

        // Adicionar FK na tabela de respostas apontando para a questão instanciada
        Schema::table('checklist_answers', function (Blueprint $table) {
            $table->foreignId('service_order_checklist_question_id')
                ->nullable()
                ->after('checklist_question_id')
                ->constrained('service_order_checklist_questions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('checklist_answers', function (Blueprint $table) {
            $table->dropForeign(['service_order_checklist_question_id']);
            $table->dropColumn('service_order_checklist_question_id');
        });

        Schema::dropIfExists('service_order_checklist_questions');
    }
};
