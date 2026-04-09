<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Fix: Field 'id' doesn't have a default value - ensure id is AUTO_INCREMENT.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('tasks')) {
            return;
        }

        $idColumn = DB::selectOne("SHOW COLUMNS FROM `tasks` WHERE Field = 'id'");
        if ($idColumn && isset($idColumn->Extra) && str_contains(strtolower((string) $idColumn->Extra), 'auto_increment')) {
            return;
        }

        DB::statement('SET @__mig_old_sql_mode = @@SESSION.sql_mode');
        DB::statement("SET SESSION sql_mode = ''");
        try {
            DB::statement('ALTER TABLE `tasks` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        } finally {
            DB::statement('SET SESSION sql_mode = @__mig_old_sql_mode');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverting AUTO_INCREMENT is rarely needed; leave no-op.
    }
};
