<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Diagnostic: list daily automated tasks and whether a task instance exists for today.
 * Run: php artisan tasks:check-daily-automated
 * - If a task shows "MISSING", the generator is skipping it but no instance exists (duplicate-check bug or timezone).
 * - If a task shows "EXISTS", the instance was created; if user doesn't see it, check visibility (assignee/assignor or main Tasks list).
 */
class CheckDailyAutomatedTasks extends Command
{
    protected $signature = 'tasks:check-daily-automated';
    protected $description = 'Check which daily automated tasks have (or lack) a task instance for today';

    public function handle()
    {
        $now = Carbon::now('Asia/Kolkata');
        $today = $now->toDateString();
        $dayStart = Carbon::today('Asia/Kolkata')->startOfDay();
        $dayEnd = Carbon::today('Asia/Kolkata')->endOfDay();

        try {
            DB::statement("SET time_zone = '+05:30'");
        } catch (\Throwable $e) {
            // continue
        }

        $this->info("Today (Asia/Kolkata): {$today}");
        $this->info("Checking daily automate_tasks for an existing task with start_date between {$dayStart->toDateTimeString()} and {$dayEnd->toDateTimeString()}");
        $this->newLine();

        $automateTasks = DB::table('automate_tasks')
            ->where('schedule_type', 'daily')
            ->where('is_pause', 0)
            ->whereNotIn('status', ['Done', 'Archived'])
            ->orderBy('id')
            ->get();

        $missing = [];
        $exists = [];

        foreach ($automateTasks as $autoTask) {
            $existsToday = DB::table('tasks')
                ->where('automate_task_id', $autoTask->id)
                ->whereBetween('start_date', [$dayStart, $dayEnd])
                ->whereNull('deleted_at')
                ->exists();

            if ($existsToday) {
                $exists[] = $autoTask->title;
            } else {
                $missing[] = ['id' => $autoTask->id, 'title' => $autoTask->title, 'assign_to' => $autoTask->assign_to ?? ''];
            }
        }

        if (count($missing) > 0) {
            $this->warn('MISSING (no task instance for today – these are NOT being created):');
            foreach ($missing as $m) {
                $this->line("  [{$m['id']}] {$m['title']}" . ($m['assign_to'] ? " → {$m['assign_to']}" : ' (no assignee)'));
            }
            $this->newLine();
        }

        $this->info('EXISTS (task instance for today found): ' . count($exists));
        if (count($exists) > 0 && $this->option('verbose')) {
            foreach ($exists as $t) {
                $this->line("  {$t}");
            }
        }

        $this->newLine();
        $this->info('Summary: ' . count($missing) . ' missing, ' . count($exists) . ' already have an instance for today.');
        if (count($missing) > 0) {
            $this->warn('Fix: run tasks:generate-daily-automated again after ensuring duplicate check uses Asia/Kolkata (see GenerateDailyAutomatedTasks).');
        }

        return 0;
    }
}
