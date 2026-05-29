<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Support\TaskBusinessTime;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Auto-expire daily automated tasks that were not completed within their missed window.
 *
 * A daily automate-task instance becomes missed 24 hours after its generated start time
 * (start_date), in the business timezone (see config tasks.missed_after_hours.daily). When the
 * window elapses and status is not Done/Archived: mark missed, archive to deleted_tasks, soft-delete.
 *
 * Scheduled hourly in {@see config('tasks.business_timezone')} — see Kernel. Manual trigger:
 * {@see \App\Http\Controllers\TaskController::expireDailyAutomatedTasks()}.
 */
class ExpireDailyAutomatedTasks extends Command
{
    protected $signature = 'tasks:expire-daily-automated {--dry-run : Show what would be expired without changing anything}';

    protected $description = 'Auto-delete daily automated tasks not completed the same day and count them as Missed';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->info('Expiring incomplete daily automated tasks' . ($dryRun ? ' [DRY RUN]' : '') . '...');

        try {
            TaskBusinessTime::applyDatabaseSession();

            $now = TaskBusinessTime::now();
            // Daily auto-task is missed 24h (configurable) after its generated start time.
            $cutoff = $now->copy()->subHours(TaskBusinessTime::missedAfterHours('daily'));

            $expired = 0;
            $archived = 0;
            $failed = 0;

            // STRICT: only daily automated tasks. Weekly/monthly auto-tasks share is_automate_task=1
            // and task_type='automate_task', so the LOWER(schedule_type)='daily' check is the ONLY
            // safe way to exclude them. See App\Console\Commands\ExecuteAutomatedTasks for how
            // weekly/monthly instances are created with their own schedule_type.
            // Normal/manual tasks (is_automate_task = 0) are NEVER affected.
            Task::query()
                ->where('is_automate_task', 1)
                ->whereRaw('LOWER(schedule_type) = ?', ['daily'])
                ->whereNotNull('start_date')
                ->where('start_date', '<=', $cutoff)
                ->whereNotIn('status', ['Done', 'Archived'])
                ->orderBy('id')
                ->chunkById(100, function ($tasks) use ($now, $dryRun, &$expired, &$archived, &$failed) {
                    foreach ($tasks as $task) {
                        try {
                            $startedAt = $task->start_date instanceof \DateTimeInterface
                                ? Carbon::parse($task->start_date)->format('Y-m-d')
                                : (string) $task->start_date;
                            $this->warn("✗ Expiring (missed, started {$startedAt}): #{$task->id} {$task->title}");

                            if ($dryRun) {
                                $expired++;

                                continue;
                            }

                            DB::transaction(function () use ($task, $now, &$archived) {
                                DB::table('tasks')
                                    ->where('id', $task->id)
                                    ->update([
                                        'is_missed' => 1,
                                        'is_missed_track' => 1,
                                        'status' => 'Missed',
                                        'updated_at' => $now,
                                    ]);

                                $task->refresh();

                                if ($this->archiveToDeletedTasks($task, $now)) {
                                    $archived++;
                                }

                                $task->delete();
                            });

                            $expired++;
                        } catch (Exception $e) {
                            $failed++;
                            $this->error("Failed to expire task ID {$task->id}: {$e->getMessage()}");
                            Log::error('ExpireDailyAutomatedTasks: per-task failure', [
                                'task_id' => $task->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                });

            $this->info("✅ Done. Expired: {$expired}, Archived: {$archived}, Failed: {$failed}");

            Log::info('ExpireDailyAutomatedTasks completed', [
                'expired' => $expired,
                'archived' => $archived,
                'failed' => $failed,
                'dry_run' => $dryRun,
                'timestamp' => $now->toDateTimeString(),
            ]);

            return 0;
        } catch (Exception $e) {
            $this->error("Fatal error: {$e->getMessage()}");
            Log::error('ExpireDailyAutomatedTasks command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    /**
     * Best-effort copy of saveDeletedTask logic from TaskController, adapted for system use (no Auth).
     */
    private function archiveToDeletedTasks(Task $task, Carbon $now): bool
    {
        try {
            $str = function ($v, $max = 255) {
                if ($v === null || $v === '') {
                    return null;
                }
                $s = (string) $v;

                return strlen($s) > $max ? substr($s, 0, $max) : $s;
            };
            $date = function ($v) {
                if ($v === null || $v === '') {
                    return null;
                }
                if ($v instanceof \DateTimeInterface) {
                    return $v->format('Y-m-d H:i:s');
                }

                return (string) $v;
            };

            $assignToRaw = $task->assign_to;
            $assignorUser = ! empty($task->assignor) ? User::where('email', $task->assignor)->first() : null;
            $firstAssignee = ! empty($assignToRaw) ? trim(explode(',', (string) $assignToRaw)[0]) : null;
            $assigneeUser = $firstAssignee ? User::where('email', $firstAssignee)->first() : null;

            $splitTasks = $task->split_tasks;
            if (! is_numeric($splitTasks)) {
                $splitTasks = $splitTasks ? 1 : 0;
            }

            $nowStr = $now->format('Y-m-d H:i:s');
            $row = [
                'original_task_id' => (int) $task->id,
                'title' => $str($task->title ?? '', 255),
                'description' => $task->description !== null ? $str((string) $task->description, 65535) : null,
                'group' => $str($task->group),
                'priority' => $str($task->priority),
                'status' => 'Missed',
                'assignor' => $str($task->assignor),
                'assign_to' => $str($assignToRaw),
                'assignor_name' => $str($assignorUser ? $assignorUser->name : $task->assignor),
                'assignee_name' => $str($assigneeUser ? $assigneeUser->name : $assignToRaw),
                'eta_time' => $task->eta_time !== null && $task->eta_time !== '' ? (int) $task->eta_time : null,
                'etc_done' => $task->etc_done !== null && $task->etc_done !== '' ? (int) $task->etc_done : null,
                'start_date' => $date($task->start_date),
                'completion_date' => $date($task->completion_date),
                'completion_day' => $task->completion_day !== null && $task->completion_day !== '' ? (int) $task->completion_day : null,
                'split_tasks' => (int) $splitTasks,
                'is_missed' => 1,
                'is_missed_track' => 1,
                'link1' => $str($task->link1),
                'link2' => $str($task->link2),
                'link3' => $str($task->link3),
                'link4' => $str($task->link4),
                'link5' => $str($task->link5),
                'link6' => $str($task->link6),
                'link7' => $str($task->link7),
                'link8' => $str($task->link8),
                'link9' => $str($task->link9),
                'image' => $str($task->image),
                'task_type' => $str($task->task_type),
                'rework_reason' => $task->rework_reason !== null ? $str((string) $task->rework_reason, 65535) : null,
                'deleted_by_email' => 'system@auto',
                'deleted_by_name' => 'Auto Expire (Daily)',
                'deleted_at' => $nowStr,
                'created_at' => $nowStr,
                'updated_at' => $nowStr,
            ];

            try {
                DB::table('deleted_tasks')->insert($row);

                return true;
            } catch (\Throwable $e) {
                $minimal = [
                    'original_task_id' => (int) $task->id,
                    'title' => $str($task->title ?? '', 255),
                    'assignor' => $str($task->assignor),
                    'assign_to' => $str($assignToRaw),
                    'status' => 'Missed',
                    'is_missed' => 1,
                    'is_missed_track' => 1,
                    'deleted_by_email' => 'system@auto',
                    'deleted_by_name' => 'Auto Expire (Daily)',
                    'deleted_at' => $nowStr,
                    'created_at' => $nowStr,
                    'updated_at' => $nowStr,
                ];
                DB::table('deleted_tasks')->insert($minimal);

                return true;
            }
        } catch (\Throwable $e) {
            Log::warning('ExpireDailyAutomatedTasks: archive failed', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
