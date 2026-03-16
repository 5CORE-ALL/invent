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
        Schema::create('walmart_7ub_daily_counts', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->integer('pink_count')->default(0);
            $table->integer('red_count')->default(0);
            $table->integer('green_count')->default(0);
            $table->timestamps();
            
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('walmart_7ub_daily_counts');
    }
};

