<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily channel-level count of campaigns in Green utilisation (L7) — U7% 66–99%.
 * Powers the GREEN UTIL (L7) toolbar badge history chart on Google Shopping / SERP / YT.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('google_ads_green_util_daily_counts')) {
            return;
        }

        Schema::create('google_ads_green_util_daily_counts', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 32);
            $table->date('snapshot_date');
            $table->unsignedInteger('green_count')->default(0);
            $table->timestamps();

            $table->unique(['channel', 'snapshot_date'], 'gads_green_util_channel_date_uq');
            $table->index(['channel', 'snapshot_date'], 'gads_green_util_channel_date_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_ads_green_util_daily_counts');
    }
};
