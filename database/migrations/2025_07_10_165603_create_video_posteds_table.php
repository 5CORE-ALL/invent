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
        if (!Schema::hasTable('video_posted_values')) {
            Schema::create('video_posted_values', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->unique();
                $table->json('value');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('assembly_videos')) {
            Schema::create('assembly_videos', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->index();
                $table->json('value')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('three_d_videos')) {
            Schema::create('three_d_videos', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->index();
                $table->json('value')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('video_360s')) {
            Schema::create('video_360s', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->index();
                $table->json('value')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('benefit_videos')) {
            Schema::create('benefit_videos', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->index();
                $table->json('value')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('diy_videos')) {
            Schema::create('diy_videos', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->index();
                $table->json('value')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('shoppable_videos')) {
            Schema::create('shoppable_videos', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->index();
                $table->json('value')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_posted_values');
        Schema::dropIfExists('shoppable_videos');
        Schema::dropIfExists('diy_videos');
        Schema::dropIfExists('benefit_videos');
        Schema::dropIfExists('video_360s');
        Schema::dropIfExists('three_d_videos');
        Schema::dropIfExists('assembly_videos');
    }
};
