<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (day, Dil vs SBid slab/range) holding how many SKUs sat in it that day.
 * eBay 3 only. Written by POST /ebay3/sbid-slab-count-snapshot from the Dil vs SBid modal.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ebay3_sbid_slab_count_snapshots')) {
            return;
        }
        Schema::create('ebay3_sbid_slab_count_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date');
            $table->string('band', 64);
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['snapshot_date', 'band'], 'ebay3_sbid_slab_count_day_band_unique');
            $table->index('band');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay3_sbid_slab_count_snapshots');
    }
};
