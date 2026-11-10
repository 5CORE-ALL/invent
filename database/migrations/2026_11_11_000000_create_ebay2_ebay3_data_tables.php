<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tables of the Ebay1 Data page. eBay 2 and eBay 3 get a copy of each (same columns and indexes). */
    private const TABLES = [
        'item_specifics', 'extra_columns', 'column_sources', 'column_groups',
        'settings', 'pending_specifics', 'cell_styles', 'missing_history',
    ];

    public function up(): void
    {
        foreach (['ebay2', 'ebay3'] as $prefix) {
            foreach (self::TABLES as $name) {
                $from = 'ebay1_'.$name;
                $to = $prefix.'_'.$name;
                if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
                    DB::statement('CREATE TABLE `'.$to.'` LIKE `'.$from.'`');
                }
            }

            // Start from what eBay 1 already holds: the pages follow eBay 1 from now on.
            // The eBay 1 item ID is not copied (each account has its own listing).
            if (Schema::hasTable('ebay1_item_specifics') && Schema::hasTable($prefix.'_item_specifics')
                && ! DB::table($prefix.'_item_specifics')->exists()) {
                DB::statement(
                    'INSERT INTO `'.$prefix.'_item_specifics` (sku, item_id, category_id, category_path, specifics, pulled_at, created_at, updated_at) '
                    .'SELECT sku, NULL, category_id, category_path, specifics, pulled_at, NOW(), NOW() FROM ebay1_item_specifics'
                );
            }
            foreach (['extra_columns', 'column_sources'] as $name) {
                $from = 'ebay1_'.$name;
                $to = $prefix.'_'.$name;
                if (Schema::hasTable($from) && Schema::hasTable($to) && ! DB::table($to)->exists()) {
                    DB::statement('INSERT INTO `'.$to.'` SELECT * FROM `'.$from.'`');
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['ebay2', 'ebay3'] as $prefix) {
            foreach (self::TABLES as $name) {
                Schema::dropIfExists($prefix.'_'.$name);
            }
        }
    }
};
