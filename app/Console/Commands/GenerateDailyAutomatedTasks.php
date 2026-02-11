<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class GenerateDailyAutomatedTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:generate-daily-automated';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate daily automated tasks from automated_tasks table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting automated task generation...');
        
        try {
            // Use Asia/Kolkata timezone explicitly
            $now = Carbon::now('Asia/Kolkata');
            $today = $now->toDateString();
            
            $this->info("Current date: {$today} {$now->format('H:i:s')}");
            $generated = 0;
            $skipped = 0;

            // Fetch automated tasks in chunks for better performance
            DB::table('automate_tasks')
                ->where('schedule_type', 'daily')
                ->where('is_pause', 0)
                ->whereNotIn('status', ['Done', 'Archived'])
                ->orderBy('id')
                ->chunk(100, function ($automatedTasks) use ($now, $today, &$generated, &$skipped) {
                    foreach ($automatedTasks as $autoTask) {
                        try {
                            // Check for duplicate - prevent creating same task twice today
                            $alreadyExists = DB::table('tasks')
                                ->where('automate_task_id', $autoTask->id)
                                ->whereDate('start_date', $today)
                                ->exists();

                            if ($alreadyExists) {
                                $skipped++;
                                $this->warn("⊘ Skipped (already created today): {$autoTask->title}");
                                continue;
                            }

                            // Parse schedule_time (e.g., "20:30:00")
                            $scheduleTime = $autoTask->schedule_time ?? '00:00:00';
                            $timeParts = explode(':', $scheduleTime);
                            
                            // Create start_date and due_date = today + schedule_time (using Asia/Kolkata timezone)
                            $startDate = Carbon::today('Asia/Kolkata')
                                ->setTime((int)$timeParts[0], (int)$timeParts[1], (int)$timeParts[2]);
                            
                            // Due date is same as start date for automated tasks
                            $dueDate = $startDate->copy();
                            
                            $this->info("Creating task: start_date={$startDate->format('Y-m-d H:i:s')}, title_date={$now->format('d-M-y')}");

                            // Prepare task data
                            $taskData = [
                                'task_id' => null,
                                'title' => $autoTask->title,
                                'group' => $autoTask->group,
                                'priority' => $autoTask->priority,
                                'description' => $autoTask->description,
                                'eta_time' => $autoTask->eta_time ?? 0,
                                'etc_done' => 0,
                                'is_missed' => 0,
                                'is_missed_track' => 0,
                                'is_automate_task' => 1,
                                'completion_date' => $dueDate,
                                'completion_day' => 0,
                                'start_date' => $startDate,
                                'due_date' => $dueDate,
                                'split_tasks' => $autoTask->split_tasks ?? 0,
                                'assign_to' => $autoTask->assign_to,
                                'assignor' => $autoTask->assignor,
                                'link1' => $autoTask->link1,
                                'link2' => $autoTask->link2 ?? null,
                                'link3' => $autoTask->link3 ?? null,
                                'link4' => $autoTask->link4 ?? null,
                                'link5' => $autoTask->link5 ?? null,
                                'link6' => $autoTask->link6 ?? null,
                                'link7' => $autoTask->link7 ?? null,
                                'link8' => null,
                                'link9' => null,
                                'image' => null,
                                'automate_task_id' => $autoTask->id,
                                'task_type' => 'automate_task',
                                'schedule_type' => 'daily',
                                'schedule_time' => $autoTask->schedule_time,
                                'status' => 'Todo',
                                'rework_reason' => null,
                                'delete_rating' => 0,
                                'delete_feedback' => null,
                                'order' => $autoTask->order ?? 0,
                                'workspace' => $autoTask->workspace ?? 0,
                                'is_data_from' => 0,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];

                            // Insert the task
                            DB::table('tasks')->insert($taskData);
                            
                            $generated++;
                            $this->info("✓ Generated: {$autoTask->title}");

                        } catch (Exception $e) {
                            $this->error("Error processing task ID {$autoTask->id}: {$e->getMessage()}");
                            Log::error("Generate automated task failed", [
                                'automate_task_id' => $autoTask->id,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                        }
                    }
                });

            $this->info("✅ Completed! Generated: {$generated}, Skipped: {$skipped}");
            
            Log::info("Daily automated tasks generation completed", [
                'generated' => $generated,
                'skipped' => $skipped,
                'timestamp' => $now->toDateTimeString()
            ]);

            return 0;

        } catch (Exception $e) {
            $this->error("Fatal error: {$e->getMessage()}");
            Log::error("Generate automated tasks command failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}
