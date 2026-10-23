<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The sidebar missing-ads badges count eBay listings with a join to metrics
 * and a per-row Shopify inventory lookup. Without these indexes that count
 * scans every metrics row and every Shopify SKU, and the whole page waits.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('ebay_3_metrics', 'item_id', 'ebay_3_metrics_item_id_index');
        $this->addIndex('ebay_metrics', 'item_id', 'ebay_metrics_item_id_index');
        $this->addIndex('shopify_skus', 'sku', 'shopify_skus_sku_index');
    }

    public function down(): void
    {
        $this->dropIndex('ebay_3_metrics', 'ebay_3_metrics_item_id_index');
        $this->dropIndex('ebay_metrics', 'ebay_metrics_item_id_index');
        $this->dropIndex('shopify_skus', 'shopify_skus_sku_index');
    }

    private function addIndex(string $table, string $column, string $name): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || $this->hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($column, $name) {
            $table->index($column, $name);
        });
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! Schema::hasTable($table) || ! $this->hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($name) {
            $table->dropIndex($name);
        });
    }

    private function hasIndex(string $table, string $name): bool
    {
        $row = DB::selectOne(
            'SELECT 1 AS ok FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $name]
        );

        return $row !== null;
    }
};
