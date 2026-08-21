<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * tiktok_shop_data_views / tiktok_two_shop_data_views lost AUTO_INCREMENT on id
     * in some environments, so firstOrNew()->save() inserts fail with:
     * SQLSTATE[HY000]: 1364 Field 'id' doesn't have a default value
     * (breaks /tiktok-data-json when INV<=0 auto-saves NR=NRA).
     */
    public function up(): void
    {
        foreach (['tiktok_shop_data_views', 'tiktok_two_shop_data_views'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            try {
                $hasPrimary = ! empty(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = 'PRIMARY'"));
                if (! $hasPrimary) {
                    DB::statement("ALTER TABLE `{$table}` ADD PRIMARY KEY (`id`)");
                }

                $col = DB::selectOne("SHOW COLUMNS FROM `{$table}` WHERE Field = 'id'");
                $extra = strtolower((string) ($col->Extra ?? ''));
                if (! str_contains($extra, 'auto_increment')) {
                    DB::statement("ALTER TABLE `{$table}` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
                }
            } catch (\Throwable $e) {
                // Non-fatal: TiktokShopDataView / TiktokTwoShopDataView assign id as a fallback.
            }
        }
    }

    public function down(): void
    {
        // Intentionally no-op: removing AUTO_INCREMENT would re-break inserts.
    }
};
