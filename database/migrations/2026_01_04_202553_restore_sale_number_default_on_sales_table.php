<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Illuminate\Support\Facades\DB::statement("ALTER TABLE sales ALTER COLUMN sale_number SET DEFAULT nextval('sales_number_seq')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Illuminate\Support\Facades\DB::statement("ALTER TABLE sales ALTER COLUMN sale_number DROP DEFAULT");
    }
};
