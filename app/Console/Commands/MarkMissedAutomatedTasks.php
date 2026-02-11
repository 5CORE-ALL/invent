<?php

namespace App\Console\Commands;

use Carbon\Carbon;
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
    protected $description = 'Mark automated tasks as missed if they are past due date and not completed';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for missed automated tasks...');
        
        try {
            $now = Carbon::now();
            $marked = 0;

            // Find and mark missed tasks in chunks
            DB::table('tasks')
                ->where('is_automate_task', 1)
                ->where('is_missed', 0)
                ->where('due_date', '<', $now)
                ->whereNotIn('status', ['Done', 'Archived', 'Missed'])
                ->orderBy('id')
                ->chunk(100, function ($tasks) use ($now, &$marked) {
                    foreach ($tasks as $task) {
                        try {
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
                            
                            $daysLate = Carbon::parse($task->due_date)->diffInDays($now);
                            $this->warn("✗ Marked as missed ({$daysLate} days late): {$task->title}");

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
