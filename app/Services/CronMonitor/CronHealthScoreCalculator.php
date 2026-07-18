<?php

namespace App\Services\CronMonitor;

class CronHealthScoreCalculator
{
    /**
     * @param  array{score_factor?: float}|null  $historical
     * @return array{score: int, label: string, breakdown: array<string, int>}
     */
    public function calculate(CronExecutionContext $ctx, bool $validationPassed, ?array $historical = null): array
    {
        $weights = config('cron-monitor.health_score', []);
        $breakdown = [
            'cron_started' => ! empty($ctx->started) ? (int) ($weights['cron_started'] ?? 15) : 0,
            'api_successful' => $ctx->apiConnected ? (int) ($weights['api_successful'] ?? 15) : 0,
            'fetched_records' => $ctx->fetchedRecords > 0 ? (int) ($weights['fetched_records'] ?? 15) : 0,
            'updated_records' => $ctx->effectiveUpdated() > 0 ? (int) ($weights['updated_records'] ?? 15) : 0,
            'validation_passed' => $validationPassed ? (int) ($weights['validation_passed'] ?? 15) : 0,
            'retry_success' => 0,
            'runtime' => 0,
            'historical' => 0,
        ];

        if (
            ($ctx->expectedRecords === 0 || $ctx->expectedRecords === null)
            && $ctx->fetchedRecords === 0
            && $ctx->effectiveUpdated() === 0
            && $validationPassed
            && $ctx->apiConnected
        ) {
            $breakdown['fetched_records'] = (int) ($weights['fetched_records'] ?? 15);
            $breakdown['updated_records'] = (int) ($weights['updated_records'] ?? 15);
        }

        // Retry success: recovered after retries, or no retries needed
        $retryWeight = (int) ($weights['retry_success'] ?? 10);
        if ($ctx->retryCount === 0 && $validationPassed) {
            $breakdown['retry_success'] = $retryWeight;
        } elseif ($ctx->retryCount > 0 && $validationPassed) {
            $breakdown['retry_success'] = $retryWeight;
        }

        // Runtime vs expected / historical
        $runtimeWeight = (int) ($weights['runtime'] ?? 10);
        $expected = $ctx->log?->expected_runtime_seconds
            ?? (int) (($historical['summary']['baseline_runtime'] ?? 0));
        $actual = $ctx->log
            ? max(0, now()->diffInSeconds($ctx->log->started_at ?? now()))
            : 0;
        if ($expected <= 0 || $actual <= 0) {
            $breakdown['runtime'] = $validationPassed ? $runtimeWeight : 0;
        } elseif ($actual <= $expected * 1.5) {
            $breakdown['runtime'] = $runtimeWeight;
        } elseif ($actual <= $expected * 3) {
            $breakdown['runtime'] = (int) round($runtimeWeight / 2);
        }

        $histWeight = (int) ($weights['historical'] ?? 5);
        $factor = (float) ($historical['score_factor'] ?? 1.0);
        $breakdown['historical'] = (int) round($histWeight * $factor);

        $score = (int) min(100, array_sum($breakdown));
        $labels = $weights['labels'] ?? ['healthy' => 80, 'warning' => 50];

        $label = match (true) {
            ! $validationPassed => 'critical',
            $score >= (int) ($labels['healthy'] ?? 80) => 'healthy',
            $score >= (int) ($labels['warning'] ?? 50) => 'warning',
            default => 'critical',
        };

        return [
            'score' => $score,
            'label' => $label,
            'breakdown' => $breakdown,
        ];
    }
}
