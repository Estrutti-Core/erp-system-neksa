<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add sale_number column with SERIAL (auto-increment)
        Schema::table('sales', function (Blueprint $table) {
            $table->integer('sale_number')->nullable()->after('id');
        });

        // Create sequence for sale_number
        DB::statement('CREATE SEQUENCE IF NOT EXISTS sales_number_seq START 1');
        
        // Set default value to use sequence
        DB::statement("ALTER TABLE sales ALTER COLUMN sale_number SET DEFAULT nextval('sales_number_seq')");
        
        // Generate numbers for existing sales
        $sales = DB::table('sales')->orderBy('created_at')->get();
        foreach ($sales as $index => $sale) {
            DB::table('sales')
                ->where('id', $sale->id)
                ->update(['sale_number' => $index + 1]);
        }
        
        // Update sequence to continue from last number
        $lastNumber = DB::table('sales')->max('sale_number') ?? 0;
        DB::statement("SELECT setval('sales_number_seq', $lastNumber)");
        
        // Make sale_number NOT NULL and UNIQUE
        Schema::table('sales', function (Blueprint $table) {
            $table->integer('sale_number')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('sale_number');
        });
        
        DB::statement('DROP SEQUENCE IF EXISTS sales_number_seq');
    }
};
