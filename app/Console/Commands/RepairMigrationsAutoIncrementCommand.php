<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RepairMigrationsAutoIncrementCommand extends Command
{
    protected $signature = 'migrate:repair-autoincrement';

    protected $description = 'Restore AUTO_INCREMENT on migrations.id so artisan migrate can record completed files';

    public function handle(): int
    {
        if (! Schema::hasTable('migrations') || ! Schema::hasColumn('migrations', 'id')) {
            $this->error('migrations table or id column is missing.');

            return self::FAILURE;
        }

        $col = DB::selectOne("SHOW COLUMNS FROM `migrations` WHERE Field = 'id'");
        $extra = strtolower((string) ($col->Extra ?? ''));
        if (str_contains($extra, 'auto_increment')) {
            $this->info('migrations.id already has AUTO_INCREMENT.');

            return self::SUCCESS;
        }

        $next = max(1, ((int) DB::table('migrations')->max('id')) + 1);
        $type = strtolower((string) ($col->Type ?? 'int(10) unsigned'));
        $definition = str_contains($type, 'bigint')
            ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT'
            : 'INT UNSIGNED NOT NULL AUTO_INCREMENT';

        DB::statement("ALTER TABLE `migrations` MODIFY `id` {$definition}");
        DB::statement("ALTER TABLE `migrations` AUTO_INCREMENT = {$next}");

        $this->info("Restored AUTO_INCREMENT on migrations.id (next = {$next}).");

        return self::SUCCESS;
    }
}
