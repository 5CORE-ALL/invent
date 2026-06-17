<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes for Title Master list query and title saves (sku lookups / joins).
     */
    public function up(): void
    {
        $safeIndex = function (string $table, callable $callback): void {
            if (! Schema::hasTable($table)) {
                return;
            }
            try {
                Schema::table($table, $callback);
            } catch (\Throwable $e) {
                // Index may already exist (re-run / manual DDL).
            }
        };

        $safeIndex('product_stock_mappings', function (Blueprint $table) {
            $table->index('sku', 'product_stock_mappings_sku_index');
        });

        $safeIndex('amazon_listings_raw', function (Blueprint $table) {
            $table->index(['seller_sku', 'id'], 'amazon_listings_raw_seller_sku_id_index');
        });

        $safeIndex('shopify_skus', function (Blueprint $table) {
            $table->index('sku', 'shopify_skus_sku_index');
        });

        $safeIndex('amazon_datsheets', function (Blueprint $table) {
            $table->index('sku', 'amazon_datsheets_sku_index');
        });

        $safeIndex('product_master', function (Blueprint $table) {
            $table->index(['deleted_at', 'parent'], 'product_master_deleted_parent_index');
        });
    }

    public function down(): void
    {
        $drop = function (string $table, string $indexName): void {
            if (! Schema::hasTable($table)) {
                return;
            }
            try {
                Schema::table($table, function (Blueprint $t) use ($indexName) {
                    $t->dropIndex($indexName);
                });
            } catch (\Throwable $e) {
                //
            }
        };

        $drop('product_stock_mappings', 'product_stock_mappings_sku_index');
        $drop('amazon_listings_raw', 'amazon_listings_raw_seller_sku_id_index');
        $drop('shopify_skus', 'shopify_skus_sku_index');
        $drop('amazon_datsheets', 'amazon_datsheets_sku_index');
        $drop('product_master', 'product_master_deleted_parent_index');
    }
};
