<?php

namespace App\Services;

use App\Models\PageAttendanceAssignment as Assignment;
use App\Models\PageAttendanceEvent as Event;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Page attendance: the people responsible for a page check in after their work on it is done.
 *
 * Each assignment (person + page + frequency) has back-to-back periods of `frequency_days` days that start on
 * the day it was assigned: daily = every calendar day, 7 = one 7-day block, 30 = one 30-day block.
 * Checking in during a period marks it done. A period that ends without a check-in is recorded as missed in
 * the history and a task is generated for the person (page-attendance:check, every morning).
 * Days are India days (Asia/Kolkata).
 */
class PageAttendanceService
{
    public const TZ = 'Asia/Kolkata';

    public const TASK_GROUP = 'Page Attendance';

    public const FALLBACK_ASSIGNOR = 'software5@5core.com';

    /** Never walk back further than this when catching up on ended periods. */
    private const CATCH_UP_DAYS = 120;

    /**
     * Pages that can have a responsible-person list. Add a key here and include the two
     * partials (page-attendance-badge / page-attendance-modal) on the page to enable another one.
     *
     * @return array<string, array{label: string, url: string}>
     */
    public static function pages(): array
    {
        return [
            'order-fulfillment.pending' => ['label' => 'Order Fulfillment · Pending', 'url' => url('/order-fulfillment/pending')],
        ];
    }

    public static function pageLabel(string $pageKey): string
    {
        return self::pages()[$pageKey]['label'] ?? $pageKey;
    }

    public static function available(): bool
    {
        return Schema::hasTable('page_attendance_assignments') && Schema::hasTable('page_attendance_events');
    }

    public static function today(): Carbon
    {
        return now(self::TZ)->startOfDay();
    }

    public static function frequencyShort(int $days): string
    {
        return $days === 1 ? 'Daily' : $days.'d';
    }

    public static function frequencyLabel(int $days): string
    {
        return $days === 1 ? 'Daily' : 'Every '.$days.' days';
    }

    private static function startOf(Assignment $a): Carbon
    {
        return Carbon::parse($a->start_date->toDateString(), self::TZ)->startOfDay();
    }

    /**
     * The period of an assignment that contains $day, or null when $day is before it started.
     *
     * @return array{0: Carbon, 1: Carbon}|null [first day, last day]
     */
    public static function periodFor(Assignment $a, Carbon $day): ?array
    {
        $start = self::startOf($a);
        $n = max(1, (int) $a->frequency_days);
        $diff = (int) round($start->diffInDays($day->copy()->startOfDay(), false));
        if ($diff < 0) {
            return null;
        }
        $first = $start->copy()->addDays(intdiv($diff, $n) * $n);

        return [$first, $first->copy()->addDays($n - 1)];
    }

    /**
     * The signed-in person says their work on the page is done: every assignment of theirs on this
     * page whose current period has no check-in yet is marked done.
     *
     * @return array{ok: bool, message: string, marked: int, already: int}
     */
    public function checkIn(User $user, string $pageKey, ?string $note): array
    {
        $assignments = Assignment::query()
            ->where('page_key', $pageKey)->where('user_id', $user->id)->where('active', true)->get();
        if ($assignments->isEmpty()) {
            return ['ok' => false, 'message' => 'You are not assigned to this page.', 'marked' => 0, 'already' => 0];
        }

        $today = self::today();
        $note = $note !== null ? mb_substr(trim($note), 0, 500) : null;
        $marked = 0;
        $already = 0;

        foreach ($assignments as $a) {
            $period = self::periodFor($a, $today);
            if ($period === null) {
                continue;
            }
            $exists = Event::query()->where('assignment_id', $a->id)->whereDate('period_start', $period[0]->toDateString())->exists();
            if ($exists) {
                $already++;

                continue;
            }
            try {
                Event::create([
                    'assignment_id' => $a->id,
                    'page_key' => $pageKey,
                    'user_id' => $user->id,
                    'frequency_days' => $a->frequency_days,
                    'period_start' => $period[0]->toDateString(),
                    'period_end' => $period[1]->toDateString(),
                    'status' => Event::DONE,
                    'visited_at' => now(),
                    'note' => $note !== '' ? $note : null,
                ]);
                $marked++;
            } catch (\Throwable $e) {
                // A double click lost the race on the unique key: the period is already recorded.
                $already++;
            }
        }

        return [
            'ok' => true,
            'message' => $marked > 0 ? 'Attendance marked.' : 'You are already checked in for this period.',
            'marked' => $marked,
            'already' => $already,
        ];
    }

