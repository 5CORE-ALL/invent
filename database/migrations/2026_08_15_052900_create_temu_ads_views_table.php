<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Temu Ads report upload (ads.txt / Seller Center ads export).
     * Joined to /temu-decrease by goods_id — same key as Views (temu_view_data).
     */
    public function up(): void
    {
        if (Schema::hasTable('temu_ads_views')) {
            return;
        }

        Schema::create('temu_ads_views', function (Blueprint $table) {
            $table->id();
            $table->string('goods_id')->nullable()->index();
            $table->string('sku')->nullable()->index();
            $table->text('goods_name')->nullable();
            $table->decimal('spend', 12, 2)->default(0);
            $table->decimal('net_total_cost', 12, 2)->default(0);
            $table->decimal('base_price_sales', 12, 2)->default(0);
            $table->decimal('roas', 10, 2)->default(0);
            $table->decimal('acos', 10, 2)->default(0);
            $table->decimal('cost_per_order', 12, 2)->default(0);
            $table->integer('sub_order_count')->default(0);
            $table->integer('items')->default(0);
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0)->comment('Clicks (Overall) — Ads Views column');
            $table->decimal('ctr', 8, 2)->default(0);
            $table->decimal('cvr', 8, 2)->default(0);
            $table->integer('add_to_cart_count')->default(0);
            $table->decimal('net_base_price_sales', 12, 2)->default(0);
            $table->decimal('net_roas', 10, 2)->default(0);
            $table->decimal('net_acos', 10, 2)->default(0);
            $table->decimal('net_cost_per_order', 12, 2)->default(0);
            $table->integer('net_sub_order_count')->default(0);
            $table->integer('net_items')->default(0);
            $table->timestamps();

            $table->index(['goods_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temu_ads_views');
    }
};
