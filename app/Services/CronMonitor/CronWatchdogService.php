<?php

namespace App\Services\CronMonitor;

use App\Events\CronMonitor\CronMissed;
use App\Events\CronMonitor\CronTimedOut;
use App\Models\CronExecutionLog;
use App\Models\CronMonitorAlert;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CronWatchdogService
{
    public function __construct(
        protected ScheduledJobRegistry $registry,
        protected StuckJobDetector $stuckDetector,
    ) {}

    /**
     * @return list<CronMonitorAlert>
     */
    public function run(): array
    {
        if (! config('cron-monitor.watchdog.enabled', true)) {
            return [];
        }

        $alerts = [];
        // First: close runs whose process is gone, so they are not reported later as "stuck" / "timed out".
        $alerts = array_merge($alerts, $this->reapAbandonedRunning());
        $alerts = array_merge($alerts, $this->stuckDetector->detect());
        $alerts = array_merge($alerts, $this->checkStaleRunning());
        $alerts = array_merge($alerts, $this->checkWatchedJobs());

        return $alerts;
    }

    /**
     * A `running` log whose process no longer exists (crash, kill -9, restart) can never finish by itself.
     * Close it as `interrupted` based on the real process state. A run that is genuinely still processing
     * (process alive) is never touched here. Only logs of this host are judged: another host's pid is unknown.
     *
     * @return list<CronMonitorAlert>
     */
    protected function reapAbandonedRunning(): array
    {
        $grace = (int) config('cron-monitor.leases.abandoned_grace_seconds', 120);
        $host = gethostname() ?: php_uname('n');
        $alerts = [];

        $candidates = CronExecutionLog::query()
            ->where('status', CronExecutionLog::STATUS_RUNNING)
            ->whereNotNull('pid')
            ->where('execution_server', $host)
            ->where('started_at', '<=', now()->subSeconds($grace))
            ->get();

        foreach ($candidates as $log) {
            if (self::processAlive((int) $log->pid)) {
                continue;
            }

            $duration = $log->started_at ? now()->diffInSeconds($log->started_at) : null;
            // Atomic: only the run that is still `running` is closed (never one that just finished).
            $closed = CronExecutionLog::query()
                ->where('id', $log->id)
                ->where('status', CronExecutionLog::STATUS_RUNNING)
                ->update([
                    'status' => CronExecutionLog::STATUS_INTERRUPTED,
                    'finished_at' => now(),
                    'duration_seconds' => $duration,
                    'error_message' => "Process {$log->pid} is gone (crash, kill or restart) — closed by watchdog.",
                    'root_cause' => 'Interrupted: process ended without finishing.',
                    'updated_at' => now(),
                ]);
            if ($closed !== 1) {
                continue;
            }

            Log::warning('[CronMonitor] Abandoned run closed as interrupted', [
                'job' => $log->job_name, 'log_id' => $log->id, 'pid' => $log->pid, 'ran_for_s' => $duration,
            ]);
            $alerts[] = $this->storeAlert(
                $log->refresh(),
                'interrupted',
                'warning',
                "Interrupted: {$log->job_name}",
                "Execution #{$log->id} (pid {$log->pid}) ended without finishing; the next scheduled run takes over."
            );
        }

        return $alerts;
    }

    /** True unless `$pid` is provably gone (no such process, zombie, or reused by a non-PHP program). */
    public static function processAlive(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }
        if (is_dir('/proc')) {
            $cmdline = @file_get_contents("/proc/{$pid}/cmdline");
            if ($cmdline === false || $cmdline === '') {
                return false; // no such process, or a zombie
            }
            // The pid exists. A PHP process (artisan, queue worker, php-fpm) is treated as the run itself;
            // a pid reused by a non-PHP program means our process is gone.
            return str_contains($cmdline, 'php') || str_contains($cmdline, 'artisan');
        }
        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }

        return true; // cannot tell: never close a run we cannot prove is dead
    }

    /**
     * @return list<CronMonitorAlert>
     */
    protected function checkStaleRunning(): array
    {
        $staleMinutes = (int) config('cron-monitor.timeouts.stale_running_minutes', 180);
        $cutoff = now()->subMinutes($staleMinutes);
        $alerts = [];

        $stale = CronExecutionLog::query()
            ->where('status', CronExecutionLog::STATUS_RUNNING)
            ->where('started_at', '<=', $cutoff)
            ->get();

        foreach ($stale as $log) {
            $log->update([
                'status' => CronExecutionLog::STATUS_TIMED_OUT,
                'finished_at' => now(),
                'duration_seconds' => $log->started_at
                    ? now()->diffInSeconds($log->started_at)
                    : null,
                'error_message' => "Marked timed out by watchdog after {$staleMinutes} minutes.",
                'validation_message' => 'Cron still running past stale threshold.',
            ]);

            $alert = $this->storeAlert(
                $log,
                'timed_out',
                'critical',
                "Cron timed out: {$log->job_name}",
                "Execution #{$log->id} exceeded {$staleMinutes} minutes without finishing."
            );
            $alerts[] = $alert;
            event(new CronTimedOut($log, $alert));
        }

        return $alerts;
    }

    /**
     * @return list<CronMonitorAlert>
     */
    protected function checkWatchedJobs(): array
    {
        // Auto-discover all Kernel scheduled artisan commands + config overrides
        $watched = $this->registry->watchedJobsForWatchdog();
        $skipSchedules = config('cron-monitor.watchdog.skip_miss_schedules', [
            'every_minute',
            'every_five_minutes',
            'every_ten_minutes',
        ]);
        $alerts = [];

        foreach ($watched as $command => $cfg) {
            $jobName = $cfg['job_name'] ?? $command;
            $timezone = $cfg['timezone'] ?? config('app.timezone', 'UTC');
            $grace = (int) ($cfg['grace_minutes'] ?? config('cron-monitor.watchdog.grace_minutes', 30));
            $timeout = (int) ($cfg['timeout_minutes'] ?? config('cron-monitor.timeouts.default_minutes', 120));
            $scheduleType = $cfg['schedule'] ?? 'daily';

            // Job-specific timeout for currently running
            $running = CronExecutionLog::query()
                ->where(function ($q) use ($jobName, $command) {
                    $q->where('job_name', $jobName)->orWhere('command', $command);
                })
                ->where('status', CronExecutionLog::STATUS_RUNNING)
                ->where('started_at', '<=', now()->subMinutes($timeout))
                ->first();

            if ($running) {
                $running->update([
                    'status' => CronExecutionLog::STATUS_TIMED_OUT,
                    'finished_at' => now(),
                    'duration_seconds' => $running->started_at
                        ? now()->diffInSeconds($running->started_at)
                        : null,
                    'error_message' => "Timed out after {$timeout} minutes (job-specific).",
                ]);
                $alert = $this->storeAlert(
                    $running,
                    'timed_out',
                    'critical',
                    "Cron timed out: {$jobName}",
                    "Still running past configured timeout of {$timeout} minutes."
                );
                $alerts[] = $alert;
                event(new CronTimedOut($running, $alert));
            }

            // High-frequency jobs: still timeout-check above, but skip "missed" spam
            if (in_array($scheduleType, $skipSchedules, true)) {
                continue;
            }

            $windowStart = $this->expectedWindowStart($cfg, $timezone);
            if (! $windowStart) {
                continue;
            }

            $deadline = $windowStart->copy()->addMinutes($grace);
            if (now($timezone)->lt($deadline)) {
                continue;
            }

            $ran = CronExecutionLog::query()
                ->where(function ($q) use ($jobName, $command) {
                    $q->where('job_name', $jobName)->orWhere('command', $command);
                })
                ->where('started_at', '>=', $windowStart->copy()->timezone(config('app.timezone')))
                ->exists();

            if ($ran) {
                continue;
            }

            // Avoid duplicate miss alerts for the same window
            $already = CronMonitorAlert::query()
                ->where('job_name', $jobName)
                ->where('alert_type', 'cron_missed')
                ->where('created_at', '>=', $windowStart)
                ->exists();

            if ($already) {
                continue;
            }

            $missLog = CronExecutionLog::create([
                'job_name' => $jobName,
                'command' => $command,
                'status' => CronExecutionLog::STATUS_MISSED,
                'started_at' => $windowStart,
                'finished_at' => now(),
                'duration_seconds' => 0,
                'validation_message' => 'Cron did not start within expected window.',
                'error_message' => "Expected around {$windowStart->toDateTimeString()} ({$timezone}).",
                'execution_server' => gethostname() ?: php_uname('n'),
            ]);

            $alert = $this->storeAlert(
                $missLog,
                'cron_missed',
                'critical',
                "Cron missed: {$jobName}",
                "No execution found after expected start {$windowStart->toDateTimeString()} ({$timezone})."
            );
            $alerts[] = $alert;
            event(new CronMissed($missLog, $alert));

            Log::warning('[CronMonitor] Missed cron', [
                'job' => $jobName,
                'expected' => $windowStart->toDateTimeString(),
            ]);
        }

        return $alerts;
    }

    protected function expectedWindowStart(array $cfg, string $timezone): ?Carbon
    {
        $now = Carbon::now($timezone);
        $schedule = $cfg['schedule'] ?? 'daily';
        $expectedAt = $cfg['expected_at'] ?? null;

        return match ($schedule) {
            'every_minute' => $now->copy()->subMinute()->startOfMinute(),
            'every_five_minutes' => $now->copy()->subMinutes(5)->startOfMinute(),
            'every_ten_minutes' => $now->copy()->subMinutes(10)->startOfMinute(),
            'hourly' => $now->copy()->subHour()->startOfHour(),
            'daily' => $this->dailyWindow($now, $expectedAt),
            'weekly' => $this->weeklyWindow($now, $expectedAt, (int) ($cfg['day_of_week'] ?? 1)),
            default => $expectedAt ? $this->dailyWindow($now, $expectedAt) : null,
        };
    }

    protected function dailyWindow(Carbon $now, ?string $expectedAt): ?Carbon
    {
        if (! $expectedAt) {
            return $now->copy()->subDay()->startOfDay();
        }

        [$h, $m] = array_pad(explode(':', $expectedAt), 2, 0);
        $today = $now->copy()->setTime((int) $h, (int) $m, 0);

        return $now->lt($today) ? $today->copy()->subDay() : $today;
    }

    protected function weeklyWindow(Carbon $now, ?string $expectedAt, int $dayOfWeek): ?Carbon
    {
        [$h, $m] = $expectedAt
            ? array_pad(explode(':', $expectedAt), 2, 0)
            : [0, 0];

        $candidate = $now->copy()->startOfWeek(Carbon::SUNDAY)->addDays($dayOfWeek)
            ->setTime((int) $h, (int) $m, 0);

        if ($now->lt($candidate)) {
            $candidate->subWeek();
        }

        return $candidate;
    }

    protected function storeAlert(
        CronExecutionLog $log,
        string $type,
        string $severity,
        string $title,
        string $message
    ): CronMonitorAlert {
        return CronMonitorAlert::create([
            'execution_log_id' => $log->id,
            'job_name' => $log->job_name,
            'alert_type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'payload' => [
                'status' => $log->status,
                'command' => $log->command,
            ],
        ]);
    }
}
