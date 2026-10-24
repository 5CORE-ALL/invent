<?php

namespace App\Console\Commands;

use App\Services\DynamicMessages\DynamicTaskEngine;
use Illuminate\Console\Command;

class RunDynamicTasks extends Command
{
    protected $signature = 'dynamic-tasks:run {--dry-run : Show what would be created without creating tasks}';

    protected $description = 'Evaluate active dynamic task rules and create the pre-saved tasks for the saved users (assignor: Amarjit)';

    public function handle(DynamicTaskEngine $engine): int
    {
        $dry = (bool) $this->option('dry-run');
        $results = $engine->runAll($dry);

        foreach ($results as $ruleId => $r) {
            if (isset($r['error'])) {
                $this->error("Rule #{$ruleId}: {$r['error']}");

                continue;
            }
            $this->line(sprintf(
                'Rule #%d: %s%d created, %d duplicate, %d still open, %d condition not met',
                $ruleId,
                $dry ? '[dry-run] would match '.count($r['matches']).', ' : '',
                $r['created'],
                $r['skipped_duplicates'],
                $r['skipped_open'],
                $r['no_match']
            ));
        }
        $this->info('Done. Rules processed: '.count($results));

        return self::SUCCESS;
    }
}
