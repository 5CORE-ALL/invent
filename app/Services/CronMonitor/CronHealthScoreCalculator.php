<?php

namespace App\Services\CronMonitor;

class CronHealthScoreCalculator
{
    /**
     * @return array{score: int, label: string, breakdown: array<string, int>}
     */
    public function calculate(CronExecutionContext $ctx, bool $validationPassed): array
    {
        $weights = config('cron-monitor.health_score', []);
        $breakdown = [
            'cron_started' => ! empty($ctx->started) ? (int) ($weights['cron_started'] ?? 20) : 0,
            'api_successful' => $ctx->apiConnected ? (int) ($weights['api_successful'] ?? 20) : 0,
            'fetched_records' => $ctx->fetchedRecords > 0 ? (int) ($weights['fetched_records'] ?? 20) : 0,
            'updated_records' => $ctx->effectiveUpdated() > 0 ? (int) ($weights['updated_records'] ?? 20) : 0,
            'validation_passed' => $validationPassed ? (int) ($weights['validation_passed'] ?? 20) : 0,
        ];

        // Allow expected-zero jobs to earn fetched/updated points when intentionally empty.
        if (
            ($ctx->expectedRecords === 0 || $ctx->expectedRecords === null)
            && $ctx->fetchedRecords === 0
            && $ctx->effectiveUpdated() === 0
            && $validationPassed
            && $ctx->apiConnected
        ) {
            $breakdown['fetched_records'] = (int) ($weights['fetched_records'] ?? 20);
            $breakdown['updated_records'] = (int) ($weights['updated_records'] ?? 20);
        }

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
