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
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (['tasks', 'deleted_tasks', 'automate_tasks'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'assign_to')) {
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` MODIFY `assign_to` VARCHAR(255) NULL");
        }
    }
};
