<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Title Master joins (and the SKU union that drives the listing query) match on
 * product_stock_mappings.sku, which previously had no index — forcing a full-scan
 * block-nested-loop join. Adding this index removes that scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_stock_mappings') || ! Schema::hasColumn('product_stock_mappings', 'sku')) {
            return;
        }
        if ($this->indexExists('product_stock_mappings', 'product_stock_mappings_sku_index')) {
            return;
        }
        // sku is VARCHAR(255) with utf8mb4; a full-column index exceeds InnoDB's 1000-byte limit.
        DB::statement('ALTER TABLE `product_stock_mappings` ADD INDEX `product_stock_mappings_sku_index` (`sku`(191))');
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_stock_mappings')) {
            return;
        }
        if (! $this->indexExists('product_stock_mappings', 'product_stock_mappings_sku_index')) {
            return;
        }
        Schema::table('product_stock_mappings', function ($table) {
            $table->dropIndex('product_stock_mappings_sku_index');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);

            return ! empty($rows);
        } catch (\Throwable $e) {
            return false;
        }
    }
};
