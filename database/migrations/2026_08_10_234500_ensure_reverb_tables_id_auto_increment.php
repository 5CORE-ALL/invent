<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Some restored dumps lose AUTO_INCREMENT on reverb_* id columns, which breaks
 * reverb:fetch (delete+insert) and new ReverbProduct / order metric creates.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['reverb_products', 'reverb_order_metrics', 'migrations'] as $table) {
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
        // Irreversible intentionally — removing AUTO_INCREMENT breaks inserts.
    }
};
