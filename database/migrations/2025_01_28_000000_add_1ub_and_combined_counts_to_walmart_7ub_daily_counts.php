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
        Schema::table('walmart_7ub_daily_counts', function (Blueprint $table) {
            // Add 1UB counts
            $table->integer('ub1_pink_count')->default(0)->after('green_count');
            $table->integer('ub1_red_count')->default(0)->after('ub1_pink_count');
            $table->integer('ub1_green_count')->default(0)->after('ub1_red_count');
            
            // Add combined 7UB+1UB counts (items that match in both)
            $table->integer('combined_pink_count')->default(0)->after('ub1_green_count');
            $table->integer('combined_red_count')->default(0)->after('combined_pink_count');
            $table->integer('combined_green_count')->default(0)->after('combined_red_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('walmart_7ub_daily_counts', function (Blueprint $table) {
            $table->dropColumn([
                'ub1_pink_count',
                'ub1_red_count',
                'ub1_green_count',
                'combined_pink_count',
                'combined_red_count',
                'combined_green_count'
            ]);
        });
    }
};
