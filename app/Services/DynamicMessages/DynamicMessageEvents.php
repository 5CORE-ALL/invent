<?php

namespace App\Services\DynamicMessages;

use App\Models\AttendanceDailySummary;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskBusinessTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue of "page / data events" a message rule can listen to.
 *
 * To add a new page event, add an entry to definitions() and a resolver method.
 * A resolver receives the recipient users (keyed by id) and returns a list of
 * contexts: ['user_id' => int|null, 'vars' => array<string, scalar|null>].
 *
 *  - scope "user":   one context per recipient, delivered only to that person
 *                    (e.g. "your overdue tasks"). Metrics are about that person.
 *  - scope "global": one context, delivered to every recipient.
 *  - manual:         never fired by the scheduler; only via "Send now" / "Create now".
 */
class DynamicMessageEvents
{
    public const MANUAL = 'manual.broadcast';

    /**
     * @return array<string, array{page: string, label: string, scope: string, manual?: bool, metrics: array<string,string>, extras: array<string,string>, resolver: string}>
     */
    public static function definitions(): array
    {
        return [
            'tasks.assignee_summary' => [
                'page' => 'Tasks',
                'label' => 'Tasks page: assignee task summary',
                'scope' => 'user',
                'metrics' => [
                    'overdue_count' => 'Overdue tasks',
                    'todo_count' => 'Tasks in "Todo"',
                    'need_approval_count' => 'Tasks in "Need Approval"',
                    'rework_count' => 'Tasks in "Rework"',
                    'open_count' => 'All open tasks (not Done / Archived)',
                ],
                'extras' => [],
                'resolver' => 'resolveTaskSummary',
            ],
            'attendance.last_day' => [
                'page' => 'Attendance',
                'label' => 'Attendance page: last recorded working day',
                'scope' => 'user',
                'metrics' => [
                    'work_hours' => 'Work hours',
                    'active_percent' => 'Active % (active vs idle time)',
                    'idle_minutes' => 'Idle minutes',
                    'productivity_score' => 'Productivity score',
                    'is_late' => 'Marked late (1 = yes, 0 = no)',
                    'is_absent' => 'Marked absent (1 = yes, 0 = no)',
                ],
                'extras' => [
                    'work_date' => 'Date of that working day',
                    'status' => 'Attendance status (present / late / absent ...)',
                ],
                'resolver' => 'resolveAttendance',
            ],
            self::MANUAL => [
                'page' => 'Manual',
                'label' => 'Manual: run on demand ("Send now" / "Create now" button)',
                'scope' => 'global',
                'manual' => true,
                'metrics' => [],
                'extras' => [],
                'resolver' => 'resolveManual',
            ],
        ];
    }

    public static function definition(string $key): ?array
    {
        return self::definitions()[$key] ?? null;
    }

    /** Placeholders always available to every template. */
    public static function commonPlaceholders(): array
    {
        return [
            'name' => 'Recipient name',
            'first_name' => 'Recipient first name',
            'date' => 'Today (e.g. 24 Oct 2026)',
        ];
    }

    /** Everything the UI can show as a placeholder chip for an event. */
    public static function placeholdersFor(string $key): array
    {
        $def = self::definition($key);
        if (! $def) {
            return self::commonPlaceholders();
        }

        return array_merge(self::commonPlaceholders(), $def['metrics'], $def['extras']);
    }

    /**
     * @param  Collection<int, User>  $recipients  keyed by user id
     * @return list<array{user_id: ?int, vars: array<string, mixed>}>
     */
    public static function resolve(string $key, Collection $recipients): array
    {
        $def = self::definition($key);
        if (! $def) {
            return [];
        }

        return (new self)->{$def['resolver']}($recipients);
    }

    // ------------------------------------------------------------------ resolvers

    /** @return list<array{user_id: ?int, vars: array<string, mixed>}> */
    protected function resolveManual(Collection $recipients): array
    {
        return [['user_id' => null, 'vars' => []]];
    }

    protected function resolveTaskSummary(Collection $recipients): array
    {
        $byEmail = [];
        foreach ($recipients as $user) {
            $byEmail[strtolower(trim((string) $user->email))] = [
                'overdue_count' => 0, 'todo_count' => 0, 'need_approval_count' => 0,
                'rework_count' => 0, 'open_count' => 0,
            ];
        }

        if ($byEmail && Schema::hasTable('tasks')) {
            $tasks = Task::query()
                ->whereNotNull('assign_to')
                ->where('assign_to', '!=', '')
                ->where(function ($q) {
                    $q->whereNull('status')->orWhereNotIn('status', ['Done', 'Archived']);
                })
                ->select(['id', 'assign_to', 'status', 'start_date', 'created_at', 'schedule_type', 'is_automate_task'])
                ->cursor();

            foreach ($tasks as $task) {
                $overdue = null;
                foreach (explode(',', (string) $task->assign_to) as $email) {
                    $email = strtolower(trim($email));
                    if ($email === '' || ! isset($byEmail[$email])) {
                        continue;
                    }
                    $status = (string) $task->status;
                    $byEmail[$email]['open_count']++;
                    if ($status === 'Todo') {
                        $byEmail[$email]['todo_count']++;
                    } elseif ($status === 'Need Approval') {
                        $byEmail[$email]['need_approval_count']++;
                    } elseif ($status === 'Rework') {
                        $byEmail[$email]['rework_count']++;
                    }

                    $overdue ??= TaskBusinessTime::isTaskOverdue(
                        $status,
                        $task->getRawOriginal('start_date') ?? $task->start_date,
                        $task->getRawOriginal('created_at') ?? $task->created_at,
                        $task->schedule_type ?? '',
                        ! empty($task->is_automate_task)
                    );
                    if ($overdue) {
                        $byEmail[$email]['overdue_count']++;
                    }
                }
            }
        }

        $contexts = [];
        foreach ($recipients as $user) {
            $contexts[] = [
                'user_id' => (int) $user->id,
                'vars' => $byEmail[strtolower(trim((string) $user->email))],
            ];
        }

        return $contexts;
    }

    protected function resolveAttendance(Collection $recipients): array
    {
        if ($recipients->isEmpty() || ! Schema::hasTable('attendance_daily_summaries')) {
            return [];
        }

        $today = now(config('dynamic_messages.timezone'))->toDateString();
        $workDate = AttendanceDailySummary::query()
            ->where('work_date', '<', $today)
            ->max('work_date');
        if (! $workDate) {
            return [];
        }

        $rows = AttendanceDailySummary::query()
            ->whereDate('work_date', $workDate)
            ->whereIn('user_id', $recipients->keys()->all())
            ->get()
            ->keyBy('user_id');

        $contexts = [];
        foreach ($recipients as $user) {
            $row = $rows->get($user->id);
            if (! $row) {
                continue; // no attendance data for this person that day
            }
            $status = (string) $row->status;
            $contexts[] = [
                'user_id' => (int) $user->id,
                'vars' => [
                    'work_hours' => round($row->total_work_seconds / 3600, 2),
                    'active_percent' => $row->activePercent(),
                    'idle_minutes' => (int) round($row->idle_seconds / 60),
                    'productivity_score' => $row->productivity_score,
                    'is_late' => $status === 'late' ? 1 : 0,
                    'is_absent' => $status === 'absent' ? 1 : 0,
                    'work_date' => \Carbon\Carbon::parse($workDate)->format('d M Y'),
                    'status' => $status,
                ],
            ];
        }

        return $contexts;
    }
}
