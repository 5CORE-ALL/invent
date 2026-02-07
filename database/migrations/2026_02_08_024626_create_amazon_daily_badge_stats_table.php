<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amazon_daily_badge_stats', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date');
            $table->integer('sold_count')->default(0);
            $table->integer('zero_sold_count')->default(0);
            $table->integer('map_count')->default(0);
            $table->integer('nmap_count')->default(0);
            $table->integer('missing_count')->default(0);
            $table->integer('prc_gt_lmp_count')->default(0);
            $table->integer('campaign_count')->default(0);
            $table->integer('missing_campaign_count')->default(0);
            $table->integer('nra_count')->default(0);
            $table->integer('ra_count')->default(0);
            $table->integer('paused_count')->default(0);
            $table->integer('ub7_count')->default(0);
            $table->integer('ub7_ub1_count')->default(0);
            $table->timestamps();

            $table->unique('snapshot_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_daily_badge_stats');
    }
};
