<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\TaskWhatsAppNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExecuteAutomatedTasks extends Command
{
    protected $signature = 'tasks:execute-automated';
    protected $description = 'Execute automated tasks based on their schedule';

    public function handle(TaskWhatsAppNotificationService $taskWhatsApp)
    {
        $this->info('Checking for automated tasks to execute...');
        $this->line('Note: daily automated tasks are handled by tasks:generate-daily-automated.');

        $now = Carbon::now('Asia/Kolkata');
        $currentTime = $now->format('H:i');
        $currentDay = strtolower($now->format('D')); // mon, tue, etc.
        $currentDate = $now->format('j'); // 1-31
        $dayStart = $now->copy()->startOfDay();
        $dayEnd = $now->copy()->endOfDay();

        // Keep DB comparisons in same timezone as scheduling logic.
        try {
            DB::statement("SET time_zone = '+05:30'");
        } catch (\Throwable $e) {
            Log::warning('Could not set session time_zone to Asia/Kolkata: ' . $e->getMessage());
        }

        // Get all active automated tasks (weekly/monthly; daily are handled by generate-daily-automated)
        $automatedTasks = DB::table('automate_tasks')
            ->whereNotIn('status', ['Done', 'Archived'])
            ->get();
        
        $executed = 0;
        
        foreach ($automatedTasks as $task) {
            $shouldRun = false;
            $scheduleType = strtolower((string) ($task->schedule_type ?? ''));
            
            // Check if it's time to run based on schedule_type.
            // Daily tasks are created by tasks:generate-daily-automated; skip here to avoid duplicates.
            switch ($scheduleType) {
                case 'daily':
                    $shouldRun = false;
                    break;

                case 'weekly':
                    // Run on specific days of week (schedule_days can be "Mon,Tue" or "Monday,Tuesday")
                    if ($task->schedule_days && $task->schedule_time) {
                        $scheduledDays = array_map(function ($d) {
                            $d = strtolower(trim($d));
                            return strlen($d) >= 3 ? substr($d, 0, 3) : $d; // mon, tue, etc.
                        }, explode(',', $task->schedule_days));
                        $taskTime = Carbon::parse($task->schedule_time)->format('H:i');
                        // Use >= to recover missed exact-minute runs if scheduler is delayed.
                        $shouldRun = in_array($currentDay, $scheduledDays, true) && ($currentTime >= $taskTime);
                    }
                    break;
                    
                case 'monthly':
                    // Run on specific dates of month (schedule_days e.g. "1,15,EOM")
                    if ($task->schedule_days && $task->schedule_time) {
                        $scheduledDates = array_map(function ($d) {
                            $d = strtoupper(trim($d));
                            if ($d !== 'EOM') {
                                $d = ltrim($d, '0');
                            }
                            return $d;
                        }, explode(',', $task->schedule_days));
                        $taskTime = Carbon::parse($task->schedule_time)->format('H:i');
                        $lastDayNum = (int) $now->copy()->endOfMonth()->format('j');
                        $isEndOfMonth = ((int) $currentDate) === $lastDayNum;

                        // Use >= to recover missed exact-minute runs if scheduler is delayed.
                        $shouldRun = (in_array((string) $currentDate, $scheduledDates, true) || (in_array('EOM', $scheduledDates, true) && $isEndOfMonth))
                                     && ($currentTime >= $taskTime);
                    }
                    break;
            }
            
            if ($shouldRun) {
                // Check if task was already created today (prevent duplicates)
                $alreadyCreatedToday = DB::table('tasks')
                    ->where('automate_task_id', $task->id)
                    ->whereBetween('start_date', [$dayStart, $dayEnd])
                    ->whereNull('deleted_at')
                    ->exists();
                
                if ($alreadyCreatedToday) {
                    $this->info("⊘ Skipped (already created today): {$task->title}");
                    continue;
                }

                // Ensure task is always assigned: if no assignee, assign to assignor so user receives the task
                $assignTo = trim((string)($task->assign_to ?? ''));
                if ($assignTo === '') {
                    $assignTo = trim((string)($task->assignor ?? ''));
                    if ($assignTo !== '') {
                        Log::info('Automated task had no assignee; assigned to assignor', [
                            'automate_task_id' => $task->id,
                            'title' => $task->title,
                            'assign_to' => $assignTo,
                        ]);
                    }
                }
                
                // Keep task timing anchored to configured schedule_time (not command runtime).
                $scheduleTime = $task->schedule_time ?? '00:00:00';
                $timeParts = array_map('intval', explode(':', (string) $scheduleTime));
                $hour = $timeParts[0] ?? 0;
                $minute = $timeParts[1] ?? 0;
                $second = $timeParts[2] ?? 0;
                $startDate = Carbon::today('Asia/Kolkata')->setTime($hour, $minute, $second);

                // For automated tasks: due_date = start_date + 5 days (standard completion window)
                $dueDate = $startDate->copy()->addDays(5);
                $completionDate = $dueDate->copy();

                $taskData = [
                    'title' => $task->title . ' [Auto: ' . $now->format('d-M-y') . ']',
                    'group' => $task->group,
                    'priority' => $task->priority,
                    'description' => $task->description,
                    'assignor' => $task->assignor,
                    'assign_to' => $assignTo ?: null,
                    'eta_time' => $task->eta_time,
                    'start_date' => $startDate,
                    'due_date' => $dueDate,
                    'completion_date' => $completionDate,
                    'status' => 'Todo',
                    'is_automate_task' => 1,
                    'automate_task_id' => $task->id,
                    'task_type' => 'automate_task',
                    'schedule_type' => $task->schedule_type,
                    'schedule_time' => $task->schedule_time,
                    'link1' => $task->link1 ?? '',
                    'link2' => $task->link2 ?? '',
                    'link3' => $task->link3 ?? '',
                    'link4' => $task->link4 ?? '',
                    'link5' => $task->link5 ?? '',
                    'link6' => $task->link6 ?? '',
                    'link7' => $task->link7 ?? '',
                    'link8' => '',
                    'link9' => '',
                    'split_tasks' => $task->split_tasks ?? 0,
                    'workspace' => $task->workspace ?? 0,
                    'order' => $task->order ?? 0,
                    'task_id' => '',
                    'rework_reason' => '',
                    'delete_rating' => 0,
                    'delete_feedback' => '',
                    'completion_day' => 0,
                    'etc_done' => 0,
                    'is_missed' => 0,
                    'is_missed_track' => 0,
                    'is_data_from' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $taskId = DB::table('tasks')->insertGetId($taskData);
                $taskInstance = Task::find($taskId);

                if ($taskInstance && $assignTo) {
                    try {
                        $taskWhatsApp->notifyNewTaskAssigned($taskInstance);
                    } catch (\Throwable $e) {
                        Log::warning('Task WhatsApp notify new assigned (automated) failed: ' . $e->getMessage(), [
                            'task_id' => $taskId,
                            'assign_to' => $assignTo,
                        ]);
                    }
                }

                $executed++;
                $this->info("✓ Executed: {$task->title}");
            }
        }
        
        $this->info("Completed! {$executed} task(s) executed.");
        return 0;
    }
}