    /**
     * Record periods that ended without a check-in and generate a task for the latest one.
     *
     * @return array{checked: int, missed: int, tasks: int}
     */
    public function processMissed(): array
    {
        $today = self::today();
        $floor = $today->copy()->subDays(self::CATCH_UP_DAYS);
        $checked = 0;
        $missed = 0;
        $tasks = 0;

        $assignments = Assignment::query()->with('user')->where('active', true)->get();
        foreach ($assignments as $a) {
            $checked++;
            $n = max(1, (int) $a->frequency_days);
            $start = self::startOf($a);

            // Skip ahead so a very old assignment does not walk years of periods.
            $first = $start->copy();
            if ($first->lt($floor)) {
                $first->addDays((int) floor(((int) round($first->diffInDays($floor, false))) / $n) * $n);
            }

            $known = Event::query()->where('assignment_id', $a->id)
                ->get(['period_start'])
                ->map(fn ($e) => $e->period_start->toDateString())
                ->flip();

            for ($ps = $first->copy(); $ps->copy()->addDays($n - 1)->lt($today); $ps->addDays($n)) {
                $pe = $ps->copy()->addDays($n - 1);
                if ($known->has($ps->toDateString())) {
                    continue;
                }
                // Only the period that just ended gets a task; older gaps are history only.
                $isLatest = $pe->gte($today->copy()->subDay());
                $taskId = $isLatest ? $this->createMissedTask($a, $ps, $pe) : null;
                try {
                    Event::create([
                        'assignment_id' => $a->id,
                        'page_key' => $a->page_key,
                        'user_id' => $a->user_id,
                        'frequency_days' => $n,
                        'period_start' => $ps->toDateString(),
                        'period_end' => $pe->toDateString(),
                        'status' => Event::MISSED,
                        'task_id' => $taskId,
                    ]);
                    $missed++;
                    if ($taskId) {
                        $tasks++;
                    }
                } catch (\Throwable $e) {
                    Log::warning('page-attendance: could not save a missed period', ['assignment' => $a->id, 'error' => $e->getMessage()]);
                }
            }
        }

        return ['checked' => $checked, 'missed' => $missed, 'tasks' => $tasks];
    }

