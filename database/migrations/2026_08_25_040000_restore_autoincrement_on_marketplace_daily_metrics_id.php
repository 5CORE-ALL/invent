<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_daily_metrics') || ! Schema::hasColumn('marketplace_daily_metrics', 'id')) {
            return;
        }

        $col = DB::selectOne("SHOW COLUMNS FROM marketplace_daily_metrics WHERE Field = 'id'");
        $extra = strtolower((string) ($col->Extra ?? ''));
        if (str_contains($extra, 'auto_increment')) {
            return;
        }

        $next = ((int) DB::table('marketplace_daily_metrics')->max('id')) + 1;
        if ($next < 1) {
            $next = 1;
        }

        DB::statement('ALTER TABLE marketplace_daily_metrics MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE marketplace_daily_metrics AUTO_INCREMENT = '.$next);
    }

    public function down(): void
    {
        // Keep AUTO_INCREMENT; removing it would break inserts again.
    }
};
