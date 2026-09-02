<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Temu 3 is sheet-only (no Open API). Pricing / views / SPRICE live in
     * dedicated tables so Temu 1 and Temu 2 uploads are never overwritten.
     */
    public function up(): void
    {
        if (! Schema::hasTable('temu3_pricing')) {
            Schema::create('temu3_pricing', function (Blueprint $table) {
                $table->id();
                $table->string('category')->nullable();
                $table->string('category_id')->nullable();
                $table->text('product_name')->nullable();
                $table->string('contribution_goods')->nullable();
                $table->string('sku')->index();
                $table->string('goods_id')->nullable();
                $table->string('sku_id')->nullable();
                $table->string('variation')->nullable();
                $table->integer('quantity')->default(0);
                $table->decimal('base_price', 10, 2)->nullable();
                $table->string('external_product_id_type')->nullable();
                $table->string('external_product_id')->nullable();
                $table->string('status')->nullable();
                $table->string('detail_status')->nullable();
                $table->timestamp('date_created')->nullable();
                $table->text('incomplete_product_information')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('temu3_data_view')) {
            Schema::create('temu3_data_view', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->unique();
                $table->json('value')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('temu3_view_data')) {
            Schema::create('temu3_view_data', function (Blueprint $table) {
                $table->id();
                $table->date('date')->nullable();
                $table->string('goods_id')->nullable()->index();
                $table->text('goods_name')->nullable();
                $table->integer('product_impressions')->default(0);
                $table->integer('visitor_impressions')->default(0);
                $table->integer('product_clicks')->default(0);
                $table->integer('visitor_clicks')->default(0);
                $table->decimal('ctr', 8, 2)->default(0);
                $table->timestamps();

                $table->index(['goods_id', 'date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('temu3_view_data');
        Schema::dropIfExists('temu3_data_view');
        Schema::dropIfExists('temu3_pricing');
    }
};
