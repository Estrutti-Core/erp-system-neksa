<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_questions', function (Blueprint $table) {
            // Adiciona suporte às opções para perguntas do tipo "select"
            $table->json('options_json')->nullable()->after('question_type')
                ->comment('Opções disponíveis para perguntas do tipo select, em JSON');
        });

        // Nota: question_type já é string, aceita os novos valores sem alteração de schema.
        // Valores válidos agora: text, checkbox, select, photo, drawing, label
    }

    public function down(): void
    {
        Schema::table('checklist_questions', function (Blueprint $table) {
            $table->dropColumn('options_json');
        });
    }
};
