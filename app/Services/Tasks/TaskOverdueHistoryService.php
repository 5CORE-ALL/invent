<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Support\TaskBusinessTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Daily snapshots of every team member's overdue task count (the O-Due column on /tasks/summary).
 *
 * The count uses the same rule as the page (TaskBusinessTime::isTaskOverdue, assignee based), over all
 * tasks - not only the tasks one viewer may see - so the stored number is the same for every viewer.
 */
class TaskOverdueHistoryService
{
    /**
     * Count and store today's overdue tasks for every active team member.
     *
     * @return array{members: int, overdue_total: int}
     */
    public function snapshot(): array
    {
        if (! Schema::hasTable('task_overdue_history')) {
            return ['members' => 0, 'overdue_total' => 0];
        }

        $byEmail = [];
        Task::query()
            ->whereNotNull('assign_to')
            ->where('assign_to', '!=', '')
            ->where(function ($q) {
                $q->whereNull('status')->orWhereNotIn('status', ['Done', 'Archived']);
            })
            ->select(['id', 'assign_to', 'status', 'start_date', 'created_at', 'schedule_type', 'is_automate_task', 'group'])
            ->orderBy('id')
            ->chunkById(2000, function ($tasks) use (&$byEmail) {
                foreach ($tasks as $task) {
                    $overdue = TaskBusinessTime::isTaskOverdue(
                        $task->status ?? '',
                        $task->getRawOriginal('start_date') ?? $task->start_date,
                        $task->getRawOriginal('created_at') ?? $task->created_at,
                        $task->schedule_type ?? '',
                        ! empty($task->is_automate_task),
                        $task->group ?? null
                    );
                    if (! $overdue) {
                        continue;
                    }
                    foreach (array_map('trim', explode(',', (string) $task->assign_to)) as $email) {
                        if ($email !== '') {
                            $byEmail[$email] = ($byEmail[$email] ?? 0) + 1;
                        }
                    }
                }
            });

        // Done tasks the assignor has not checked and deleted are overdue for the ASSIGNOR.
        Task::query()
            ->where('status', 'Done')
            ->whereNotNull('assignor')
            ->where('assignor', '!=', '')
            ->select(['id', 'assignor', 'status', 'start_date', 'created_at', 'schedule_type', 'is_automate_task', 'group'])
            ->orderBy('id')
            ->chunkById(2000, function ($tasks) use (&$byEmail) {
                foreach ($tasks as $task) {
                    $overdue = TaskBusinessTime::isDoneUncheckedOverdue(
                        $task->status ?? '',
                        $task->getRawOriginal('start_date') ?? $task->start_date,
                        $task->getRawOriginal('created_at') ?? $task->created_at,
                        $task->schedule_type ?? '',
                        ! empty($task->is_automate_task),
                        $task->group ?? null
                    );
                    $assignor = trim((string) $task->assignor);
                    if ($overdue && $assignor !== '') {
                        $byEmail[$assignor] = ($byEmail[$assignor] ?? 0) + 1;
                    }
                }
            });

        $byUser = [];
        User::query()
            ->where('is_active', true)
            ->whereNull('deactivated_at')
            ->get(['id', 'email'])
            ->each(function ($user) use (&$byUser, $byEmail) {
                $byUser[(int) $user->id] = (int) ($byEmail[$user->email] ?? 0);
            });

        $this->store($byUser);
        $this->pruneReadings();

        return ['members' => count($byUser), 'overdue_total' => array_sum($byUser)];
    }

    /**
     * Store today's value per user (overwrites the earlier value of the same day).
     *
     * @param  array<int, int>  $overdueByUserId
     */
    public function store(array $overdueByUserId): void
    {
        if ($overdueByUserId === [] || ! Schema::hasTable('task_overdue_history')) {
            return;
        }

        $today = TaskBusinessTime::today()->toDateString();
        $now = now();
        // opening_overdue keeps the day's first value: it is part of the insert but not of the update list below.
        $hasOpening = Schema::hasColumn('task_overdue_history', 'opening_overdue');
        $payload = [];
        foreach ($overdueByUserId as $userId => $overdue) {
            $row = [
                'user_id' => (int) $userId,
                'snap_date' => $today,
                'overdue' => max(0, (int) $overdue),
                'captured_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($hasOpening) {
                $row['opening_overdue'] = $row['overdue'];
            }
            $payload[] = $row;
        }

        try {
            foreach (array_chunk($payload, 200) as $chunk) {
                DB::table('task_overdue_history')->upsert($chunk, ['user_id', 'snap_date'], ['overdue', 'captured_at', 'updated_at']);
            }
        } catch (\Throwable $e) {
            Log::warning('TaskOverdueHistoryService: could not store snapshot', ['error' => $e->getMessage()]);
        }

        $this->storeReadings($overdueByUserId, $now);
    }

