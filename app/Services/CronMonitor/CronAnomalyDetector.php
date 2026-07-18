<?php

namespace App\Services\CronMonitor;

use App\Models\CronExecutionLog;

class CronAnomalyDetector
{
    /**
     * @return list<array{type: string, message: string, severity: string, meta?: array}>
     */
    public function detect(CronExecutionContext $ctx, CronExecutionLog $current): array
    {
        if (! config('cron-monitor.anomaly.enabled', true)) {
            return [];
        }

        $baseline = CronExecutionLog::query()
            ->forJob($ctx->jobName)
            ->successful()
            ->where('id', '!=', $current->id)
            ->orderByDesc('finished_at')
            ->first();

        if (! $baseline) {
            return $this->detectAbsoluteAnomalies($ctx);
        }

        $cfg = config('cron-monitor.anomaly', []);
        $anomalies = $this->detectAbsoluteAnomalies($ctx);
        $minBaseline = (int) ($cfg['min_baseline_updates'] ?? 100);

        $baselineUpdates = (int) $baseline->updated_records + (int) $baseline->inserted_records;
        $currentUpdates = $ctx->effectiveUpdated();

        if ($baselineUpdates >= $minBaseline) {
            $dropPct = (float) ($cfg['update_drop_percent'] ?? 50);
            if ($baselineUpdates > 0) {
                $drop = (($baselineUpdates - $currentUpdates) / $baselineUpdates) * 100;
                if ($drop >= $dropPct) {
                    $anomalies[] = [
                        'type' => 'update_drop',
                        'severity' => 'critical',
                        'message' => sprintf(
                            'Update count dropped %.1f%% vs last success (%d → %d).',
                            $drop,
                            $baselineUpdates,
                            $currentUpdates
                        ),
                        'meta' => [
                            'baseline' => $baselineUpdates,
                            'current' => $currentUpdates,
                            'baseline_id' => $baseline->id,
                        ],
                    ];
                }
            }
        }

        $baselineFetched = (int) $baseline->fetched_records;
        if ($baselineFetched >= $minBaseline) {
            $fetchDropPct = (float) ($cfg['fetch_drop_percent'] ?? 50);
            if ($baselineFetched > 0) {
                $drop = (($baselineFetched - $ctx->fetchedRecords) / $baselineFetched) * 100;
                if ($drop >= $fetchDropPct) {
                    $anomalies[] = [
                        'type' => 'fetch_drop',
                        'severity' => 'warning',
                        'message' => sprintf(
                            'Fetched records dropped %.1f%% vs last success (%d → %d).',
                            $drop,
                            $baselineFetched,
                            $ctx->fetchedRecords
                        ),
                        'meta' => [
                            'baseline' => $baselineFetched,
                            'current' => $ctx->fetchedRecords,
                        ],
                    ];
                }
            }
        }

        $baselineRuntime = (int) ($baseline->duration_seconds ?? 0);
        $currentRuntime = (int) ($current->duration_seconds ?? 0);
        $runtimeIncreasePct = (float) ($cfg['runtime_increase_percent'] ?? 100);
        if ($baselineRuntime > 0 && $currentRuntime > 0) {
            $increase = (($currentRuntime - $baselineRuntime) / $baselineRuntime) * 100;
            if ($increase >= $runtimeIncreasePct) {
                $anomalies[] = [
                    'type' => 'runtime_exceeded',
                    'severity' => 'warning',
                    'message' => sprintf(
                        'Runtime increased %.1f%% vs last success (%ds → %ds).',
                        $increase,
                        $baselineRuntime,
                        $currentRuntime
                    ),
                    'meta' => [
                        'baseline' => $baselineRuntime,
                        'current' => $currentRuntime,
                    ],
                ];
            }
        }

        $baselineFailed = max(1, (int) $baseline->failed_records);
        $spikeMultiplier = (float) ($cfg['failure_spike_multiplier'] ?? 3);
        if ($ctx->failedRecords >= $baselineFailed * $spikeMultiplier && $ctx->failedRecords >= 10) {
            $anomalies[] = [
                'type' => 'failure_spike',
                'severity' => 'critical',
                'message' => sprintf(
                    'Failure spike: %d failed vs baseline %d (%.1fx).',
                    $ctx->failedRecords,
                    (int) $baseline->failed_records,
                    $ctx->failedRecords / $baselineFailed
                ),
            ];
        }

        return $anomalies;
    }

    /**
     * @return list<array{type: string, message: string, severity: string}>
     */
    protected function detectAbsoluteAnomalies(CronExecutionContext $ctx): array
    {
        // Schedule auto-monitor has no record metrics — skip zero-fetch/update noise
        if (($ctx->meta['mode'] ?? null) === 'schedule' || ! empty($ctx->meta['auto'])) {
            return [];
        }

        if (! empty($ctx->meta['dry_run'])) {
            return [];
        }

        $anomalies = [];

        if ($ctx->fetchedRecords === 0 && ($ctx->expectedRecords === null || $ctx->expectedRecords > 0)) {
            $anomalies[] = [
                'type' => 'zero_fetched',
                'severity' => 'critical',
                'message' => 'Zero records fetched.',
            ];
        }

        if ($ctx->effectiveUpdated() === 0 && ($ctx->expectedRecords === null || $ctx->expectedRecords > 0)) {
            $anomalies[] = [
                'type' => 'no_updates',
                'severity' => 'critical',
                'message' => 'Zero records updated.',
            ];
        }

        return $anomalies;
    }
}
