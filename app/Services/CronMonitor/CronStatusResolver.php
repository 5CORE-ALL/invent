<?php

namespace App\Services\CronMonitor;

use App\Models\CronExecutionLog;

class CronStatusResolver
{
    /**
     * @return array{status: string, success_percentage: float}
     */
    public function resolve(CronExecutionContext $ctx, bool $validationPassed, bool $hadException = false): array
    {
        // Kernel auto-monitor: process exit is the success signal
        if (($ctx->meta['mode'] ?? null) === 'schedule' || ! empty($ctx->meta['auto'])) {
            if ($hadException) {
                return [
                    'status' => CronExecutionLog::STATUS_FAILED,
                    'success_percentage' => 0.0,
                ];
            }

            return [
                'status' => CronExecutionLog::STATUS_SUCCESS,
                'success_percentage' => 100.0,
            ];
        }

        $denominator = $ctx->successDenominator();
        $effective = $ctx->effectiveUpdated();
        $percentage = round(($effective / max(1, $denominator)) * 100, 2);

        if ($hadException) {
            return [
                'status' => CronExecutionLog::STATUS_FAILED,
                'success_percentage' => $percentage,
            ];
        }

        if (! $validationPassed) {
            return [
                'status' => CronExecutionLog::STATUS_FAILED,
                'success_percentage' => $percentage,
            ];
        }

        $successMin = (float) config('cron-monitor.thresholds.success_min', 95);
        $partialMin = (float) config('cron-monitor.thresholds.partial_min', 60);

        $status = match (true) {
            $percentage >= $successMin => CronExecutionLog::STATUS_SUCCESS,
            $percentage >= $partialMin => CronExecutionLog::STATUS_PARTIAL_SUCCESS,
            default => CronExecutionLog::STATUS_FAILED,
        };

        if ($status === CronExecutionLog::STATUS_SUCCESS && $ctx->retryCount > 0) {
            $status = CronExecutionLog::STATUS_RECOVERED;
        }

        return [
            'status' => $status,
            'success_percentage' => $percentage,
        ];
    }
}
