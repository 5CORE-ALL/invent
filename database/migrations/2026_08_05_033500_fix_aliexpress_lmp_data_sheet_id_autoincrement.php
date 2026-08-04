<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * aliexpress_lmp_data_sheet.id was missing PRIMARY KEY / AUTO_INCREMENT in some
     * environments, so new LMP rows fail with "Field 'id' doesn't have a default value".
     */
    public function up(): void
    {
        if (!Schema::hasTable('aliexpress_lmp_data_sheet')) {
            return;
        }

        $hasPrimary = !empty(DB::select("SHOW INDEX FROM aliexpress_lmp_data_sheet WHERE Key_name = 'PRIMARY'"));
        if (!$hasPrimary) {
            DB::statement('ALTER TABLE aliexpress_lmp_data_sheet ADD PRIMARY KEY (id)');
        }

        $col = DB::selectOne("SHOW COLUMNS FROM aliexpress_lmp_data_sheet WHERE Field = 'id'");
        $extra = strtolower((string) ($col->Extra ?? ''));
        if (!str_contains($extra, 'auto_increment')) {
            DB::statement('ALTER TABLE aliexpress_lmp_data_sheet MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }
    }

    public function down(): void
    {
        // no-op
    }
};