    private function createMissedTask(Assignment $a, Carbon $ps, Carbon $pe): ?int
    {
        try {
            $user = $a->user;
            if (! $user || ! Schema::hasTable('tasks')) {
                return null;
            }
            $marker = 'page-attendance-'.$a->id.'-'.$ps->toDateString();
            $existing = Task::withTrashed()->where('link8', $marker)->first();
            if ($existing) {
                return (int) $existing->id;
            }

            $label = self::pageLabel($a->page_key);
            $url = self::pages()[$a->page_key]['url'] ?? url('/');
            $period = $ps->equalTo($pe) ? $ps->format('j M Y') : $ps->format('j M').' – '.$pe->format('j M Y');
            $start = now();
            $due = now(self::TZ)->endOfDay()->setTimezone(config('app.timezone'));

            $data = [
                'title' => 'Mark attendance on '.$label.' ('.self::frequencyShort((int) $a->frequency_days).'): missed '.$period,
                'description' => "You are responsible for {$label} ({$this->freqText($a)}) and did not check in for {$period}.\n\n"
                    ."Open {$url}, finish your work on the page, then press \"Mark attendance\" in the Page attendance box.",
                'group' => self::TASK_GROUP,
                'priority' => 'normal',
                'assignor' => $a->assignor_email ?: self::FALLBACK_ASSIGNOR,
                'assign_to' => $user->email ?: $user->name,
                'status' => 'Todo',
                'eta_time' => 10,
                'start_date' => $start,
                'completion_date' => $due,
                'due_date' => $due,
                'completion_day' => 0,
                'etc_done' => 0,
                'is_missed' => 0,
                'is_missed_track' => 0,
                'workspace' => 0,
                'order' => 0,
                'task_id' => '',
                'link1' => $url,
                'link2' => '', 'link3' => '', 'link4' => '', 'link5' => '', 'link6' => '', 'link7' => '',
                'link8' => $marker,
                'link9' => '',
                'is_data_from' => 0,
                'is_automate_task' => 0,
                'task_type' => 'manual',
                'rework_reason' => '',
                'delete_rating' => 0,
                'delete_feedback' => '',
                'split_tasks' => 0,
            ];
            if (Schema::hasColumn('tasks', 'is_corrective_action')) {
                $data['is_corrective_action'] = 0;
            }
            $task = Task::create($data);

            try {
                app(TaskWhatsAppNotificationService::class)->notifyNewTaskAssigned($task);
            } catch (\Throwable $e) {
                Log::warning('page-attendance task WhatsApp failed', ['task_id' => $task->id, 'error' => $e->getMessage()]);
            }

            return (int) $task->id;
        } catch (\Throwable $e) {
            Log::warning('page-attendance: task could not be created: '.$e->getMessage(), ['assignment' => $a->id]);

            return null;
        }
    }

    private function freqText(Assignment $a): string
    {
        return strtolower(self::frequencyLabel((int) $a->frequency_days));
    }

