<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Temu 3 Marketplace Manager. API orders live in temu3_api_orders because
     * temu3_orders is the sheet-upload table and is truncated on every upload.
     */
    public function up(): void
    {
        if (! Schema::hasTable('temu3_api_orders')) {
            Schema::create('temu3_api_orders', function (Blueprint $table) {
                $table->id();

                $table->string('parent_order_sn')->nullable();
                $table->integer('parent_order_status')->nullable();
                $table->string('parent_order_status_text')->nullable();
                $table->timestamp('parent_order_time')->nullable();
                $table->timestamp('expect_ship_latest_time')->nullable();
                $table->timestamp('parent_shipping_time')->nullable();
                $table->timestamp('latest_delivery_time')->nullable();
                $table->timestamp('order_update_time')->nullable();
                $table->integer('region_id')->nullable();
                $table->integer('site_id')->nullable();

                $table->string('order_sn')->nullable();
                $table->string('sku_id')->nullable();
                $table->string('goods_id')->nullable();
                $table->string('ext_code')->nullable();
                $table->string('product_sku_id')->nullable();
                $table->text('goods_name')->nullable();
                $table->text('spec')->nullable();
                $table->integer('quantity')->nullable();
                $table->integer('original_order_quantity')->nullable();
                $table->integer('canceled_quantity_before_shipment')->nullable();
                $table->decimal('order_base_amount', 12, 2)->nullable();
                $table->decimal('order_total_amount', 12, 2)->nullable();
                $table->integer('order_status')->nullable();
                $table->string('order_status_text')->nullable();
                $table->string('fulfillment_type')->nullable();
                $table->string('order_payment_type')->nullable();
                $table->text('thumb_url')->nullable();
                $table->timestamp('order_shipping_time')->nullable();
                $table->string('tracking_number', 128)->nullable();
                $table->string('carrier', 128)->nullable();
                $table->string('package_sn', 128)->nullable();
                $table->timestamp('tracking_fetched_at')->nullable();

                $table->longText('raw_json')->nullable();
                $table->longText('amount_raw_json')->nullable();
                $table->timestamp('amount_fetched_at')->nullable();

                $table->string('fetch_window')->nullable();
                $table->timestamp('fetched_at')->nullable();

                $table->string('shopify_order_id', 64)->nullable()->index();
                $table->timestamp('pushed_to_shopify_at')->nullable();
                $table->string('import_status', 32)->nullable()->index();
                $table->string('display_sku', 128)->nullable();

                $table->timestamps();

                $table->unique('order_sn');
                $table->index('parent_order_sn');
                $table->index('sku_id');
                $table->index('goods_id');
                $table->index('ext_code');
                $table->index('parent_order_time');
                $table->index('order_status');
                $table->index(['parent_order_sn', 'tracking_number'], 'temu3_api_orders_parent_tracking_idx');
            });
        }

        if (! Schema::hasTable('temu3_listing_statuses')) {
            Schema::create('temu3_listing_statuses', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->index();
                $table->json('value');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('product_stock_mappings') && ! Schema::hasColumn('product_stock_mappings', 'inventory_temu3')) {
            Schema::table('product_stock_mappings', function (Blueprint $table) {
                if (Schema::hasColumn('product_stock_mappings', 'inventory_temu2')) {
                    $table->integer('inventory_temu3')->nullable()->after('inventory_temu2');
                } else {
                    $table->integer('inventory_temu3')->nullable();
                }
            });
        }

        if (Schema::hasTable('temu3_metrics')) {
            Schema::table('temu3_metrics', function (Blueprint $table) {
                $columns = [
                    'inactive_reason' => fn () => $table->string('inactive_reason', 191)->nullable(),
                    'recommended_base_price' => fn () => $table->decimal('recommended_base_price', 12, 2)->nullable(),
                    'product_clicks_l7' => fn () => $table->unsignedBigInteger('product_clicks_l7')->nullable(),
                    'product_clicks_l1' => fn () => $table->unsignedBigInteger('product_clicks_l1')->nullable(),
                    'bullet_points' => fn () => $table->text('bullet_points')->nullable(),
                    'goods_summary' => fn () => $table->text('goods_summary')->nullable(),
                    'goods_desc' => fn () => $table->longText('goods_desc')->nullable(),
                    'description_master' => fn () => $table->text('description_master')->nullable(),
                    'image_urls' => fn () => $table->longText('image_urls')->nullable(),
                    'image_master_json' => fn () => $table->longText('image_master_json')->nullable(),
                    'video_master_json' => fn () => $table->longText('video_master_json')->nullable(),
                ];
                foreach ($columns as $name => $add) {
                    if (! Schema::hasColumn('temu3_metrics', $name)) {
                        $add();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('temu3_metrics')) {
            $drop = array_values(array_filter([
                'inactive_reason', 'recommended_base_price', 'product_clicks_l7', 'product_clicks_l1',
                'bullet_points', 'goods_summary', 'goods_desc', 'description_master',
                'image_urls', 'image_master_json', 'video_master_json',
            ], static fn (string $col) => Schema::hasColumn('temu3_metrics', $col)));
            if ($drop !== []) {
                Schema::table('temu3_metrics', function (Blueprint $table) use ($drop) {
                    $table->dropColumn($drop);
                });
            }
        }

        if (Schema::hasTable('product_stock_mappings') && Schema::hasColumn('product_stock_mappings', 'inventory_temu3')) {
            Schema::table('product_stock_mappings', function (Blueprint $table) {
                $table->dropColumn('inventory_temu3');
            });
        }

        Schema::dropIfExists('temu3_listing_statuses');
        Schema::dropIfExists('temu3_api_orders');
    }
};
