<?php

namespace App\Services\Support\Concerns;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

/**
 * REST Admin API rate limiting: spacing between calls, 429 retries with backoff + jitter,
 * and parsing of Retry-After / X-Shopify-API-Call-Limit.
 *
 * Each class using this trait gets its own static last-call timestamp (per store/service).
 */
trait ShopifyAdminRateLimitRetry
{
    private static ?float $shopifyAdminLastCallEndedAt = null;

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
     * Extra wait hint from 429 response (Retry-Call or exhausted call bucket).
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

    /**
     * Retry HTTP calls on 429 with exponential backoff, jitter, and header hints.
     *
     * @param  callable(): Response  $call
     */
    private function retryOnRateLimit(callable $call, int $maxRetries = 5): Response
    {
        $baseDelaySeconds = 3;
        $response = null;

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $this->enforceShopifyApiSpacing();
            $response = $call();
            $this->markShopifyApiCallCompleted();

            if ($response->status() !== 429) {
                return $response;
            }

            if ($attempt === $maxRetries - 1) {
                Log::warning('Shopify Admin API: rate limited (429), max retries exhausted', [
                    'attempts' => $maxRetries,
                    'retry_after' => $response->header('Retry-After'),
                    'x_shopify_api_call_limit' => $response->header('X-Shopify-API-Call-Limit'),
                ]);
                break;
            }

            $exponential = $baseDelaySeconds * (2 ** $attempt);
            $fromHeaders = $this->shopifyAdditionalWaitSecondsFrom429($response);
            $jitterSeconds = random_int(0, 2);
            $waitSeconds = max($exponential, $fromHeaders) + $jitterSeconds;

            Log::info('Shopify Admin API: rate limited (429), backing off before retry', [
                'wait_seconds' => round($waitSeconds, 2),
                'attempt' => $attempt + 1,
                'max_retries' => $maxRetries,
                'exponential_base' => $exponential,
                'from_headers' => $fromHeaders,
                'jitter_seconds' => $jitterSeconds,
                'retry_after' => $response->header('Retry-After'),
                'x_shopify_api_call_limit' => $response->header('X-Shopify-API-Call-Limit'),
            ]);

            usleep((int) ($waitSeconds * 1_000_000));
        }

        return $response;
    }
}
