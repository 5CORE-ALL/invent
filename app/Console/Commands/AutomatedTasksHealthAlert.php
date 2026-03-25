<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutomatedTasksHealthAlert extends Command
{
    protected $signature = 'tasks:automated-health-alert {--title= : Diagnose specific automated task title} {--email= : Optional user email visibility check}';
    protected $description = 'Lightweight alert for missing automated task instances';
    private const DAILY_GENERATION_TIME = '12:01';

    public function handle(): int
    {
        $now = Carbon::now('Asia/Kolkata');
        $currentTime = $now->format('H:i');
        $dayStart = $now->copy()->startOfDay();
        $dayEnd = $now->copy()->endOfDay();
        $currentDow = strtolower($now->format('D'));
        $currentDom = (string) ((int) $now->format('j'));
        $lastDom = (int) $now->copy()->endOfMonth()->format('j');
        $isEom = ((int) $currentDom) === $lastDom;
        $titleFilter = trim((string) $this->option('title'));
        $emailFilter = trim((string) $this->option('email'));

        try {
            DB::statement("SET time_zone = '+05:30'");
        } catch (\Throwable $e) {
            // Continue silently; alert still works with app timezone values.
        }

        if ($titleFilter !== '') {
            return $this->diagnoseSpecificTitle(
                $titleFilter,
                $emailFilter,
                $currentTime,
                $currentDow,
                $currentDom,
                $isEom,
                $dayStart,
                $dayEnd
            );
        }

        $activeAutomated = DB::table('automate_tasks')
            ->whereNotIn('status', ['Done', 'Archived'])
            ->get();
        $inactiveCount = DB::table('automate_tasks')
            ->whereIn('status', ['Done', 'Archived'])
            ->count();

        $missingDaily = [];
        $missingDue = [];

        foreach ($activeAutomated as $task) {
            $scheduleType = strtolower((string) ($task->schedule_type ?? ''));
            $hasTodayInstance = DB::table('tasks')
                ->where('automate_task_id', $task->id)
                ->whereBetween('start_date', [$dayStart, $dayEnd])
                ->whereNull('deleted_at')
                ->exists();

            $isDailyDueWindow = $currentTime >= self::DAILY_GENERATION_TIME;
            if ($scheduleType === 'daily' && !$hasTodayInstance && $isDailyDueWindow) {
                $missingDaily[] = ['id' => $task->id, 'title' => (string) $task->title];
                continue;
            }

            if (!$hasTodayInstance && in_array($scheduleType, ['weekly', 'monthly'], true) && $this->isDueNow($task, $currentTime, $currentDow, $currentDom, $isEom)) {
                $missingDue[] = ['id' => $task->id, 'title' => (string) $task->title, 'type' => $scheduleType];
            }
        }

        $totalMissing = count($missingDaily) + count($missingDue);
        if ($totalMissing === 0) {
            $this->info('Automated health OK: no missing active tasks. Inactive templates skipped: ' . $inactiveCount . '.');
            return 0;
        }

        $dailySample = array_slice(array_map(fn ($t) => "{$t['id']}:{$t['title']}", $missingDaily), 0, 5);
        $dueSample = array_slice(array_map(fn ($t) => "{$t['id']}:{$t['title']} ({$t['type']})", $missingDue), 0, 5);

        Log::warning('Automated task health alert: missing instances detected', [
            'timestamp_ist' => $now->toDateTimeString(),
            'missing_daily_count' => count($missingDaily),
            'missing_due_weekly_monthly_count' => count($missingDue),
            'missing_daily_sample' => $dailySample,
            'missing_due_sample' => $dueSample,
        ]);

        $this->warn('Automated health alert raised. Check logs for details.');
        return 1;
    }

    private function diagnoseSpecificTitle(
        string $titleFilter,
        string $emailFilter,
        string $currentTime,
        string $currentDow,
        string $currentDom,
        bool $isEom,
        Carbon $dayStart,
        Carbon $dayEnd
    ): int {
        $matches = DB::table('automate_tasks')
            ->where('title', 'like', '%' . $titleFilter . '%')
            ->orderBy('id')
            ->get();

        if ($matches->isEmpty()) {
            $this->warn("No automated task template found for title filter: {$titleFilter}");
            return 1;
        }

        $this->info("Found {$matches->count()} automate_tasks match(es) for: {$titleFilter}");
        foreach ($matches as $task) {
            $scheduleType = strtolower((string) ($task->schedule_type ?? ''));
            $hasTodayInstance = DB::table('tasks')
                ->where('automate_task_id', $task->id)
                ->whereBetween('start_date', [$dayStart, $dayEnd])
                ->whereNull('deleted_at')
                ->exists();

            $dailyDue = $scheduleType === 'daily' ? ($currentTime >= self::DAILY_GENERATION_TIME) : false;
            $dueNow = $scheduleType === 'daily'
                ? $dailyDue
                : $this->isDueNow($task, $currentTime, $currentDow, $currentDom, $isEom);

            $isActive = !in_array((string) ($task->status ?? ''), ['Done', 'Archived'], true);

            $visibleForEmail = null;
            if ($emailFilter !== '' && $hasTodayInstance) {
                $visibleForEmail = DB::table('tasks')
                    ->where('automate_task_id', $task->id)
                    ->whereBetween('start_date', [$dayStart, $dayEnd])
                    ->whereNull('deleted_at')
                    ->where(function ($q) use ($emailFilter) {
                        $q->where('assignor', $emailFilter)
                            ->orWhere('assign_to', 'like', '%' . $emailFilter . '%');
                    })
                    ->exists();
            }

            $this->line(sprintf(
                '[%d] "%s" | type=%s | time=%s | days=%s | status=%s | active=%s | due_now=%s | has_today_instance=%s',
                $task->id,
                (string) $task->title,
                $scheduleType ?: '-',
                (string) ($task->schedule_time ?? '-'),
                (string) ($task->schedule_days ?? '-'),
                (string) ($task->status ?? '-'),
                $isActive ? 'yes' : 'no',
                $dueNow ? 'yes' : 'no',
                $hasTodayInstance ? 'yes' : 'no'
            ));

            if ($emailFilter !== '') {
                $this->line('   visibility_for_' . $emailFilter . ': ' . ($visibleForEmail ? 'visible' : 'not-visible'));
            }
        }

        return 0;
    }

    private function isDueNow(object $task, string $currentTime, string $currentDow, string $currentDom, bool $isEom): bool
    {
        $scheduleTimeRaw = (string) ($task->schedule_time ?? '');
        if ($scheduleTimeRaw === '') {
            return false;
        }

        $taskTime = Carbon::parse($scheduleTimeRaw)->format('H:i');
        if ($currentTime < $taskTime) {
            return false;
        }

        $scheduleType = strtolower((string) ($task->schedule_type ?? ''));
        $days = array_filter(array_map('trim', explode(',', (string) ($task->schedule_days ?? ''))));

        if ($scheduleType === 'weekly') {
            $normalizedDays = array_map(function ($d) {
                $d = strtolower($d);
                return strlen($d) >= 3 ? substr($d, 0, 3) : $d;
            }, $days);
            return in_array($currentDow, $normalizedDays, true);
        }

        if ($scheduleType === 'monthly') {
            $normalizedDates = array_map(function ($d) {
                $u = strtoupper($d);
                if ($u === 'EOM') {
                    return 'EOM';
                }
                return (string) ((int) $u);
            }, $days);
            return in_array($currentDom, $normalizedDates, true) || (in_array('EOM', $normalizedDates, true) && $isEom);
        }

        return false;
    }
}
