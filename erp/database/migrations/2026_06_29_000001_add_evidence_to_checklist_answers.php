<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_answers', function (Blueprint $table) {
            $table->text('observation')->nullable()->after('answer_value');
            $table->json('photos_json')->nullable()->after('observation');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_answers', function (Blueprint $table) {
            $table->dropColumn(['observation', 'photos_json']);
        });
    }
};
