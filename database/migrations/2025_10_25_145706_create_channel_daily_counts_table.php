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
        Schema::create('channel_daily_counts', function (Blueprint $table) {
            $table->id();
            $table->string('channel_name')->unique();
            $table->json('counts'); // JSON object with dates as keys and counts as values
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_daily_counts');
    }
};