    /**
     * Everything the modal needs.
     *
     * @return array<string, mixed>
     */
    public function state(string $pageKey, ?User $viewer, bool $isAdmin): array
    {
        $today = self::today();
        $since30 = $today->copy()->subDays(30)->toDateString();

        $assignments = Assignment::query()->with('user')
            ->where('page_key', $pageKey)->where('active', true)
            ->get()
            ->sortBy(fn (Assignment $a) => strtolower((string) ($a->user->name ?? '')).'|'.str_pad((string) $a->frequency_days, 4, '0', STR_PAD_LEFT))
            ->values();

        $ids = $assignments->pluck('id')->all();
        $events = Event::query()->where('page_key', $pageKey)
            ->where('period_end', '>=', $today->copy()->subDays(self::CATCH_UP_DAYS)->toDateString())
            ->get();
        $byAssignment = $events->groupBy('assignment_id');

        $rows = [];
        $doneNow = 0;
        foreach ($assignments as $a) {
            $period = self::periodFor($a, $today);
            $mine = $byAssignment->get($a->id, collect());
            $current = $period ? $mine->first(fn (Event $e) => $e->period_start->toDateString() === $period[0]->toDateString()) : null;
            $isDone = $current && $current->status === Event::DONE;
            if ($isDone) {
                $doneNow++;
            }
            $lastDone = $mine->where('status', Event::DONE)->sortByDesc('visited_at')->first();
            $name = (string) ($a->user->name ?? 'Unknown');

            $rows[] = [
                'id' => $a->id,
                'user_id' => (int) $a->user_id,
                'name' => $name,
                'initials' => $this->initials($name),
                'frequency_days' => (int) $a->frequency_days,
                'frequency' => self::frequencyShort((int) $a->frequency_days),
                'frequency_label' => self::frequencyLabel((int) $a->frequency_days),
                'started' => $a->start_date->format('j M Y'),
                'status' => $period === null ? 'upcoming' : ($isDone ? 'done' : 'due'),
                'period_start' => $period ? $period[0]->format('j M') : null,
                'period_end' => $period ? $period[1]->format('j M') : null,
                'days_left' => $period ? max(0, (int) round($today->diffInDays($period[1], false))) : null,
                'done_at' => $isDone && $current->visited_at ? $current->visited_at->copy()->setTimezone(self::TZ)->format('j M, g:i A') : null,
                'last_visit' => $lastDone && $lastDone->visited_at ? $lastDone->visited_at->copy()->setTimezone(self::TZ)->format('j M, g:i A') : null,
                'missed_30d' => $mine->filter(fn (Event $e) => $e->status === Event::MISSED && $e->period_end->toDateString() >= $since30)->count(),
            ];
        }

        $history = Event::query()->with('user')
            ->where('page_key', $pageKey)
            ->orderByRaw('COALESCE(visited_at, created_at) DESC')
            ->limit(300)->get()
            ->map(function (Event $e): array {
                $when = $e->visited_at ?? $e->created_at;

                return [
                    'id' => $e->id,
                    'user_id' => (int) $e->user_id,
                    'name' => (string) ($e->user->name ?? 'Unknown'),
                    'initials' => $this->initials((string) ($e->user->name ?? '?')),
                    'frequency' => self::frequencyShort((int) $e->frequency_days),
                    'status' => $e->status,
                    'period' => $e->period_start->equalTo($e->period_end)
                        ? $e->period_start->format('j M Y')
                        : $e->period_start->format('j M').' – '.$e->period_end->format('j M Y'),
                    'when' => $when ? $when->copy()->setTimezone(self::TZ)->format('j M Y, g:i A') : '',
                    'note' => (string) ($e->note ?? ''),
                    'task_id' => $e->task_id ? (int) $e->task_id : null,
                ];
            })->values()->all();

        $people = $assignments->pluck('user_id')->unique()->count();
        $stats = [
            'people' => $people,
            'periods' => count($rows),
            'done_now' => $doneNow,
            'pending_now' => count($rows) - $doneNow,
            'missed_30d' => $events->filter(fn (Event $e) => $e->status === Event::MISSED && $e->period_end->toDateString() >= $since30)->count(),
            'tasks_30d' => $events->filter(fn (Event $e) => $e->task_id && $e->period_end->toDateString() >= $since30)->count(),
        ];

        $payload = [
            'success' => true,
            'page' => ['key' => $pageKey, 'label' => self::pageLabel($pageKey)],
            'today' => $today->format('D, j M Y'),
            'timezone' => self::TZ,
            'is_admin' => $isAdmin,
            'me' => $viewer ? ['id' => (int) $viewer->id, 'name' => (string) $viewer->name] : null,
            'assignments' => $rows,
            'history' => $history,
            'stats' => $stats,
        ];

        if ($isAdmin) {
            $payload['users'] = User::query()->active()->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u): array => ['id' => (int) $u->id, 'name' => (string) $u->name])->all();
        }

        return $payload;
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $out = '';
        foreach (array_slice(array_filter($parts), 0, 2) as $p) {
            $out .= mb_strtoupper(mb_substr($p, 0, 1));
        }

        return $out !== '' ? $out : '?';
    }

    /**
     * Assign people to a page, every selected person with every selected frequency.
     *
     * @param  list<int>  $userIds
     * @param  list<int>  $frequencies
     * @return array{created: int, reactivated: int, existing: int}
     */
    public function assign(string $pageKey, array $userIds, array $frequencies, ?User $by): array
    {
        $today = self::today()->toDateString();
        $created = 0;
        $reactivated = 0;
        $existing = 0;

        foreach (array_unique($userIds) as $uid) {
            foreach (array_unique($frequencies) as $freq) {
                $row = Assignment::query()
                    ->where('page_key', $pageKey)->where('user_id', $uid)->where('frequency_days', $freq)->first();
                if ($row && $row->active) {
                    $existing++;

                    continue;
                }
                if ($row) {
                    // Removed earlier: start a fresh period run from today so old gaps are not counted.
                    $row->update(['active' => true, 'start_date' => $today, 'assigned_by' => $by?->id, 'assignor_email' => $by?->email]);
                    $reactivated++;

                    continue;
                }
                Assignment::create([
                    'page_key' => $pageKey,
                    'user_id' => $uid,
                    'frequency_days' => $freq,
                    'start_date' => $today,
                    'assigned_by' => $by?->id,
                    'assignor_email' => $by?->email,
                    'active' => true,
                ]);
                $created++;
            }
        }

        return ['created' => $created, 'reactivated' => $reactivated, 'existing' => $existing];
    }
}
