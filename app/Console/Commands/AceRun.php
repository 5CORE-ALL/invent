<?php

namespace App\Console\Commands;

use App\Support\Ace\AceEngine;
use Illuminate\Console\Command;

class AceRun extends Command
{
    protected $signature = 'ace:run {--id= : Only run this ACE rule id}';

    protected $description = 'Run the daily ATE (Auto Task Event) check: column rules and server-checked badge rules create a task only when the rule applies';

    public function handle(): int
    {
        $id = $this->option('id') ? (int) $this->option('id') : null;
        $created = AceEngine::runColumnRules($id) + AceEngine::runBadgeRules($id);
        $this->info("ACE: created {$created} task(s).");

        return self::SUCCESS;
    }
}
