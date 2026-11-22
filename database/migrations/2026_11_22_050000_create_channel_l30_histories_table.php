<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One L30 (units sold, rolling 30 days) value per SKU / channel / day.
 * Written whenever the SKU modal loads its Channels breakdown; read by the L30 history dot.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('channel_l30_histories')) {
            return;
        }
        Schema::create('channel_l30_histories', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 191);
            $table->string('marketplace', 64);
            $table->date('record_date');
            $table->integer('l30')->default(0);
            $table->timestamps();

            $table->unique(['sku', 'marketplace', 'record_date'], 'ch_l30_hist_unique');
            $table->index(['sku', 'marketplace'], 'ch_l30_hist_sku_mp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_l30_histories');
    }
};
