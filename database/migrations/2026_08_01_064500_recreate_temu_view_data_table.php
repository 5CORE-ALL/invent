<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recreate temu_view_data (dropped 2026-07-30). Used for Seller Center
     * product-clicks sheet uploads — Temu OpenAPI has no product-page views endpoint
     * (ads API clkCntAll only covers ad clicks).
     */
    public function up(): void
    {
        if (Schema::hasTable('temu_view_data')) {
            return;
        }

        Schema::create('temu_view_data', function (Blueprint $table) {
            $table->id();
            $table->date('date')->nullable();
            $table->string('goods_id')->nullable()->index();
            $table->text('goods_name')->nullable();
            $table->integer('product_impressions')->default(0);
            $table->integer('visitor_impressions')->default(0);
            $table->integer('product_clicks')->default(0);
            $table->integer('visitor_clicks')->default(0);
            $table->decimal('ctr', 8, 2)->default(0)->comment('CTR percentage');
            $table->timestamps();

            $table->index(['goods_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temu_view_data');
    }
};
