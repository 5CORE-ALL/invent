<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDailySummary;
use App\Models\Dar;
use App\Models\DeletedTask;
use App\Models\Task;
use App\Models\TeamLoggerDailyHours;
use App\Models\User;
use App\Services\TeamSalaryCalculator;
use App\Support\AttL30Metrics;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class DarController extends Controller
{
    public function index(Request $request)
    {
        $users = User::active()->orderBy('name')->get(['id', 'name', 'email']);
        $filterUserId = (int) $request->query('user_id', 0);
        $filterDays = (int) $request->query('days', 0);

        return view('purchase-master.dar.index', compact('users', 'filterUserId', 'filterDays'));
    }

    public function data(Request $request)
    {
        $query = Dar::with('user:id,name,email');

        $userId = (int) $request->query('user_id', 0);
        if ($userId > 0) {
            $query->where('user_id', $userId);
        }

        $days = (int) $request->query('days', 0);
        if ($days > 0) {
            $query->whereDate('report_date', '>=', now()->subDays(max($days, 1) - 1)->toDateString());
        }

        $rows = $query
            ->orderByDesc('report_date')
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'user_id' => $row->user_id,
                    'user_name' => optional($row->user)->name,
                    'report_date' => optional($row->report_date)->format('Y-m-d'),
                    'group' => $row->group,
                    'task' => $row->task,
                    'time_taken' => $row->time_taken,
                    'history' => $row->history ?? [],
                    'updated_by' => $row->updated_by,
                    'updated_at' => optional($row->updated_at)->format('Y-m-d H:i'),
                ];
            });

        return response()->json(['data' => $rows]);
    }

    /**
     * Comparison grid: one row per day showing
     *  - number of tasks done that day
     *  - ATC (sum of etc_done minutes of tasks marked Done that day)
     *  - ETC (sum of eta_time minutes of those same tasks)
     *  - working hours that day (attendance active time, Team Logger for its users)
     */
    public function comparison(Request $request)
    {
        $days = (int) $request->query('days', 30);
        $days = min(max($days, 1), 366);
        $userId = (int) $request->query('user_id', 0);

        $tz = 'America/Los_Angeles';
        $to = Carbon::today($tz);
        $from = $to->copy()->subDays($days - 1);
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $users = User::query()->get(['id', 'name', 'email'])->keyBy('id');
        $idByEmail = [];
        foreach ($users as $u) {
            $key = strtolower(trim((string) $u->email));
            if ($key !== '') {
                $idByEmail[$key] = (int) $u->id;
            }
        }

        // [date][user_id] => ['dar' => min, 'atc' => min, 'done' => n, 'work' => seconds]
        $grid = [];
        $cell = function (string $date, int $uid) use (&$grid): void {
            if (! isset($grid[$date][$uid])) {
                $grid[$date][$uid] = ['etc' => 0.0, 'atc' => 0.0, 'done' => 0, 'work' => 0];
            }
        };

        // Done tasks (live + archived), credited to every assignee on the completion day
        $startAt = $from->copy()->startOfDay()->format('Y-m-d H:i:s');
        $endAt = $to->copy()->endOfDay()->format('Y-m-d H:i:s');
        $seen = [];
        $addTask = function ($task, bool $archived) use (&$grid, &$seen, $cell, $idByEmail, $userId): void {
            if (empty($task->completion_date)) {
                return;
            }
            $taskKey = $archived ? (int) ($task->original_task_id ?? 0) : (int) $task->id;
            if ($taskKey > 0) {
                if ($archived && isset($seen[$taskKey])) {
                    return; // live copy already counted
                }
                $seen[$taskKey] = true;
            }
            $date = Carbon::parse($task->completion_date)->toDateString();
            foreach (array_filter(array_map('trim', explode(',', (string) ($task->assign_to ?? '')))) as $email) {
                $uid = $idByEmail[strtolower($email)] ?? 0;
                if ($uid <= 0 || ($userId > 0 && $uid !== $userId)) {
                    continue;
                }
                $cell($date, $uid);
                $grid[$date][$uid]['done']++;
                $grid[$date][$uid]['atc'] += (float) ($task->etc_done ?? 0);
                $grid[$date][$uid]['etc'] += (float) ($task->eta_time ?? 0);
            }
        };

        Task::query()
            ->where('status', 'Done')
            ->whereBetween('completion_date', [$startAt, $endAt])
            ->get(['id', 'assign_to', 'completion_date', 'etc_done', 'eta_time'])
            ->each(fn ($t) => $addTask($t, false));

        DeletedTask::query()
            ->where('status', 'Done')
            ->whereBetween('completion_date', [$startAt, $endAt])
            ->get(['original_task_id', 'assign_to', 'completion_date', 'etc_done', 'eta_time'])
            ->each(fn ($t) => $addTask($t, true));

        // Working hours (attendance active seconds)
        if (Schema::hasTable('attendance_daily_summaries')) {
            $q = AttendanceDailySummary::query()
                ->whereDate('work_date', '>=', $fromDate)
                ->whereDate('work_date', '<=', $toDate);
            if ($userId > 0) {
                $q->where('user_id', $userId);
            }
            foreach ($q->get(['user_id', 'work_date', 'active_seconds']) as $row) {
                $date = optional($row->work_date)->format('Y-m-d');
                if (! $date || (int) $row->active_seconds <= 0) {
                    continue;
                }
                $cell($date, (int) $row->user_id);
                $grid[$date][(int) $row->user_id]['work'] = max($grid[$date][(int) $row->user_id]['work'], (int) $row->active_seconds);
            }
        }

        // Team Logger users (e.g. Shobha, Mariya) clock time there instead
        if (Schema::hasTable('team_logger_daily_hours')) {
            $mapper = new TeamSalaryCalculator();
            $hoursByEmailDate = [];
            TeamLoggerDailyHours::query()
                ->whereDate('work_date', '>=', $fromDate)
                ->whereDate('work_date', '<=', $toDate)
                ->get(['employee_email', 'work_date', 'active_hours', 'productive_hours'])
                ->each(function ($r) use (&$hoursByEmailDate) {
                    $email = strtolower(trim((string) $r->employee_email));
                    $date = optional($r->work_date)->format('Y-m-d');
                    if ($email === '' || ! $date) {
                        return;
                    }
                    $h = (float) $r->active_hours;
                    if ($h <= 0) {
                        $h = (float) $r->productive_hours;
                    }
                    $hoursByEmailDate[$email][$date] = max($hoursByEmailDate[$email][$date] ?? 0, $h);
                });

            foreach ($users as $u) {
                if (! AttL30Metrics::usesTeamLogger($u->name, $u->email)) {
                    continue;
                }
                if ($userId > 0 && (int) $u->id !== $userId) {
                    continue;
                }
                $appEmail = strtolower(trim((string) $u->email));
                $tlEmail = strtolower($mapper->teamLoggerEmail((string) $u->email));
                $byDate = $hoursByEmailDate[$tlEmail] ?? $hoursByEmailDate[$appEmail] ?? [];
                foreach ($byDate as $date => $hours) {
                    if ($hours <= 0) {
                        continue;
                    }
                    $cell($date, (int) $u->id);
                    $grid[$date][(int) $u->id]['work'] = max($grid[$date][(int) $u->id]['work'], (int) round($hours * 3600));
                }
            }
        }

        // One row per date (all selected users summed)
        $rows = [];
        foreach ($grid as $date => $byUser) {
            $t = ['etc' => 0.0, 'atc' => 0.0, 'done' => 0, 'work' => 0];
            foreach ($byUser as $m) {
                foreach ($t as $k => $_) {
                    $t[$k] += $m[$k];
                }
            }
            if ($t['atc'] <= 0 && $t['etc'] <= 0 && $t['done'] <= 0 && $t['work'] <= 0) {
                continue;
            }
            $rows[] = [
                'id' => $date,
                'report_date' => $date,
                'tasks_done' => $t['done'],
                'atc_minutes' => round($t['atc'], 2),
                'etc_minutes' => round($t['etc'], 2),
                'working_minutes' => (int) round($t['work'] / 60),
            ];
        }

        usort($rows, fn (array $a, array $b): int => strcmp($b['report_date'], $a['report_date']));

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'report_date' => 'required|date',
            'group' => 'nullable|string|max:255',
            'task' => 'nullable|string',
            'time_taken' => 'nullable|numeric|min:0',
        ]);

        $email = Auth::user()->email ?? 'system';

        $validated['created_by'] = $email;
        $validated['updated_by'] = $email;
        $validated['history'] = [
            $this->historyEntry($email, 'created'),
        ];

        $row = Dar::create($validated);

        return response()->json(['success' => true, 'id' => $row->id]);
    }

    public function update(Request $request, $id)
    {
        $row = Dar::findOrFail($id);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'report_date' => 'required|date',
            'group' => 'nullable|string|max:255',
            'task' => 'nullable|string',
            'time_taken' => 'nullable|numeric|min:0',
        ]);

        $email = Auth::user()->email ?? 'system';

        $history = $row->history ?? [];
        $history[] = $this->historyEntry($email, 'updated');

        $validated['updated_by'] = $email;
        $validated['history'] = $history;

        $row->update($validated);

        return response()->json(['success' => true, 'id' => $row->id]);
    }

    public function destroy($id)
    {
        $row = Dar::findOrFail($id);
        $row->delete();

        return response()->json(['success' => true]);
    }

    private function historyEntry(string $email, string $action): array
    {
        return [
            'email' => $email,
            'action' => $action,
            'at' => now()->format('Y-m-d H:i'),
        ];
    }
}
