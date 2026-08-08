<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * temu2_pricing / temu2_metrics lost AUTO_INCREMENT on id in some environments,
     * so inserts fail with: Field 'id' doesn't have a default value.
     */
    public function up(): void
    {
        foreach (['temu2_pricing', 'temu2_metrics'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            try {
                $col = DB::selectOne("SHOW COLUMNS FROM `{$table}` WHERE Field = 'id'");
                if ($col && stripos((string) ($col->Extra ?? ''), 'auto_increment') === false) {
                    DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
                }
            } catch (\Throwable $e) {
                // Non-fatal: upload path also assigns id as a fallback.
            }
        }
    }

    public function down(): void
    {
        // Intentionally no-op: removing AUTO_INCREMENT would re-break inserts.
    }
};
