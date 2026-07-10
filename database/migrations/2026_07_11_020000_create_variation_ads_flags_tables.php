<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Current on/off state of each channel checkbox per SKU. Absence of a row
        // means the default (green / checked = true).
        Schema::create('variation_ads_flags', function (Blueprint $table) {
            $table->id();
            $table->string('sku');
            $table->string('col_key', 32);      // amz_kw | amz_pt | ebay2 | google_shop
            $table->boolean('checked')->default(true);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['sku', 'col_key'], 'variation_ads_flag_unique');
            $table->index('col_key');
        });

        // Date-wise snapshot of how many SKUs are green (checked) per channel column,
        // one row per (date, column) — feeds the trend graph.
        Schema::create('variation_ads_flag_daily', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date');
            $table->string('col_key', 32);
            $table->unsignedInteger('green_count')->default(0);
            $table->unsignedInteger('total_count')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->unique(['snapshot_date', 'col_key'], 'variation_ads_daily_unique');
            $table->index('col_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variation_ads_flag_daily');
        Schema::dropIfExists('variation_ads_flags');
    }
};
