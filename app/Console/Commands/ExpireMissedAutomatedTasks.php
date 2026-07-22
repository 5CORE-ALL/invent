<?php

namespace App\Console\Commands;

use App\Models\AutomateTaskChecklistForm;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskBusinessTime;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Mark and soft-delete automated tasks whose checklist was not filled by the
 * scheduled PST cutoff. Daily tasks expire at 11:59 PM PST the same day;
 * weekly tasks expire 7 days later at 11:59 PM PST; monthly tasks expire on the
 * last day of the month at 11:59 PM PST. If a checklist is attached, the task's
 * report is updated with a red missed marker before deletion.
 */
class ExpireMissedAutomatedTasks extends Command
{
    protected $signature = 'tasks:expire-missed-automated {--dry-run : Show what would be expired without changing anything}';

    protected $description = 'Mark and delete automated tasks not completed by their scheduled PST cutoff';

    /** Report prefix used to identify missed checklist reports in the UI. */
    public const MISSED_REPORT_PREFIX = '[MISSED] Checklist not filled by scheduled time.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->info('Expiring incomplete automated tasks by PST cutoff' . ($dryRun ? ' [DRY RUN]' : '') . '...');

        try {
            TaskBusinessTime::applyDatabaseSession();

            $tz = (string) config('tasks.missed_task_timezone', 'America/Los_Angeles');
            $cutoffTime = (string) config('tasks.missed_task_cutoff_time', '23:59:00');
            $now = Carbon::now($tz);
            $hasReportColumn = Schema::hasColumn('deleted_tasks', 'report');

            $this->info("Current time ({$tz}): " . $now->toDateTimeString());

            $expired = 0;
            $archived = 0;
            $failed = 0;

            // Preload checklist forms so we can mark missed reports without N+1 queries.
            $checklistAutomateTaskIds = AutomateTaskChecklistForm::query()
                ->pluck('automate_task_id')
                ->map(fn ($id) => (int) $id)
                ->flip()
                ->all();

            Task::query()
                ->where('is_automate_task', 1)
                ->whereNull('deleted_at')
                ->whereNotNull('start_date')
                ->whereNotIn('status', ['Done', 'Archived', 'Missed'])
                ->orderBy('id')
                ->chunkById(100, function ($tasks) use ($now, $tz, $cutoffTime, $dryRun, $checklistAutomateTaskIds, &$expired, &$archived, &$failed) {
                    foreach ($tasks as $task) {
                        try {
                            $cutoff = $this->cutoffFor($task, $tz, $cutoffTime);
                            if ($cutoff === null || $now->lt($cutoff)) {
                                continue;
                            }

                            $startedAt = $task->start_date instanceof \DateTimeInterface
                                ? Carbon::parse($task->start_date)->format('Y-m-d')
                                : (string) $task->start_date;
                            $this->warn("✗ Expiring (missed {$task->schedule_type}, started {$startedAt}, cutoff {$cutoff->toDateTimeString()}): #{$task->id} {$task->title}");

                            if ($dryRun) {
                                $expired++;
                                continue;
                            }

                            $hasChecklist = isset($checklistAutomateTaskIds[(int) ($task->automate_task_id ?? 0)]);

                            DB::transaction(function () use ($task, $now, $hasChecklist, $hasReportColumn, &$archived) {
                                $report = $task->report;
                                if ($hasChecklist) {
                                    $report = $this->buildMissedChecklistReport($task, $now);
                                }

                                DB::table('tasks')
                                    ->where('id', $task->id)
                                    ->update([
                                        'is_missed' => 1,
                                        'is_missed_track' => 1,
                                        'status' => 'Missed',
                                        'report' => $report,
                                        'updated_at' => $now,
                                    ]);

                                $task->refresh();

                            if ($this->archiveToDeletedTasks($task, $now, $hasReportColumn)) {
                                $archived++;
                            }

                                $task->delete();
                            });

                            $expired++;
                        } catch (Exception $e) {
                            $failed++;
                            $this->error("Failed to expire task ID {$task->id}: {$e->getMessage()}");
                            Log::error('ExpireMissedAutomatedTasks: per-task failure', [
                                'task_id' => $task->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                });

            $this->info("✅ Done. Expired: {$expired}, Archived: {$archived}, Failed: {$failed}");

            Log::info('ExpireMissedAutomatedTasks completed', [
                'expired' => $expired,
                'archived' => $archived,
                'failed' => $failed,
                'dry_run' => $dryRun,
                'timezone' => $tz,
                'timestamp' => $now->toDateTimeString(),
            ]);

            return 0;
        } catch (Exception $e) {
            $this->error("Fatal error: {$e->getMessage()}");
            Log::error('ExpireMissedAutomatedTasks command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    /**
     * Calculate the PST cutoff for an automated task instance.
     *   daily   -> same day at cutoff_time
     *   weekly  -> start_date + 7 days at cutoff_time
     *   monthly -> last day of start_date's month at cutoff_time
     */
    private function cutoffFor(Task $task, string $tz, string $cutoffTime): ?Carbon
    {
        $scheduleType = strtolower(trim((string) ($task->schedule_type ?? '')));
        if (! in_array($scheduleType, ['daily', 'weekly', 'monthly'], true)) {
            return null;
        }

        try {
            $start = Carbon::parse($task->start_date)->setTimezone($tz);
        } catch (Exception $e) {
            Log::warning('ExpireMissedAutomatedTasks: invalid start_date', [
                'task_id' => $task->id,
                'start_date' => $task->start_date,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $parts = array_map('intval', explode(':', $cutoffTime));
        $hour = $parts[0] ?? 23;
        $minute = $parts[1] ?? 59;
        $second = $parts[2] ?? 0;

        return match ($scheduleType) {
            'daily' => $start->copy()->setTime($hour, $minute, $second),
            'weekly' => $start->copy()->addDays(7)->setTime($hour, $minute, $second),
            'monthly' => $start->copy()->endOfMonth()->setTime($hour, $minute, $second),
            default => null,
        };
    }

    /**
     * Build a red missed-marker report for tasks that have a linked checklist.
     */
    private function buildMissedChecklistReport(Task $task, Carbon $now): string
    {
        $lines = [
            self::MISSED_REPORT_PREFIX,
            'Scheduled: ' . ($task->start_date ? Carbon::parse($task->start_date)->toDateTimeString() : 'N/A'),
            'Marked missed at: ' . $now->toDateTimeString(),
        ];

        return implode("\n", $lines);
    }

    /**
     * Best-effort copy of saveDeletedTask logic, adapted for system use (no Auth).
     * Includes the task report if the deleted_tasks table has a report column.
     */
    private function archiveToDeletedTasks(Task $task, Carbon $now, bool $hasReportColumn): bool
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
                'deleted_by_name' => 'Auto Expire (Missed)',
                'deleted_at' => $nowStr,
                'created_at' => $nowStr,
                'updated_at' => $nowStr,
            ];

            if ($hasReportColumn) {
                $row['report'] = $task->report !== null ? $str((string) $task->report, 65535) : null;
            }

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
                    'deleted_by_name' => 'Auto Expire (Missed)',
                    'deleted_at' => $nowStr,
                    'created_at' => $nowStr,
                    'updated_at' => $nowStr,
                ];
                if ($hasReportColumn) {
                    $minimal['report'] = $task->report !== null ? $str((string) $task->report, 65535) : null;
                }
                DB::table('deleted_tasks')->insert($minimal);

                return true;
            }
        } catch (\Throwable $e) {
            Log::warning('ExpireMissedAutomatedTasks: archive failed', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
