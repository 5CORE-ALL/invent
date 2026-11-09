<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ebay1 Data: one row per day with how many item specific cells had no data, over every category.
     * The page saves today's figure whenever it changes; the history dot on the badge lists the days.
     */
    public function up(): void
    {
        if (Schema::hasTable('ebay1_missing_history')) {
            return;
        }

        Schema::create('ebay1_missing_history', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date')->unique();
            $table->unsignedInteger('total_missing')->default(0);
            $table->unsignedInteger('total_cells')->default(0);
            $table->unsignedInteger('categories_count')->default(0);
            $table->json('categories')->nullable(); // [{name, missing, cells}]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay1_missing_history');
    }
};
