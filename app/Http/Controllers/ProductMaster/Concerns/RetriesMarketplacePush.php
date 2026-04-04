<?php

namespace App\Http\Controllers\ProductMaster\Concerns;

use Illuminate\Support\Facades\Log;

trait RetriesMarketplacePush
{
    /**
     * Run a marketplace API push with automatic retries (transient failures, timeouts, rate limits).
     *
     * Max 3 retries (4 attempts total). Backoff before each retry: 2s, 4s, 6s.
     *
     * @param  callable(): array{success?: bool, message?: string}  $operation
     * @return array{success: bool, message: string, attempts: int, retried: bool}
     */
    protected function invokeMarketplacePushWithRetries(callable $operation, string $logContext, string $marketplace, string $sku): array
    {
        $maxAttempts = 4;
        $backoffSeconds = [2, 4, 6];
        $last = ['success' => false, 'message' => 'No attempt executed.'];

        for ($i = 0; $i < $maxAttempts; $i++) {
            try {
                $last = $operation();
                if (! is_array($last)) {
                    $last = ['success' => (bool) $last, 'message' => $last ? 'OK' : 'Failed'];
                }
                $ok = (bool) ($last['success'] ?? false);
                if ($ok) {
                    if ($i > 0) {
                        Log::info("{$logContext}: push succeeded after retry", [
                            'sku' => $sku,
                            'marketplace' => $marketplace,
                            'attempt' => $i + 1,
                        ]);
                    }

                    return [
                        'success' => true,
                        'message' => (string) ($last['message'] ?? 'Updated'),
                        'attempts' => $i + 1,
                        'retried' => $i > 0,
                    ];
                }
            } catch (\Throwable $e) {
                $last = ['success' => false, 'message' => $e->getMessage()];
                Log::warning("{$logContext}: push attempt threw", [
                    'sku' => $sku,
                    'marketplace' => $marketplace,
                    'attempt' => $i + 1,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($i < $maxAttempts - 1) {
                $wait = $backoffSeconds[$i] ?? 6;
                Log::warning("{$logContext}: push failed, retrying after {$wait}s", [
                    'sku' => $sku,
                    'marketplace' => $marketplace,
                    'attempt' => $i + 1,
                    'max_attempts' => $maxAttempts,
                    'message' => is_array($last) ? ($last['message'] ?? '') : '',
                ]);
                usleep($wait * 1_000_000);
            }
        }

        return [
            'success' => false,
            'message' => (string) ($last['message'] ?? 'Update failed after retries'),
            'attempts' => $maxAttempts,
            'retried' => true,
        ];
    }

    /**
     * Retries with configurable backoff delays (e.g. 2s, 4s, 8s for rate limits).
     *
     * @param  callable(): array{success?: bool, message?: string}  $operation
     * @param  list<int>  $backoffSeconds  Sleep before retry #1, #2, … (after failed attempt)
     * @return array{success: bool, message: string, attempts: int, retried: bool}
     */
    protected function invokeMarketplacePushWithCustomBackoff(callable $operation, string $logContext, string $marketplace, string $sku, array $backoffSeconds = [2, 4, 8]): array
    {
        $maxAttempts = count($backoffSeconds) + 1;
        $last = ['success' => false, 'message' => 'No attempt executed.'];

        for ($i = 0; $i < $maxAttempts; $i++) {
            try {
                $last = $operation();
                if (! is_array($last)) {
                    $last = ['success' => (bool) $last, 'message' => $last ? 'OK' : 'Failed'];
                }
                $ok = (bool) ($last['success'] ?? false);
                if ($ok) {
                    if ($i > 0) {
                        Log::info("{$logContext}: push succeeded after retry", [
                            'sku' => $sku,
                            'marketplace' => $marketplace,
                            'attempt' => $i + 1,
                        ]);
                    }

                    return [
                        'success' => true,
                        'message' => (string) ($last['message'] ?? 'Updated'),
                        'attempts' => $i + 1,
                        'retried' => $i > 0,
                    ];
                }
            } catch (\Throwable $e) {
                $last = ['success' => false, 'message' => $e->getMessage()];
                Log::warning("{$logContext}: push attempt threw", [
                    'sku' => $sku,
                    'marketplace' => $marketplace,
                    'attempt' => $i + 1,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($i < $maxAttempts - 1) {
                $wait = $backoffSeconds[$i] ?? 8;
                Log::warning("{$logContext}: push failed, retrying after {$wait}s", [
                    'sku' => $sku,
                    'marketplace' => $marketplace,
                    'attempt' => $i + 1,
                    'message' => is_array($last) ? ($last['message'] ?? '') : '',
                ]);
                sleep($wait);
            }
        }

        return [
            'success' => false,
            'message' => (string) ($last['message'] ?? 'Update failed after retries'),
            'attempts' => $maxAttempts,
            'retried' => true,
        ];
    }
}
