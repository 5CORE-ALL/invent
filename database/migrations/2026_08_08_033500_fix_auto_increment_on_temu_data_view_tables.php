<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * temu_data_view / temu2_data_view were created without AUTO_INCREMENT on id
     * in some environments, so firstOrNew()->save() inserts fail with:
     * SQLSTATE[HY000]: 1364 Field 'id' doesn't have a default value
     * (breaks /temu-pricing/save-sprice when cross-applying to Temu 2).
     */
    public function up(): void
    {
        foreach (['temu_data_view', 'temu2_data_view'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            try {
                $col = DB::selectOne("SHOW COLUMNS FROM `{$table}` WHERE Field = 'id'");
                if ($col && stripos((string) ($col->Extra ?? ''), 'auto_increment') === false) {
                    DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
                }
            } catch (\Throwable $e) {
                // Non-fatal: write path also assigns id as a fallback.
            }
        }
    }

    public function down(): void
    {
        // Intentionally no-op: removing AUTO_INCREMENT would re-break inserts.
    }
};
