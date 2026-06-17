<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-campaign daily snapshot of merged-view metrics on
 * /facebook-all-ads-sheet. One row per campaign per day, written
 * (upserted) every time the merged view is computed. Powers the
 * "click a badge → trend chart" feature with a true history that
 * matches what the badges show, instead of pulling from Meta API
 * data which uses different attribution windows / coverage.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('facebook_campaign_metric_snapshots')) {
            Schema::create('facebook_campaign_metric_snapshots', function (Blueprint $table) {
                $table->id();
                $table->string('campaign_id', 64)->index();
                $table->date('snapshot_date')->index();
                // Same numeric metrics the badges sum.
                $table->decimal('impr',  16, 2)->default(0);
                $table->decimal('clk',   16, 2)->default(0);
                $table->decimal('spend', 16, 2)->default(0);
                $table->decimal('sales', 16, 2)->default(0);
                $table->decimal('sold',  16, 2)->default(0);
                $table->decimal('sbgt',  10, 2)->default(0);
                $table->timestamps();

                // One row per (campaign, day). Re-saves overwrite via
                // updateOrInsert on this key.
                $table->unique(['campaign_id', 'snapshot_date'], 'campaign_day_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_campaign_metric_snapshots');
    }
};
