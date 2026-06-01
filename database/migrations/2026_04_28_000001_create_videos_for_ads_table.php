<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos_for_ads', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->text('ads_topic_story')->nullable();
            $table->text('ads_what')->nullable();
            $table->text('ads_why_purpose')->nullable();
            $table->text('ads_audience')->nullable();
            $table->text('ads_benefit_audience')->nullable();
            $table->text('ads_location')->nullable();
            $table->text('ads_language')->nullable();
            $table->text('ads_script_link')->nullable();
            $table->string('ads_script_link_status')->nullable();
            $table->text('ads_video_en_link')->nullable();
            $table->string('ads_video_en_link_status')->nullable();
            $table->text('ads_video_es_link')->nullable();
            $table->string('ads_video_es_link_status')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos_for_ads');
    }
};
