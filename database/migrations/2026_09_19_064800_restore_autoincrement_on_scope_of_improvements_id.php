<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * scope_of_improvements.id lost AUTO_INCREMENT in some environments, so
     * inserts fail with SQLSTATE[HY000]: 1364 Field 'id' doesn't have a default value.
     */
    public function up(): void
    {
        if (! Schema::hasTable('scope_of_improvements') || ! Schema::hasColumn('scope_of_improvements', 'id')) {
            return;
        }

        try {
            $hasPrimary = ! empty(DB::select("SHOW INDEX FROM `scope_of_improvements` WHERE Key_name = 'PRIMARY'"));
            if (! $hasPrimary) {
                DB::statement('ALTER TABLE `scope_of_improvements` ADD PRIMARY KEY (`id`)');
            } 

            $col = DB::selectOne("SHOW COLUMNS FROM `scope_of_improvements` WHERE Field = 'id'");
            $extra = strtolower((string) ($col->Extra ?? ''));
            if (! str_contains($extra, 'auto_increment')) {
                DB::statement('ALTER TABLE `scope_of_improvements` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
            }

            $max = (int) DB::table('scope_of_improvements')->max('id');
            DB::statement('ALTER TABLE `scope_of_improvements` AUTO_INCREMENT = '.max($max + 1, 1));
        } catch (\Throwable $e) {
            // Non-fatal: ScopeOfImprovement assigns id as a fallback.
        }
    }

    public function down(): void
    {
        // Intentionally no-op: removing AUTO_INCREMENT would re-break inserts.
    }
};
