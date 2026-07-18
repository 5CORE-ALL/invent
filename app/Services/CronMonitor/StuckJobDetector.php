<?php

namespace App\Services\CronMonitor;

use App\Events\CronMonitor\CronTimedOut;
use App\Models\CronExecutionLog;
use App\Models\CronMonitorAlert;
use App\Repositories\CronExecutionLogRepository;

class StuckJobDetector
{
    public function __construct(
        protected ScheduledJobRegistry $registry,
        protected CronExecutionLogRepository $repository,
        protected DuplicateLockService $locks,
    ) {}

    /**
     * @return list<CronMonitorAlert>
     */
    public function detect(): array
    {
        if (! config('cron-monitor.stuck.enabled', true)) {
            return [];
        }

        $alerts = [];
        $multiplier = (float) config('cron-monitor.stuck.multiplier', 3.0);
        $minExpected = (int) config('cron-monitor.stuck.min_expected_seconds', 120);

        $running = CronExecutionLog::query()
            ->where('status', CronExecutionLog::STATUS_RUNNING)
            ->whereNotNull('started_at')
            ->get();

        foreach ($running as $log) {
            $expected = $this->expectedSeconds($log);
            if ($expected < $minExpected) {
                $expected = max($expected, $minExpected);
            }

            $actual = now()->diffInSeconds($log->started_at);
            if ($actual < $expected * $multiplier) {
                continue;
            }

            $log->update([
                'status' => CronExecutionLog::STATUS_STUCK,
                'expected_runtime_seconds' => $expected,
                'duration_seconds' => $actual,
                'root_cause' => sprintf(
                    'Stuck: expected ~%ds, running %ds (%.1fx)',
                    $expected,
                    $actual,
                    $expected > 0 ? $actual / $expected : 0
                ),
                'error_message' => 'Job exceeded expected runtime threshold.',
            ]);

            if (config('cron-monitor.watchdog.auto_unlock_stuck', false) && $log->command) {
                $this->locks->forceRelease($log->command);
            }

            $alert = CronMonitorAlert::create([
                'execution_log_id' => $log->id,
                'job_name' => $log->job_name,
                'alert_type' => 'stuck',
                'severity' => 'critical',
                'title' => "Stuck cron: {$log->job_name}",
                'message' => $log->root_cause,
                'payload' => [
                    'expected_runtime_seconds' => $expected,
                    'duration_seconds' => $actual,
                    'command' => $log->command,
                ],
            ]);
            $alerts[] = $alert;
            event(new CronTimedOut($log, $alert));
        }

        return $alerts;
    }

    protected function expectedSeconds(CronExecutionLog $log): int
    {
        if ($log->expected_runtime_seconds) {
            return (int) $log->expected_runtime_seconds;
        }

        $avg = $this->repository->averageRuntime($log->job_name);
        if ($avg) {
            return (int) ceil($avg);
        }

        if ($log->command) {
            $jobs = $this->registry->jobs();
            $cfg = $jobs[$log->command] ?? null;
            if ($cfg && ! empty($cfg['timeout_minutes'])) {
                return (int) $cfg['timeout_minutes'] * 60;
            }
        }

        return (int) config('cron-monitor.timeouts.default_minutes', 120) * 60;
    }
}
