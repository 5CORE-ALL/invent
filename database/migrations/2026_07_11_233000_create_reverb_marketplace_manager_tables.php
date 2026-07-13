<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reverb_metric')) {
            Schema::create('reverb_metric', function (Blueprint $table) {
                $table->id();
                $table->string('product_id', 64)->nullable()->index();
                $table->string('sku', 128)->nullable()->index();
                $table->string('product_name')->nullable();
                $table->decimal('price', 12, 2)->nullable();
                $table->unsignedInteger('l30')->nullable();
                $table->unsignedInteger('l60')->nullable();
                $table->json('order_dates')->nullable();
                $table->dateTime('last_order_date')->nullable();
                $table->text('bullet_points')->nullable();
                $table->timestamps();
                $table->unique(['product_id', 'sku'], 'reverb_metric_product_sku_unique');
            });
        }

        if (! Schema::hasTable('reverb_pricing_prices')) {
            Schema::create('reverb_pricing_prices', function (Blueprint $table) {
                $table->id();
                $table->string('sku', 128)->unique();
                $table->decimal('price', 12, 2)->nullable();
                $table->integer('rv_stock')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('reverb_order_metrics')) {
            Schema::table('reverb_order_metrics', function (Blueprint $table) {
                if (! Schema::hasColumn('reverb_order_metrics', 'order_id')) {
                    $table->string('order_id', 64)->nullable()->index()->after('id');
                }
                if (! Schema::hasColumn('reverb_order_metrics', 'product_id')) {
                    $table->string('product_id', 64)->nullable()->after('sku');
                }
                if (! Schema::hasColumn('reverb_order_metrics', 'display_title')) {
                    $table->string('display_title', 512)->nullable()->after('product_id');
                }
                if (! Schema::hasColumn('reverb_order_metrics', 'raw_payload')) {
                    $table->json('raw_payload')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reverb_metric');
        Schema::dropIfExists('reverb_pricing_prices');
    }
};
