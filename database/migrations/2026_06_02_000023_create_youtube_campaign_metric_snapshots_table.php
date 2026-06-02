<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-campaign daily snapshot of merged-view metrics on /youtube-video-ads.
 * One row per campaign per day, upserted every time the merged view is
 * computed. Powers the "click a badge → trend chart" feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('youtube_campaign_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_id', 64)->index();
            $table->date('snapshot_date')->index();
            $table->decimal('impr',  16, 2)->default(0);
            $table->decimal('clk',   16, 2)->default(0);
            $table->decimal('spend', 16, 2)->default(0);
            $table->decimal('sales', 16, 2)->default(0);
            $table->decimal('sold',  16, 2)->default(0);
            $table->decimal('sbgt',  10, 2)->default(0);
            $table->timestamps();

            $table->unique(['campaign_id', 'snapshot_date'], 'youtube_campaign_day_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('youtube_campaign_metric_snapshots');
    }
};
