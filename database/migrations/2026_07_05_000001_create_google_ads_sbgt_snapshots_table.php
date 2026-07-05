<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-campaign daily snapshot of the computed SBGT (suggested budget) shown on the
 * Google Shopping / SERP / YouTube grids. One row per campaign per day, upserted when
 * the grid loads. Powers the day-over-day trend dot in the SBGT column: today's SBGT
 * is compared to the previous snapshot (green = up, red = down, gray = unchanged / no
 * prior day), and the per-campaign history popup.
 *
 * The Google grids don't otherwise persist SBGT (unlike the Meta sheet, which stores it
 * in facebook_campaign_metric_snapshots), so this table is what makes the trend possible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('google_ads_sbgt_snapshots')) {
            Schema::create('google_ads_sbgt_snapshots', function (Blueprint $table) {
                $table->id();
                $table->string('campaign_id', 32)->index();
                // Which grid the campaign belongs to: shopping | serp | youtube.
                $table->string('channel', 16)->default('shopping')->index();
                $table->date('snapshot_date')->index();
                // Computed SBGT for that day and the ACOS it was derived from (context).
                $table->decimal('sbgt', 10, 2)->default(0);
                $table->decimal('acos', 8, 2)->nullable();
                $table->timestamps();

                // One row per (campaign, day). Re-saves overwrite via updateOrInsert.
                $table->unique(['campaign_id', 'snapshot_date'], 'gads_sbgt_campaign_day_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('google_ads_sbgt_snapshots');
    }
};
