<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores per-SKU click-through-rate snapshots fetched from Amazon.
 *
 * Two sources:
 *   - source = 'ads'      → Amazon Advertising API (Sponsored Products report, paid CTR)
 *   - source = 'organic'  → SP-API Brand Analytics Search Catalog Performance Report (organic search CTR)
 *
 * One row per (sku, source, period_start, period_end). The Hero Images Master
 * page joins the latest row per (sku, source) to surface "Ads CTR" / "Org CTR"
 * columns. Schedule the fetcher commands to keep this table fresh.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('amazon_ctr_metrics')) {
            return;
        }

        Schema::create('amazon_ctr_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 191)->index();
            $table->string('asin', 32)->nullable()->index();
            $table->enum('source', ['ads', 'organic']);
            $table->string('period_label', 24)->nullable(); // DAILY | SUMMARY | WEEK | MONTH | QUARTER
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->decimal('ctr', 8, 4)->nullable(); // percent, e.g. 3.4500
            $table->unsignedBigInteger('cart_adds')->nullable();
            $table->unsignedBigInteger('purchases')->nullable();
            $table->decimal('spend', 12, 2)->nullable();   // ads only
            $table->decimal('sales', 12, 2)->nullable();   // ads only
            $table->json('raw')->nullable();               // full row from Amazon for debugging
            $table->timestamp('fetched_at')->useCurrent();
            $table->timestamps();

            $table->unique(['sku', 'source', 'period_start', 'period_end'], 'amzn_ctr_unique_per_period');
            $table->index(['source', 'period_end'], 'amzn_ctr_source_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_ctr_metrics');
    }
};
