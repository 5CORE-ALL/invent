<?php

namespace App\Services\Support\Concerns;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

/**
 * REST Admin API: spacing between calls, retries for 429 / 5xx / connection timeouts (cURL 28),
 * with exponential backoff 1s → 2s → 4s → 8s and Retry-After / X-Shopify-API-Call-Limit hints for 429.
 *
 * Each class using this trait shares static last-call timing (per PHP process).
 */
trait ShopifyAdminRateLimitRetry
{
    private static ?float $shopifyAdminLastCallEndedAt = null;

    /** Backoff seconds before retry attempts (attempt 0 → 1 → 2 → 3). */
    private function shopifyAdminRetryBackoffSeconds(): array
    {
        return [1, 2, 4, 8];
    }

    /**
     * Ensure at least 2 seconds between the end of the previous Shopify Admin request and the next one.
     */
    private function enforceShopifyApiSpacing(): void
    {
        if (self::$shopifyAdminLastCallEndedAt === null) {
            return;
        }
        $elapsed = microtime(true) - self::$shopifyAdminLastCallEndedAt;
        if ($elapsed < 2.0) {
            usleep((int) ((2.0 - $elapsed) * 1_000_000));
        }
    }

    private function markShopifyApiCallCompleted(): void
    {
        self::$shopifyAdminLastCallEndedAt = microtime(true);
    }

    /**
     * Extra wait hint from 429 response (Retry-After or exhausted call bucket).
     */
    private function shopifyAdditionalWaitSecondsFrom429(Response $response): float
    {
        $retryAfter = $response->header('Retry-After');
        if ($retryAfter !== null && $retryAfter !== '' && is_numeric($retryAfter)) {
            return min(120.0, max(0.0, (float) $retryAfter));
        }

        $callLimit = $response->header('X-Shopify-API-Call-Limit')
            ?? $response->header('X-Shopify-Shop-Api-Call-Limit');
        if (is_string($callLimit) && preg_match('/^(\d+)\s*\/\s*(\d+)$/', trim($callLimit), $m)) {
            $used = (int) $m[1];
            $limit = max(1, (int) $m[2]);
            if ($used >= $limit) {
                return 3.0;
            }
        }

        return 0.0;
    }

    private function isRetryableShopifyConnectionError(\Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        $msg = $e->getMessage();

        return str_contains($msg, 'cURL error 28')
            || str_contains($msg, 'Operation timed out')
            || str_contains($msg, 'Connection timed out')
            || str_contains($msg, 'timed out')
            || str_contains($msg, 'Could not resolve host');
    }

    /**
     * Retry HTTP calls on 429, 5xx, and connection/timeout errors (cURL 28) with exponential backoff.
     *
     * Default: 4 attempts (up to 3 retries) with delays 1s, 2s, 4s, 8s before each retry.
     *
     * @param  callable(): Response  $call
     * @param  float  $minSpacingSeconds  Minimum seconds between Shopify API calls.
     *                                    Pass 0.4 for bulk image POST/DELETE loops to avoid
     *                                    the default 2-second gap multiplying across many calls.
     */
    private function retryOnRateLimit(callable $call, int $maxRetries = 4, float $minSpacingSeconds = 2.0): Response
    {
        $backoff = $this->shopifyAdminRetryBackoffSeconds();
        $response = null;

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            try {
                // Enforce minimum spacing between Shopify Admin API calls
                if (self::$shopifyAdminLastCallEndedAt !== null) {
                    $elapsed = microtime(true) - self::$shopifyAdminLastCallEndedAt;
                    if ($elapsed < $minSpacingSeconds) {
                        usleep((int) (($minSpacingSeconds - $elapsed) * 1_000_000));
                    }
                }
                $response = $call();
                $this->markShopifyApiCallCompleted();
            } catch (\Throwable $e) {
                if ($attempt < $maxRetries - 1 && $this->isRetryableShopifyConnectionError($e)) {
                    $delay = (float) ($backoff[$attempt] ?? $backoff[3]);
                    Log::warning('Shopify Admin API: connection/timeout, retrying', [
                        'attempt' => $attempt + 1,
                        'max_retries' => $maxRetries,
                        'delay_seconds' => $delay,
                        'exception' => $e::class,
                        'message' => mb_substr($e->getMessage(), 0, 300),
                    ]);
                    usleep((int) ($delay * 1_000_000));

                    continue;
                }

                throw $e;
            }

            $status = $response->status();

            if ($status < 400) {
                return $response;
            }

            $retryableStatus = $status === 429 || ($status >= 500 && $status < 600);

            if (! $retryableStatus) {
                return $response;
            }

            if ($attempt === $maxRetries - 1) {
                Log::warning('Shopify Admin API: retryable HTTP status, max retries exhausted', [
                    'status' => $status,
                    'attempts' => $maxRetries,
                    'retry_after' => $response->header('Retry-After'),
                    'x_shopify_api_call_limit' => $response->header('X-Shopify-API-Call-Limit'),
                ]);

                return $response;
            }

            $delay = (float) ($backoff[$attempt] ?? $backoff[3]);
            if ($status === 429) {
                $delay = max($delay, $this->shopifyAdditionalWaitSecondsFrom429($response));
            }

            $jitterMicros = random_int(0, 500_000);

            Log::info('Shopify Admin API: backing off before retry', [
                'status' => $status,
                'wait_seconds' => $delay,
                'attempt' => $attempt + 1,
                'max_retries' => $maxRetries,
                'retry_after' => $response->header('Retry-After'),
                'x_shopify_api_call_limit' => $response->header('X-Shopify-API-Call-Limit'),
            ]);

            usleep((int) ($delay * 1_000_000) + $jitterMicros);
        }

        return $response;
    }
}
