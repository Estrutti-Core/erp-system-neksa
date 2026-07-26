<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('checklist_block_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_block_id')
                ->constrained('checklist_blocks')
                ->cascadeOnDelete();
            $table->string('question_text');
            $table->string('question_type')->default('text')
                ->comment('text, checkbox, select, photo, drawing, label, signature');
            $table->json('options_json')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('checklist_block_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_block_questions');
        Schema::dropIfExists('checklist_blocks');
    }
};
