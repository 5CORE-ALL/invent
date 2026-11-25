<?php

namespace App\Console\Commands;

use App\Services\Tasks\TaskOverdueHistoryService;
use Illuminate\Console\Command;

class SnapshotTaskOverdueHistory extends Command
{
    protected $signature = 'tasks:snapshot-overdue';

    protected $description = 'Save every team member\'s overdue task count for today (feeds the O-Due history graph on /tasks/summary)';

    public function handle(TaskOverdueHistoryService $history): int
    {
        $r = $history->snapshot();
        $this->info("Saved overdue snapshot for {$r['members']} member(s); {$r['overdue_total']} overdue task assignment(s) in total.");

        return self::SUCCESS;
    }
}
