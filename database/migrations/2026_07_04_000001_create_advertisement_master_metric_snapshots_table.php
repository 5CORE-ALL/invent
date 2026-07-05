<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily rollup of the /advertisement-master channel metrics (all
 * marketplaces + their nested channels) so the table can show a
 * day-over-day trend dot per metric. One row per (snapshot_date, channel),
 * stamped on the Pacific business day — the controller `updateOrInsert`s on
 * each page load so the latest value of the day always wins. Only the four
 * raw measures are persisted; CVR / ACOS are derived at read time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisement_master_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date')->index();
            $table->string('channel', 128);           // 'Amazon' | 'Amazon · KW' | 'Shopify · Facebook · G Video' ...
            $table->decimal('spend', 16, 2)->default(0);
            $table->decimal('clicks', 16, 2)->default(0);
            $table->decimal('sold', 16, 2)->default(0);
            $table->decimal('sales', 16, 2)->default(0);
            $table->timestamps();

            $table->unique(['snapshot_date', 'channel'], 'adm_snap_date_channel_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisement_master_metric_snapshots');
    }
};
