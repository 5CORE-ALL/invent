<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per Pacific day: how many SKUs are on Clearance (Inv Days = YES) with INV > 0, and their total INV.
 * Feeds the "Clearance count · last 30 days" graph in every Sprc Dil modal, so the history no longer depends on
 * somebody opening the page that day (it used to live in each browser's localStorage).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clearance_daily_counts')) {
            return;
        }
        Schema::create('clearance_daily_counts', function (Blueprint $table) {
            $table->id();
            $table->date('record_date')->unique();
            $table->unsignedInteger('sku_count')->default(0);
            $table->unsignedBigInteger('inv_units')->default(0);
            // live = taken by the hourly job / page; backfill = rebuilt from the clearance change log.
            $table->string('source', 12)->default('live');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clearance_daily_counts');
    }
};
