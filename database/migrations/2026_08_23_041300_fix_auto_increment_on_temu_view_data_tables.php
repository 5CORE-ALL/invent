<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * temu_view_data / temu2_view_data lost AUTO_INCREMENT on id in some
     * environments, so view uploads fail with:
     * SQLSTATE[HY000]: 1364 Field 'id' doesn't have a default value.
     */
    public function up(): void
    {
        foreach (['temu_view_data', 'temu2_view_data'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            try {
                $col = DB::selectOne("SHOW COLUMNS FROM `{$table}` WHERE Field = 'id'");
                if ($col && stripos((string) ($col->Extra ?? ''), 'auto_increment') === false) {
                    DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
                }
            } catch (\Throwable $e) {
                // Upload path also assigns id as a fallback.
            }
        }
    }

    public function down(): void
    {
        // Intentionally no-op: removing AUTO_INCREMENT would re-break inserts.
    }
};
