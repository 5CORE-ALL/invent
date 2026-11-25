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
            ->select(['id', 'assign_to', 'status', 'start_date', 'created_at', 'schedule_type', 'is_automate_task'])
            ->orderBy('id')
            ->chunkById(2000, function ($tasks) use (&$byEmail) {
                foreach ($tasks as $task) {
                    $overdue = TaskBusinessTime::isTaskOverdue(
                        $task->status ?? '',
                        $task->getRawOriginal('start_date') ?? $task->start_date,
                        $task->getRawOriginal('created_at') ?? $task->created_at,
                        $task->schedule_type ?? '',
                        ! empty($task->is_automate_task)
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

        $byUser = [];
        User::query()
            ->where('is_active', true)
            ->whereNull('deactivated_at')
            ->get(['id', 'email'])
            ->each(function ($user) use (&$byUser, $byEmail) {
                $byUser[(int) $user->id] = (int) ($byEmail[$user->email] ?? 0);
            });

        $this->store($byUser);

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
        $payload = [];
        foreach ($overdueByUserId as $userId => $overdue) {
            $payload[] = [
                'user_id' => (int) $userId,
                'snap_date' => $today,
                'overdue' => max(0, (int) $overdue),
                'captured_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        try {
            foreach (array_chunk($payload, 200) as $chunk) {
                DB::table('task_overdue_history')->upsert($chunk, ['user_id', 'snap_date'], ['overdue', 'captured_at', 'updated_at']);
            }
        } catch (\Throwable $e) {
            Log::warning('TaskOverdueHistoryService: could not store snapshot', ['error' => $e->getMessage()]);
        }
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
