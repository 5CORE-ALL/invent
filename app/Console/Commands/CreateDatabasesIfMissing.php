<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class CreateDatabasesIfMissing extends Command
{
    protected $signature = 'db:create-if-missing
                            {--databases=api_central,shiphub : Comma-separated database names (default: api_central, shiphub)}';

    protected $description = 'Create MySQL databases (e.g. apicentral, shiphub) if they do not exist';

    public function handle(): int
    {
        $names = array_map('trim', explode(',', $this->option('databases')));
        $names = array_filter($names);

        if (empty($names)) {
            $this->warn('No database names given.');
            return self::SUCCESS;
        }

        $driver = Config::get('database.default');
        if ($driver !== 'mysql') {
            $this->warn('This command only supports MySQL. Current driver: ' . $driver);
            return self::SUCCESS;
        }

        $conn = Config::get('database.connections.mysql');
        $host = $conn['host'] ?? '127.0.0.1';
        $port = $conn['port'] ?? '3306';
        $username = $conn['username'] ?? '';
        $password = $conn['password'] ?? '';
        $charset = $conn['charset'] ?? 'utf8mb4';
        $collation = $conn['collation'] ?? 'utf8mb4_unicode_ci';

        foreach ($names as $name) {
            $safeName = '`' . str_replace('`', '``', $name) . '`';
            try {
                DB::statement("CREATE DATABASE IF NOT EXISTS {$safeName} CHARACTER SET {$charset} COLLATE {$collation}");
                $this->info("Database {$name}: exists or created.");
            } catch (\Throwable $e) {
                $this->error("Database {$name}: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
