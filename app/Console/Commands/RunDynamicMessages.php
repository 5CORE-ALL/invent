<?php

namespace App\Console\Commands;

use App\Services\DynamicMessages\DynamicMessageEngine;
use Illuminate\Console\Command;

class RunDynamicMessages extends Command
{
    protected $signature = 'dynamic-messages:run {--dry-run : Show what would be sent without sending}';

    protected $description = 'Evaluate active dynamic message rules and deliver pre-saved messages to the saved users';

    public function handle(DynamicMessageEngine $engine): int
    {
        $dry = (bool) $this->option('dry-run');
        $results = $engine->runAll($dry);

        foreach ($results as $ruleId => $r) {
            if (isset($r['error'])) {
                $this->error("Rule #{$ruleId}: {$r['error']}");

                continue;
            }
            $this->line(sprintf(
                'Rule #%d: %s%d created, %d duplicate, %d condition not met',
                $ruleId,
                $dry ? '[dry-run] would match '.count($r['matches']).', ' : '',
                $r['created'],
                $r['skipped_duplicates'],
                $r['no_match']
            ));
        }
        $this->info('Done. Rules processed: '.count($results));

        return self::SUCCESS;
    }
}
