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
    private const FIXED_DAILY_TIME = '12:01:00';

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
     * Daily task is skipped if: schedule_type != 'daily', status in (Done, Archived),
     * or a task for this automate_task_id was already created today (same day in Asia/Kolkata).
     * MySQL GET_LOCK stops two servers / overlapping runs from inserting twice the same day.
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
                ->whereRaw('LOWER(schedule_type) = ?', ['daily'])
                ->whereNotIn('status', ['Done', 'Archived'])
                ->orderBy('id')
                ->chunk(100, function ($automatedTasks) use ($now, $today, $taskWhatsApp, &$generated, &$skipped) {
                    foreach ($automatedTasks as $autoTask) {
                        try {
                            $dayStart = Carbon::today('Asia/Kolkata')->startOfDay();
                            $dayEnd = Carbon::today('Asia/Kolkata')->endOfDay();
                            $dayStartStr = $dayStart->format('Y-m-d H:i:s');
                            $dayEndStr = $dayEnd->format('Y-m-d H:i:s');

                            $lockKey = $this->dailyGenerationLockName((int) $autoTask->id, $today);
                            $lockHeld = false;
                            if (DB::getDriverName() === 'mysql') {
                                $lockRow = DB::selectOne('SELECT GET_LOCK(?, 30) AS acquired', [$lockKey]);
                                $acquired = isset($lockRow->acquired) ? (int) $lockRow->acquired : 0;
                                if ($acquired !== 1) {
                                    $skipped++;
                                    $this->warn("⊘ Skipped (lock busy, try next run): {$autoTask->title}");

                                    continue;
                                }
                                $lockHeld = true;
                            }

                            try {
                                // Ek din = ek instance: start_date ya created_at aaj IST window mein ho to dubara mat banao.
                                $alreadyExists = DB::table('tasks')
                                    ->where('automate_task_id', $autoTask->id)
                                    ->whereNull('deleted_at')
                                    ->where(function ($q) use ($dayStartStr, $dayEndStr) {
                                        $q->whereBetween('start_date', [$dayStartStr, $dayEndStr])
                                            ->orWhereBetween('created_at', [$dayStartStr, $dayEndStr]);
                                    })
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

                            // Daily automation uses a single fixed time for consistency across all templates.
                            $scheduleTime = self::FIXED_DAILY_TIME;
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

                            // Prepare task data
                            $taskData = [
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
                                'schedule_time' => self::FIXED_DAILY_TIME,
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
                            $taskId = DB::table('tasks')->insertGetId($taskData);
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

                            } finally {
                                if ($lockHeld) {
                                    DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockKey]);
                                }
                            }

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

    /** MySQL GET_LOCK naam (max 64 chars): har template + din. */
    private function dailyGenerationLockName(int $automateTaskId, string $todayYmd): string
    {
        $day = str_replace('-', '', $todayYmd);

        return substr('inv_da_' . $automateTaskId . '_' . $day, 0, 64);
    }
}
