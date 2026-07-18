<?php

namespace App\Services\CronMonitor;

use App\Models\CronExecutionLog;

class HistoricalAnalysisService
{
    /**
     * @return array{
     *   anomalies: list<array{type: string, message: string, severity: string, meta?: array}>,
     *   summary: array<string, mixed>,
     *   score_factor: float
     * }
     */
    public function compare(CronExecutionContext $ctx): array
    {
        if (! config('cron-monitor.anomaly.enabled', true)) {
            return ['anomalies' => [], 'summary' => [], 'score_factor' => 1.0];
        }

        if (($ctx->meta['mode'] ?? null) === 'schedule' || ! empty($ctx->meta['auto'])) {
            return ['anomalies' => [], 'summary' => [], 'score_factor' => 1.0];
        }

        $baseline = CronExecutionLog::query()
            ->forJob($ctx->jobName)
            ->whereIn('status', [CronExecutionLog::STATUS_SUCCESS, CronExecutionLog::STATUS_RECOVERED])
            ->when($ctx->log?->id, fn ($q) => $q->where('id', '!=', $ctx->log->id))
            ->orderByDesc('finished_at')
            ->first();

        if (! $baseline) {
            return ['anomalies' => [], 'summary' => ['baseline' => null], 'score_factor' => 1.0];
        }

        $cfg = config('cron-monitor.anomaly', []);
        $anomalies = [];
        $minBaseline = (int) ($cfg['min_baseline_updates'] ?? 100);

        $baselineUpdates = (int) $baseline->updated_records + (int) $baseline->inserted_records;
        $currentUpdates = $ctx->effectiveUpdated();
        $summary = [
            'baseline_id' => $baseline->id,
            'baseline_updates' => $baselineUpdates,
            'current_updates' => $currentUpdates,
            'baseline_runtime' => $baseline->duration_seconds,
            'baseline_failed' => $baseline->failed_records,
            'baseline_latency' => $baseline->api_latency_ms_avg,
            'baseline_memory' => $baseline->memory_usage,
        ];

        if ($baselineUpdates >= $minBaseline && $baselineUpdates > 0) {
            $drop = (($baselineUpdates - $currentUpdates) / $baselineUpdates) * 100;
            if ($drop >= (float) ($cfg['update_drop_percent'] ?? 50)) {
                $anomalies[] = [
                    'type' => 'update_drop',
                    'severity' => 'critical',
                    'message' => sprintf(
                        'Update count decreased by %.1f%% (%d → %d).',
                        $drop,
                        $baselineUpdates,
                        $currentUpdates
                    ),
                    'meta' => compact('drop', 'baselineUpdates', 'currentUpdates'),
                ];
            }
        }

        $baselineFailRate = $this->failureRate($baseline);
        $currentFailRate = $ctx->processedRecords > 0
            ? ($ctx->failedRecords / $ctx->processedRecords) * 100
            : 0;
        $summary['baseline_failure_rate'] = $baselineFailRate;
        $summary['current_failure_rate'] = round($currentFailRate, 2);

        if ($baselineFailRate >= 0 && $currentFailRate - $baselineFailRate >= (float) ($cfg['failure_rate_increase_percent'] ?? 50)) {
            $anomalies[] = [
                'type' => 'failure_rate',
                'severity' => 'warning',
                'message' => sprintf(
                    'Failure rate rose from %.1f%% to %.1f%%.',
                    $baselineFailRate,
                    $currentFailRate
                ),
            ];
        }

        $baselineRuntime = (int) ($baseline->duration_seconds ?? 0);
        $currentRuntime = $ctx->log
            ? max(0, now()->diffInSeconds($ctx->log->started_at ?? now()))
            : 0;
        if ($baselineRuntime > 0 && $currentRuntime > 0) {
            $increase = (($currentRuntime - $baselineRuntime) / $baselineRuntime) * 100;
            $summary['runtime_increase_percent'] = round($increase, 1);
            if ($increase >= (float) ($cfg['runtime_increase_percent'] ?? 100)) {
                $anomalies[] = [
                    'type' => 'runtime',
                    'severity' => 'warning',
                    'message' => sprintf('Runtime increased %.1f%% (%ds → %ds).', $increase, $baselineRuntime, $currentRuntime),
                ];
            }
        }

        $baselineLatency = (int) ($baseline->api_latency_ms_avg ?? 0);
        $currentLatency = (int) ($ctx->averageApiLatencyMs() ?? 0);
        if ($baselineLatency > 0 && $currentLatency > 0) {
            $latInc = (($currentLatency - $baselineLatency) / $baselineLatency) * 100;
            $summary['latency_increase_percent'] = round($latInc, 1);
            if ($latInc >= (float) ($cfg['latency_increase_percent'] ?? 100)) {
                $anomalies[] = [
                    'type' => 'api_latency',
                    'severity' => 'warning',
                    'message' => sprintf('API latency increased %.1f%% (%dms → %dms).', $latInc, $baselineLatency, $currentLatency),
                ];
            }
        }

        $baselineMem = $this->parseMemoryMb((string) ($baseline->memory_usage ?? ''));
        $currentMem = $this->parseMemoryMb($this->guessCurrentMemory());
        if ($baselineMem > 0 && $currentMem > 0) {
            $memInc = (($currentMem - $baselineMem) / $baselineMem) * 100;
            $summary['memory_increase_percent'] = round($memInc, 1);
            if ($memInc >= (float) ($cfg['memory_increase_percent'] ?? 100)) {
                $anomalies[] = [
                    'type' => 'memory',
                    'severity' => 'warning',
                    'message' => sprintf('Memory increased %.1f%% (%.1fMB → %.1fMB).', $memInc, $baselineMem, $currentMem),
                ];
            }
        }

        $scoreFactor = 1.0;
        foreach ($anomalies as $a) {
            $scoreFactor -= ($a['severity'] ?? '') === 'critical' ? 0.15 : 0.05;
        }
        $scoreFactor = max(0.4, $scoreFactor);

        return [
            'anomalies' => $anomalies,
            'summary' => $summary,
            'score_factor' => $scoreFactor,
        ];
    }

    protected function failureRate(CronExecutionLog $log): float
    {
        $denom = max(1, (int) ($log->processed_records ?: $log->fetched_records ?: 1));

        return round(((int) $log->failed_records / $denom) * 100, 2);
    }

    protected function parseMemoryMb(string $value): float
    {
        if (preg_match('/([\d.]+)\s*MB/i', $value, $m)) {
            return (float) $m[1];
        }

        return 0.0;
    }

    protected function guessCurrentMemory(): string
    {
        return round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB';
    }
}
