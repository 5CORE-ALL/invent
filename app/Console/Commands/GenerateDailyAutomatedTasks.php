<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\TaskWhatsAppNotificationService;
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
     * Daily task is skipped if: schedule_type != 'daily', is_pause = 1, status in (Done, Archived),
     * or a task for this automate_task_id was already created today (same day in Asia/Kolkata).
     */
    public function handle(TaskWhatsAppNotificationService $taskWhatsApp)
    {
        $this->info('Starting automated task generation...');
        
        try {
            // Use Asia/Kolkata timezone explicitly
            $now = Carbon::now('Asia/Kolkata');
            $today = $now->toDateString();

            // Ensure duplicate check and stored datetimes are compared in same timezone (Asia/Kolkata)
            try {
                DB::statement("SET time_zone = '+05:30'");
            } catch (\Throwable $e) {
                Log::warning('Could not set session time_zone to Asia/Kolkata: ' . $e->getMessage());
            }

            $this->info("Current date: {$today} {$now->format('H:i:s')}");
            $generated = 0;
            $skipped = 0;

            // Fetch automated tasks in chunks for better performance
            DB::table('automate_tasks')
                ->where('schedule_type', 'daily')
                ->where('is_pause', 0)
                ->whereNotIn('status', ['Done', 'Archived'])
                ->orderBy('id')
                ->chunk(100, function ($automatedTasks) use ($now, $today, $taskWhatsApp, &$generated, &$skipped) {
                    foreach ($automatedTasks as $autoTask) {
                        try {
                            // Check for duplicate - prevent creating same task twice today (use Asia/Kolkata day window)
                            $dayStart = Carbon::today('Asia/Kolkata')->startOfDay();
                            $dayEnd = Carbon::today('Asia/Kolkata')->endOfDay();
                            $alreadyExists = DB::table('tasks')
                                ->where('automate_task_id', $autoTask->id)
                                ->whereBetween('start_date', [$dayStart, $dayEnd])
                                ->whereNull('deleted_at')
                                ->exists();

                            if ($alreadyExists) {
                                $skipped++;
                                $this->warn("⊘ Skipped (already created today): {$autoTask->title}");
                                Log::debug('Automated task skipped: already created today', [
                                    'automate_task_id' => $autoTask->id,
                                    'title' => $autoTask->title,
                                    'today' => $today,
                                ]);
                                continue;
                            }

                            // Parse schedule_time (e.g. "20:30:00" or "12:01")
                            $scheduleTime = $autoTask->schedule_time ?? '00:00:00';
                            $timeParts = array_map('intval', explode(':', $scheduleTime));
                            $h = $timeParts[0] ?? 0;
                            $m = $timeParts[1] ?? 0;
                            $s = $timeParts[2] ?? 0;
                            
                            // Create start_date and due_date = today + schedule_time (using Asia/Kolkata timezone)
                            $startDate = Carbon::today('Asia/Kolkata')->setTime($h, $m, $s);
                            $dueDate = $startDate->copy()->addDays(5);
                            
                            $this->info("Creating task: start_date={$startDate->format('Y-m-d H:i:s')}, title_date={$now->format('d-M-y')}");

                            // Ensure task is always assigned: if no assignee, assign to assignor so user receives the task
                            $assignTo = trim((string)($autoTask->assign_to ?? ''));
                            if ($assignTo === '') {
                                $assignTo = trim((string)($autoTask->assignor ?? ''));
                                if ($assignTo !== '') {
                                    Log::info('Automated task had no assignee; assigned to assignor', [
                                        'automate_task_id' => $autoTask->id,
                                        'title' => $autoTask->title,
                                        'assign_to' => $assignTo,
                                    ]);
                                }
                            }

                            // Ensure id is set if table id is not AUTO_INCREMENT (avoids "Field 'id' doesn't have a default value")
                            $nextId = (int) DB::table('tasks')->max('id') + 1;
                            if ($nextId <= 0) {
                                $nextId = 1;
                            }

                            // Prepare task data
                            $taskData = [
                                'id' => $nextId,
                                'task_id' => null,
                                'title' => $autoTask->title . ' [Auto: ' . $now->format('d-M-y') . ']',
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
                                'assign_to' => $assignTo ?: null,
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
                            $taskId = $nextId;
                            $taskInstance = Task::find($taskId);

                            if ($taskInstance && $assignTo) {
                                try {
                                    $taskWhatsApp->notifyNewTaskAssigned($taskInstance);
                                } catch (\Throwable $e) {
                                    Log::warning('Task WhatsApp notify new assigned (daily automated) failed: ' . $e->getMessage(), [
                                        'task_id' => $taskId,
                                        'assign_to' => $assignTo,
                                    ]);
                                }
                            }

                            $generated++;
                            $this->info("✓ Generated: {$autoTask->title}" . ($assignTo ? " → assigned to {$assignTo}" : " (no assignee)"));

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
