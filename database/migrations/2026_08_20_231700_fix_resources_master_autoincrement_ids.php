<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'resources_master',
            'resource_departments',
            'resource_tags',
            'resource_department_map',
            'resource_tag_map',
            'resource_access_logs',
            'resource_audit_logs',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $col = collect(DB::select("SHOW COLUMNS FROM `{$table}` WHERE Field = 'id'"))->first();
            if (! $col) {
                continue;
            }

            if (str_contains(strtolower((string) $col->Extra), 'auto_increment')) {
                continue;
            }

            // Imported dumps can lose the primary key, and MySQL only allows AUTO_INCREMENT on a key.
            $hasKey = collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Column_name = 'id'"))->isNotEmpty();
            DB::statement("ALTER TABLE `{$table}` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT".($hasKey ? '' : ', ADD PRIMARY KEY (`id`)'));
        }
    }

    public function down(): void
    {
        // Keep AUTO_INCREMENT; removing it would break inserts again.
    }
};
