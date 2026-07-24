<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-SKU daily snapshots for TikTok 1 / TikTok 2 price charts
     * (same role as ebay_sku_daily_data for /ebay-tabulator-view).
     */
    public function up(): void
    {
        if (Schema::hasTable('tiktok_sku_daily_data')) {
            return;
        }

        Schema::create('tiktok_sku_daily_data', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 191)->index();
            $table->string('channel', 32)->default('tiktok')->index(); // tiktok | tiktok2
            $table->date('record_date')->index();
            $table->json('daily_data')->nullable();
            $table->timestamps();

            $table->unique(['sku', 'channel', 'record_date'], 'tiktok_sku_daily_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_sku_daily_data');
    }
};
