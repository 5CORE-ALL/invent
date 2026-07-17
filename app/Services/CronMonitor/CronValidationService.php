<?php

namespace App\Services\CronMonitor;

class CronValidationService
{
    /**
     * @return array{passed: bool, messages: list<string>}
     */
    public function validate(CronExecutionContext $ctx): array
    {
        $rules = config('cron-monitor.validation', []);
        $messages = [];

        // Kernel auto-monitor: only require the process to finish without exception.
        // Rich metrics apply when the command opts into MonitoredCommand.
        if (($ctx->meta['mode'] ?? null) === 'schedule' || ! empty($ctx->meta['auto'])) {
            return ['passed' => true, 'messages' => []];
        }

        $allowZero = (bool) ($rules['allow_zero_when_expected_zero'] ?? true);
        $intentionallyEmpty = $allowZero && $ctx->expectedRecords === 0;

        if (($rules['require_api_data'] ?? true) && ! $ctx->apiConnected && ! $intentionallyEmpty) {
            $messages[] = 'API did not connect or return a successful response.';
        }

        if (($rules['require_fetched'] ?? true) && $ctx->fetchedRecords <= 0 && ! $intentionallyEmpty) {
            $messages[] = 'No records were fetched.';
        }

        if (($rules['require_processed'] ?? true) && $ctx->processedRecords <= 0 && $ctx->fetchedRecords > 0) {
            $messages[] = 'Records were fetched but none were processed.';
        }

        if (($rules['require_updates'] ?? true) && $ctx->effectiveUpdated() <= 0 && ! $intentionallyEmpty) {
            $messages[] = 'No records were updated or inserted (zero updates).';
        }

        $minRatio = (float) ($rules['min_update_ratio_vs_expected'] ?? 0.60);
        if (
            $ctx->expectedRecords !== null
            && $ctx->expectedRecords > 0
            && $minRatio > 0
        ) {
            $ratio = $ctx->effectiveUpdated() / $ctx->expectedRecords;
            if ($ratio < $minRatio) {
                $pct = round($ratio * 100, 1);
                $messages[] = sprintf(
                    'Only %s%% updated (%d of %d expected). Below minimum ratio of %s%%.',
                    $pct,
                    $ctx->effectiveUpdated(),
                    $ctx->expectedRecords,
                    round($minRatio * 100, 1)
                );
            }
        }

        return [
            'passed' => $messages === [],
            'messages' => $messages,
        ];
    }
}
