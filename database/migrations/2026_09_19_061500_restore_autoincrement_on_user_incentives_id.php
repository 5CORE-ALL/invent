<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * user_incentives.id lost AUTO_INCREMENT in some environments, so inserts
     * fail with SQLSTATE[HY000]: 1364 Field 'id' doesn't have a default value.
     */
    public function up(): void
    {
        if (! Schema::hasTable('user_incentives') || ! Schema::hasColumn('user_incentives', 'id')) {
            return;
        }

        try {
            $hasPrimary = ! empty(DB::select("SHOW INDEX FROM `user_incentives` WHERE Key_name = 'PRIMARY'"));
            if (! $hasPrimary) {
                DB::statement('ALTER TABLE `user_incentives` ADD PRIMARY KEY (`id`)');
            }

            $col = DB::selectOne("SHOW COLUMNS FROM `user_incentives` WHERE Field = 'id'");
            $extra = strtolower((string) ($col->Extra ?? ''));
            if (! str_contains($extra, 'auto_increment')) {
                DB::statement('ALTER TABLE `user_incentives` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
            }

            $max = (int) DB::table('user_incentives')->max('id');
            DB::statement('ALTER TABLE `user_incentives` AUTO_INCREMENT = '.max($max + 1, 1));
        } catch (\Throwable $e) {
            // Non-fatal: UserIncentive assigns id as a fallback.
        }
    }

    public function down(): void
    {
        // Intentionally no-op: removing AUTO_INCREMENT would re-break inserts.
    }
};
