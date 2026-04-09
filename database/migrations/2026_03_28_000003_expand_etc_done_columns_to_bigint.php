<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->modifyWithRelaxedSqlModeOnTable('tasks', 'ALTER TABLE `tasks` MODIFY COLUMN `etc_done` BIGINT NULL');
        $this->modifyWithRelaxedSqlModeOnTable('deleted_tasks', 'ALTER TABLE `deleted_tasks` MODIFY COLUMN `etc_done` BIGINT NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->modifyWithRelaxedSqlModeOnTable('tasks', 'ALTER TABLE `tasks` MODIFY COLUMN `etc_done` INT NULL');
        $this->modifyWithRelaxedSqlModeOnTable('deleted_tasks', 'ALTER TABLE `deleted_tasks` MODIFY COLUMN `etc_done` INT NULL');
    }

    private function modifyWithRelaxedSqlModeOnTable(string $table, string $sql): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        DB::statement('SET @__mig_old_sql_mode = @@SESSION.sql_mode');
        DB::statement("SET SESSION sql_mode = ''");
        try {
            DB::statement($sql);
        } finally {
            DB::statement('SET SESSION sql_mode = @__mig_old_sql_mode');
        }
    }
};
