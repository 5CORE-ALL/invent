<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Legacy wrapper around tasks:expire-missed-automated.
 *
 * The unified missed-task expiration now handles daily, weekly and monthly
 * automated tasks using PST cutoffs. This command is kept so existing manual
 * triggers and routes continue to work.
 */
class ExpireDailyAutomatedTasks extends Command
{
    protected $signature = 'tasks:expire-daily-automated {--dry-run : Show what would be expired without changing anything}';

    protected $description = 'Legacy wrapper: auto-delete missed automated tasks using unified PST cutoff rules';

    public function handle(): int
    {
        return $this->call('tasks:expire-missed-automated', [
            '--dry-run' => (bool) $this->option('dry-run'),
        ]);
    }
}
