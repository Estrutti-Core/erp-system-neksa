<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_order_checklists', function (Blueprint $table) {
            $table->boolean('is_inactive')->default(false)->after('filled_at')
                ->comment('Marcado como inativo quando o serviço é removido mas o checklist já estava preenchido (preservação de evidência)');
        });
    }

    public function down(): void
    {
        Schema::table('service_order_checklists', function (Blueprint $table) {
            $table->dropColumn('is_inactive');
        });
    }
};
