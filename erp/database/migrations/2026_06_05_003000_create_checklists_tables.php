<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('checklist_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_template_id')->constrained('checklist_templates')->cascadeOnDelete();
            $table->string('question_text');
            $table->string('question_type')->default('text'); // text, yes_no, photo, number
            $table->boolean('is_required')->default(false);
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('checklist_template_id');
        });

        Schema::create('service_type_checklists', function (Blueprint $table) {
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('checklist_template_id')->constrained('checklist_templates')->cascadeOnDelete();
            $table->primary(['service_id', 'checklist_template_id']);
        });

        Schema::create('service_order_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->foreignId('checklist_template_id')->constrained('checklist_templates')->cascadeOnDelete();
            $table->foreignId('filled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('filled_at')->nullable();
            $table->timestamps();

            $table->index('service_order_id');
            $table->index('checklist_template_id');
        });

        Schema::create('checklist_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_checklist_id')->constrained('service_order_checklists')->cascadeOnDelete();
            $table->foreignId('checklist_question_id')->constrained('checklist_questions')->cascadeOnDelete();
            $table->text('answer_value')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();

            $table->index('service_order_checklist_id');
            $table->index('checklist_question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_answers');
        Schema::dropIfExists('service_order_checklists');
        Schema::dropIfExists('service_type_checklists');
        Schema::dropIfExists('checklist_questions');
        Schema::dropIfExists('checklist_templates');
    }
};
