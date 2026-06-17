<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily rollup of the /shopify-ads-master channel metrics so the badge
 * trend chart has a real history to draw. One row per (snapshot_date,
 * channel) — the controller `updateOrInsert`s on each page load so the
 * latest value of the day always wins. CVR / ACOS are derived from the
 * stored spend / clicks / sold / sales at read time, so only the four
 * raw measures are persisted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_ads_master_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date')->index();
            $table->string('channel', 64);            // 'Google Shopping' | 'Facebook' | 'Instagram'
            $table->decimal('spend', 16, 2)->default(0);
            $table->decimal('clicks', 16, 2)->default(0);
            $table->decimal('sold', 16, 2)->default(0);
            $table->decimal('sales', 16, 2)->default(0);
            $table->timestamps();

            $table->unique(['snapshot_date', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_ads_master_metric_snapshots');
    }
};
