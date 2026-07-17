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
    public function __construct(protected ScheduledJobRegistry $registry) {}

    /**
     * @return list<CronMonitorAlert>
     */
    public function run(): array
    {
        if (! config('cron-monitor.watchdog.enabled', true)) {
            return [];
        }

        $alerts = [];
        $alerts = array_merge($alerts, $this->checkStaleRunning());
        $alerts = array_merge($alerts, $this->checkWatchedJobs());

        return $alerts;
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
