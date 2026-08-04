<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * purchasing_power_data_views was created without PRIMARY KEY / AUTO_INCREMENT on id
     * in some environments, so new SPRICE rows fail with:
     * "Field 'id' doesn't have a default value".
     */
    public function up(): void
    {
        if (!Schema::hasTable('purchasing_power_data_views')) {
            return;
        }

        $hasPrimary = !empty(DB::select("SHOW INDEX FROM purchasing_power_data_views WHERE Key_name = 'PRIMARY'"));
        if (!$hasPrimary) {
            DB::statement('ALTER TABLE purchasing_power_data_views ADD PRIMARY KEY (id)');
        }

        $col = DB::selectOne("SHOW COLUMNS FROM purchasing_power_data_views WHERE Field = 'id'");
        $extra = strtolower((string) ($col->Extra ?? ''));
        if (!str_contains($extra, 'auto_increment')) {
            DB::statement('ALTER TABLE purchasing_power_data_views MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }
    }

    public function down(): void
    {
        // Intentionally no-op: do not remove AUTO_INCREMENT / PRIMARY KEY.
    }
};
