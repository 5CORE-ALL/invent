<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-SKU daily snapshots for eBay 3 Price / Dil history
     * (same role as ebay_sku_daily_data for /ebay-tabulator-view).
     */
    public function up(): void
    {
        if (Schema::hasTable('ebay3_sku_daily_data')) {
            return;
        }

        Schema::create('ebay3_sku_daily_data', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 191)->index();
            $table->date('record_date')->index();
            $table->json('daily_data')->nullable();
            $table->timestamps();

            $table->unique(['sku', 'record_date'], 'ebay3_sku_daily_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay3_sku_daily_data');
    }
};
