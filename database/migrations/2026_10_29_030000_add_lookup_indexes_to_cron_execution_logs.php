<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every scheduled command asks the log table "when did this job last succeed" and "did it run since X".
 * Without an index that covers job_name + status + finished_at (or command + started_at) each of those
 * scanned up to 200,000 rows and took 10-20 seconds, which held up the whole scheduler and loaded MySQL.
 */
return new class extends Migration
{
    private const INDEXES = [
        'cron_execution_logs_job_status_finished_idx' => '(`job_name`, `status`, `finished_at`)',
        'cron_execution_logs_command_started_idx' => '(`command`, `started_at`)',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('cron_execution_logs')) {
            return;
        }
        $existing = collect(DB::select('SHOW INDEX FROM `cron_execution_logs`'))->pluck('Key_name')->all();
        foreach (self::INDEXES as $name => $columns) {
            if (in_array($name, $existing, true)) {
                continue;
            }
            // In place and without locking the table.
            DB::statement("ALTER TABLE `cron_execution_logs` ADD INDEX `{$name}` {$columns}, ALGORITHM=INPLACE, LOCK=NONE");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('cron_execution_logs')) {
            return;
        }
        $existing = collect(DB::select('SHOW INDEX FROM `cron_execution_logs`'))->pluck('Key_name')->all();
        foreach (array_keys(self::INDEXES) as $name) {
            if (in_array($name, $existing, true)) {
                DB::statement("ALTER TABLE `cron_execution_logs` DROP INDEX `{$name}`");
            }
        }
    }
};
