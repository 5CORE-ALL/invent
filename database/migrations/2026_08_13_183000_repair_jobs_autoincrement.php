<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureAutoIncrementId('jobs', 'bigint unsigned');
        $this->ensureQueueIndex();
        $this->ensureAutoIncrementId('failed_jobs', 'bigint unsigned');
    }

    public function down(): void
    {
        // Leave keys in place — removing AUTO_INCREMENT would break the queue again.
    }

    protected function ensureAutoIncrementId(string $table, string $type): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
            return;
        }

        $col = collect(DB::select("SHOW COLUMNS FROM `{$table}` LIKE 'id'"))->first();
        if (! $col) {
            return;
        }
        $extra = strtolower((string) ($col->Extra ?? ''));
        if (str_contains($extra, 'auto_increment')) {
            return;
        }

        $indexes = collect(DB::select("SHOW INDEX FROM `{$table}`"));
        $hasPrimary = $indexes->contains(fn ($idx) => strtoupper((string) $idx->Key_name) === 'PRIMARY');
        if (! $hasPrimary) {
            DB::statement("ALTER TABLE `{$table}` ADD PRIMARY KEY (`id`)");
        }

        DB::statement("ALTER TABLE `{$table}` MODIFY `id` {$type} NOT NULL AUTO_INCREMENT");
        $max = (int) DB::table($table)->max('id');
        DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = ".($max + 1));
    }

    protected function ensureQueueIndex(): void
    {
        if (! Schema::hasTable('jobs')) {
            return;
        }
        $indexes = collect(DB::select('SHOW INDEX FROM `jobs`'));
        if ($indexes->contains(fn ($idx) => $idx->Column_name === 'queue')) {
            return;
        }
        DB::statement('ALTER TABLE `jobs` ADD INDEX `jobs_queue_index` (`queue`)');
    }
};
