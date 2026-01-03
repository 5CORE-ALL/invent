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
        // Step 1: Delete duplicate rows (keep the one with lowest id)
        DB::statement("
            DELETE t1 FROM walmart_daily_data t1
            INNER JOIN walmart_daily_data t2 
            WHERE t1.id > t2.id 
            AND t1.purchase_order_id = t2.purchase_order_id 
            AND t1.order_line_number = t2.order_line_number
        ");

        // Step 2: Add unique index
        Schema::table('walmart_daily_data', function (Blueprint $table) {
            $table->unique(['purchase_order_id', 'order_line_number'], 'walmart_daily_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('walmart_daily_data', function (Blueprint $table) {
            $table->dropUnique('walmart_daily_unique');
        });
    }
};

