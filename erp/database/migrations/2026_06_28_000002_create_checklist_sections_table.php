<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_template_id')
                ->constrained('checklist_templates')
                ->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('checklist_template_id');
        });

        // Vincular perguntas existentes a seções
        Schema::table('checklist_questions', function (Blueprint $table) {
            $table->foreignId('checklist_section_id')
                ->nullable()
                ->after('checklist_template_id')
                ->constrained('checklist_sections')
                ->nullOnDelete();

            // Rastreabilidade: de qual bloco esta pergunta foi importada
            $table->foreignId('source_block_id')
                ->nullable()
                ->after('checklist_section_id')
                ->constrained('checklist_blocks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('checklist_questions', function (Blueprint $table) {
            $table->dropForeign(['source_block_id']);
            $table->dropForeign(['checklist_section_id']);
            $table->dropColumn(['source_block_id', 'checklist_section_id']);
        });

        Schema::dropIfExists('checklist_sections');
    }
};