    /**
     * One point per member for the rolling graph. A member who already has a reading from the last
     * 9 minutes is skipped, so page visits cannot flood the table.
     *
     * @param  array<int, int>  $overdueByUserId
     */
    protected function storeReadings(array $overdueByUserId, \Illuminate\Support\Carbon $now): void
    {
        if (! Schema::hasTable('task_overdue_readings')) {
            return;
        }

        try {
            $recent = DB::table('task_overdue_readings')
                ->where('captured_at', '>=', $now->copy()->subMinutes(9))
                ->distinct()
                ->pluck('user_id')
                ->map(fn ($v) => (int) $v)
                ->flip()
                ->all();

            $payload = [];
            foreach ($overdueByUserId as $userId => $overdue) {
                if (isset($recent[(int) $userId])) {
                    continue;
                }
                $payload[] = ['user_id' => (int) $userId, 'overdue' => max(0, (int) $overdue), 'captured_at' => $now];
            }
            foreach (array_chunk($payload, 500) as $chunk) {
                DB::table('task_overdue_readings')->insert($chunk);
            }
        } catch (\Throwable $e) {
            Log::warning('TaskOverdueHistoryService: could not store readings', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Rolling series for the graph: every reading of the last $days days, oldest first.
     * One user id gives that member; a list gives the sum of those members; null gives everyone.
     * Falls back to the daily values while no readings exist.
     *
     * @param  array<int, int>|null  $userIds
     * @return list<array{t: string, value: int}>
     */
    public function rollingSeries(?array $userIds, int $days): array
    {
        if ($userIds === []) {
            return [];
        }

        if (Schema::hasTable('task_overdue_readings')) {
            $query = DB::table('task_overdue_readings')
                ->where('captured_at', '>=', now()->subDays(max(1, $days)))
                ->selectRaw('captured_at, SUM(overdue) AS total')
                ->groupBy('captured_at')
                ->orderBy('captured_at');
            if ($userIds !== null) {
                $query->whereIn('user_id', array_map('intval', $userIds));
            }
            $tz = (string) config('app.timezone', 'UTC');
            $points = $query->get()->map(fn ($r) => [
                't' => \Illuminate\Support\Carbon::parse((string) $r->captured_at, $tz)->toIso8601String(),
                'value' => (int) $r->total,
            ])->all();
            if ($points !== []) {
                return $points;
            }
        }

        return array_map(fn ($p) => ['t' => $p['date'].'T12:00:00', 'value' => $p['value']], $this->series($userIds, $days));
    }

    /** Old readings are not needed for the graph (longest range is 90 days). */
    public function pruneReadings(int $keepDays = 120): int
    {
        if (! Schema::hasTable('task_overdue_readings')) {
            return 0;
        }

        return (int) DB::table('task_overdue_readings')->where('captured_at', '<', now()->subDays($keepDays))->delete();
    }

    /**
     * Each member's most recent snapshot from an earlier day than today (what the O-Due arrow compares with).
     *
     * @return array<int, array{value: int, date: string}> user id => value and day
     */
    public function previousDayByUser(): array
    {
        if (! Schema::hasTable('task_overdue_history')) {
            return [];
        }

        try {
            $latest = DB::table('task_overdue_history')
                ->where('snap_date', '<', TaskBusinessTime::today()->toDateString())
                ->selectRaw('user_id, MAX(snap_date) AS d')
                ->groupBy('user_id');

            $out = [];
            DB::table('task_overdue_history as h')
                ->joinSub($latest, 'm', function ($join) {
                    $join->on('h.user_id', '=', 'm.user_id')->on('h.snap_date', '=', 'm.d');
                })
                ->get(['h.user_id', 'h.overdue', 'h.snap_date'])
                ->each(function ($r) use (&$out) {
                    $out[(int) $r->user_id] = ['value' => (int) $r->overdue, 'date' => substr((string) $r->snap_date, 0, 10)];
                });

            // No earlier day yet: compare with the first value saved today, so a change shows up the same day.
            if (Schema::hasColumn('task_overdue_history', 'opening_overdue')) {
                DB::table('task_overdue_history')
                    ->where('snap_date', TaskBusinessTime::today()->toDateString())
                    ->whereNotNull('opening_overdue')
                    ->get(['user_id', 'opening_overdue'])
                    ->each(function ($r) use (&$out) {
                        $uid = (int) $r->user_id;
                        if (! isset($out[$uid])) {
                            $out[$uid] = ['value' => (int) $r->opening_overdue, 'date' => 'the first check today'];
                        }
                    });
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('TaskOverdueHistoryService: previous-day lookup failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Rolling series, one point per day that has a snapshot.
     * One user id gives that member; a list gives the sum of those members; null gives everyone.
     *
     * @param  array<int, int>|null  $userIds
     * @return list<array{date: string, value: int}>
     */
    public function series(?array $userIds, int $days): array
    {
        if (! Schema::hasTable('task_overdue_history')) {
            return [];
        }

        $from = TaskBusinessTime::today()->subDays(max(1, $days) - 1)->toDateString();
        $query = DB::table('task_overdue_history')
            ->where('snap_date', '>=', $from)
            ->selectRaw('snap_date, SUM(overdue) AS total')
            ->groupBy('snap_date')
            ->orderBy('snap_date');
        if ($userIds !== null) {
            if ($userIds === []) {
                return [];
            }
            $query->whereIn('user_id', array_map('intval', $userIds));
        }

        return $query->get()->map(fn ($r) => [
            'date' => substr((string) $r->snap_date, 0, 10),
            'value' => (int) $r->total,
        ])->all();
    }
}
