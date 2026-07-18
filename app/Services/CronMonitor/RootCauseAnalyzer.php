<?php

namespace App\Services\CronMonitor;

use App\Models\CronExecutionLog;

class RootCauseAnalyzer
{
    /**
     * @param  array{category: string, recoverable: bool, root_cause: string, http_status: int|null}  $classification
     * @param  list<array{message?: string}>  $anomalies
     */
    public function summarize(
        CronExecutionContext $ctx,
        array $classification,
        bool $recovered = false,
        array $anomalies = []
    ): string {
        $parts = [];

        if (! empty($classification['root_cause'])) {
            $parts[] = $classification['root_cause'];
        }

        if (! empty($classification['category'])) {
            $parts[] = 'Category: ' . $classification['category'];
        }

        if ($ctx->retryCount > 0) {
            $parts[] = $recovered
                ? "Retry succeeded after {$ctx->retryCount} attempt(s)"
                : "Retry attempts: {$ctx->retryCount}";
        }

        if (! empty($ctx->meta['healed'])) {
            $parts[] = 'Healing: ' . implode(', ', array_keys((array) $ctx->meta['healed']));
        }

        if ($anomalies !== []) {
            $parts[] = 'Anomalies: ' . collect($anomalies)->pluck('message')->filter()->take(2)->implode('; ');
        }

        if ($ctx->validationMessage ?? null) {
            // no-op — validation on log
        }

        return implode(' | ', array_filter($parts)) ?: 'No root cause identified';
    }

    public function applyToLog(CronExecutionLog $log, string $rootCause, ?string $category = null, ?string $recovery = null): void
    {
        $log->root_cause = $rootCause;
        if ($category) {
            $log->failure_category = $category;
        }
        if ($recovery) {
            $log->recovery_status = $recovery;
        }
    }
}
