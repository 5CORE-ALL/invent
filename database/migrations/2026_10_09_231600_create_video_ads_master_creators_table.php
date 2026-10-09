<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_ads_master_creators', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('video_ads_master_id');
            $table->unsignedBigInteger('user_id');
            // Stored as California wall-clock time (not converted by MySQL).
            $table->dateTime('created_at')->nullable();

            $table->unique(['video_ads_master_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_ads_master_creators');
    }
};
