<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace order tables lost AUTO_INCREMENT on `id` (same class of dump/restore
 * issue as shopify_raw_orders). New Faire / Temu / eBay / … rows then fail with
 * SQL 1364 and Marketplace Manager stops syncing.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    protected array $tables = [
        'faire_order_metrics',
        'amazon_orders',
        'aliexpress_order_metrics',
        'alibaba_order_metrics',
        'reverb_order_metrics',
        'newegg_order_metrics',
        'shein_order_metrics',
        'temu_orders',
        'temu2_orders',
        'ebay1_order_metrics',
        'ebay2_order_metrics',
        'ebay3_order_metrics',
        'doba_daily_data',
        'tiktok_orders',
        'tiktok2_orders',
        'wayfair_daily_data',
        'bestbuy_order_metrics',
        'macy_order_metrics',
        'purchasing_power_sales',
        'topdawg_order_metrics',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            $this->restoreAutoIncrement($table);
        }
    }

    public function down(): void
    {
        // Keep AUTO_INCREMENT — inserts require it.
    }

    protected function restoreAutoIncrement(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
            return;
        }

        $col = collect(DB::select('SHOW COLUMNS FROM '.$table.' WHERE Field = ?', ['id']))->first();
        $extra = strtolower((string) ($col->Extra ?? ''));
        $key = strtoupper((string) ($col->Key ?? ''));
        if (str_contains($extra, 'auto_increment') && $key === 'PRI') {
            return;
        }

        $type = strtoupper((string) ($col->Type ?? 'bigint unsigned'));
        if (! str_contains(strtolower($type), 'int')) {
            $type = 'BIGINT UNSIGNED';
        }

        if ($key !== 'PRI') {
            DB::statement("ALTER TABLE `{$table}` MODIFY `id` {$type} NOT NULL");
            $indexes = collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Column_name = 'id' AND Key_name = 'PRIMARY'"));
            if ($indexes->isEmpty()) {
                DB::statement("ALTER TABLE `{$table}` ADD PRIMARY KEY (`id`)");
            }
        }

        DB::statement("ALTER TABLE `{$table}` MODIFY `id` {$type} NOT NULL AUTO_INCREMENT");
    }
};
