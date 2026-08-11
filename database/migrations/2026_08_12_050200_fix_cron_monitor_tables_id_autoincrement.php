<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restored dumps often lose AUTO_INCREMENT on cron monitor tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['cron_monitor_alerts', 'cron_execution_failures'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
                continue;
            }

            $col = DB::selectOne("SHOW COLUMNS FROM `{$table}` WHERE Field = 'id'");
            $extra = strtolower((string) ($col->Extra ?? ''));
            if (str_contains($extra, 'auto_increment')) {
                continue;
            }

            $next = max(1, ((int) DB::table($table)->max('id')) + 1);
            DB::statement("ALTER TABLE `{$table}` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
            DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = {$next}");
        }
    }

    public function down(): void
    {
        // Irreversible repair.
    }
};
