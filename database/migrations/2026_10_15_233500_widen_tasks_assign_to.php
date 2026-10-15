<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Joint tasks store every assignee email in assign_to.
     * varchar(255) is too short once many people are selected.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Some restored DBs run in strict mode yet contain legacy
        // '0000-00-00 00:00:00' datetimes. Rebuilding the table for the ALTER
        // re-validates every row and fails. Relax sql_mode for this session so
        // the column widening can proceed, then restore it.
        $originalSqlMode = (string) (DB::selectOne('SELECT @@SESSION.sql_mode AS mode')->mode ?? '');
        DB::statement("SET SESSION sql_mode = ''");

        try {
            foreach (['tasks', 'deleted_tasks', 'automate_tasks'] as $table) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'assign_to')) {
                    continue;
                }

                $column = DB::selectOne("SHOW COLUMNS FROM `{$table}` WHERE Field = 'assign_to'");
                $type = strtolower((string) ($column->Type ?? ''));
                if ($type === '' || str_contains($type, 'text')) {
                    continue;
                }

                DB::statement("ALTER TABLE `{$table}` MODIFY `assign_to` TEXT NULL");
            }
        } finally {
            DB::statement('SET SESSION sql_mode = ?', [$originalSqlMode]);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $originalSqlMode = (string) (DB::selectOne('SELECT @@SESSION.sql_mode AS mode')->mode ?? '');
        DB::statement("SET SESSION sql_mode = ''");

        try {
            foreach (['tasks', 'deleted_tasks', 'automate_tasks'] as $table) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'assign_to')) {
                    continue;
                }

                DB::statement("ALTER TABLE `{$table}` MODIFY `assign_to` VARCHAR(255) NULL");
            }
        } finally {
            DB::statement('SET SESSION sql_mode = ?', [$originalSqlMode]);
        }
    }
};
