<?php

namespace App\Console\Commands;

use App\Support\Ace\AceEngine;
use Illuminate\Console\Command;

class AceRun extends Command
{
    protected $signature = 'ace:run {--id= : Only run this ACE rule id}';

    protected $description = 'Run active ATE (Auto Task Event) column rules and create their one-time tasks';

    public function handle(): int
    {
        $id = $this->option('id') ? (int) $this->option('id') : null;
        $created = AceEngine::runColumnRules($id);
        $this->info("ACE: created {$created} one-time task(s).");

        return self::SUCCESS;
    }
}
