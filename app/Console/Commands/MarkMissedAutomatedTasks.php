<?php

namespace App\Console\Commands;

use App\Support\TaskBusinessTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class MarkMissedAutomatedTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:mark-missed-automated';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark WEEKLY/MONTHLY automated tasks missed once their generated-time window elapses (weekly 144h, monthly 720h). Daily auto-tasks are handled by tasks:expire-daily-automated. Never touches normal tasks.';

    /**
     * Execute the console command.
     *
     * Only AUTO-GENERATED weekly/monthly instances are considered. A task becomes
     * missed when now >= start_date (generated time) + window for its schedule_type.
     * Daily auto-tasks are expired separately; normal/manual tasks are never affected.
     */
    public function handle()
    {
        $this->info('Checking for missed automated (weekly/monthly) tasks...');
        
        try {
            TaskBusinessTime::applyDatabaseSession();
            $now = TaskBusinessTime::now();
            $marked = 0;

            // Find and mark missed tasks in chunks.
            // STRICT scope: only automated weekly/monthly instances (is_automate_task = 1).
            DB::table('tasks')
                ->where('is_automate_task', 1)
                ->where('is_missed', 0)
                ->whereNull('deleted_at')
                ->whereNotNull('start_date')
                ->whereRaw("LOWER(schedule_type) IN ('weekly', 'monthly')")
                ->whereNotIn('status', ['Done', 'Archived', 'Missed'])
                ->orderBy('id')
                ->chunk(100, function ($tasks) use ($now, &$marked) {
                    foreach ($tasks as $task) {
                        try {
                            // Missed only once the per-type window (from generated start time) has elapsed.
                            $missedAt = TaskBusinessTime::missedAtFor($task->start_date, $task->schedule_type);
                            if ($missedAt === null || $now->lt($missedAt)) {
                                continue;
                            }

                            // Update task as missed
                            DB::table('tasks')
                                ->where('id', $task->id)
                                ->update([
                                    'is_missed' => 1,
                                    'is_missed_track' => 1,
                                    'status' => 'Missed',
                                    'updated_at' => $now
                                ]);

                            $marked++;

                            $hoursLate = $missedAt->diffInHours($now);
                            $this->warn("✗ Marked as missed ({$task->schedule_type}, {$hoursLate}h past window): {$task->title}");

                        } catch (Exception $e) {
                            $this->error("Error marking task ID {$task->id}: {$e->getMessage()}");
                            Log::error("Mark missed task failed", [
                                'task_id' => $task->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                });

            if ($marked > 0) {
                $this->info("✅ Marked {$marked} task(s) as missed");
                
                Log::info("Missed automated tasks marked", [
                    'count' => $marked,
                    'timestamp' => $now->toDateTimeString()
                ]);
            } else {
                $this->info("✓ No missed tasks found");
            }

            return 0;

        } catch (Exception $e) {
            $this->error("Fatal error: {$e->getMessage()}");
            Log::error("Mark missed tasks command failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}
