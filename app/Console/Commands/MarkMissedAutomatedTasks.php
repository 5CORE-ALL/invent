<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Legacy wrapper around tasks:expire-missed-automated.
 *
 * The unified command now marks missed automated tasks AND deletes them, using
 * PST cutoffs for daily, weekly and monthly schedules. This command is kept so
 * existing schedules and manual calls continue to work.
 */
class MarkMissedAutomatedTasks extends Command
{
    protected $signature = 'tasks:mark-missed-automated';

    protected $description = 'Legacy wrapper: mark and delete missed automated tasks using unified PST cutoff rules';

    public function handle(): int
    {
        return $this->call('tasks:expire-missed-automated');
    }
}
