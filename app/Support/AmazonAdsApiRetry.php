<?php

namespace App\Support;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Throwable;

/**
 * Safe retry/backoff for Amazon Ads pull/push/verify calls.
 */
final class AmazonAdsApiRetry
{
    /**
     * @template T
     * @param  callable(int): T  $fn  receives 1-based attempt
     * @param  list<int>  $delaysMs
     * @return array{ok: bool, value: T|null, error: string|null, attempts: int, errors: list<string>}
     */
    public static function run(callable $fn, int $maxAttempts = 5, array $delaysMs = [400, 800, 1600, 3200, 6400], ?callable $sleeper = null, bool $retryAll = false): array
    {
        $maxAttempts = max(1, $maxAttempts);
        $errors = [];
        $sleep = $sleeper ?? static function (int $ms): void {
            if ($ms > 0) {
                usleep($ms * 1000);
            }
        };

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return [
                    'ok' => true,
                    'value' => $fn($attempt),
                    'error' => null,
                    'attempts' => $attempt,
                    'errors' => $errors,
                ];
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
                if ($attempt >= $maxAttempts || (! $retryAll && ! self::isRetryable($e))) {
                    return [
                        'ok' => false,
                        'value' => null,
                        'error' => $e->getMessage(),
                        'attempts' => $attempt,
                        'errors' => $errors,
                    ];
                }
                $sleep(self::delayFor($e, $delaysMs, $attempt));
            }
        }

        return [
            'ok' => false,
            'value' => null,
            'error' => $errors[array_key_last($errors)] ?? 'retry exhausted',
            'attempts' => $maxAttempts,
            'errors' => $errors,
        ];
    }

    public static function isRetryable(Throwable $e): bool
    {
        if ($e instanceof ConnectException) {
            return true;
        }
        $code = self::httpStatus($e);
        if ($code === 429 || $code === 408) {
            return true;
        }
        if ($code >= 500 && $code <= 599) {
            return true;
        }
        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'timeout')
            || str_contains($msg, 'timed out')
            || str_contains($msg, 'rate')
            || str_contains($msg, 'too many')
            || str_contains($msg, 'temporarily')
            || str_contains($msg, 'connection')
            || str_contains($msg, '502')
            || str_contains($msg, '503')
            || str_contains($msg, '504')
            || str_contains($msg, 'bad gateway')
            || str_contains($msg, 'unavailable');
    }

    public static function httpStatus(Throwable $e): ?int
    {
        if ($e instanceof RequestException && $e->hasResponse()) {
            return $e->getResponse()->getStatusCode();
        }
        $code = (int) $e->getCode();

        return $code >= 400 && $code <= 599 ? $code : null;
    }

    /**
     * @param  list<int>  $delaysMs
     */
    public static function delayFor(Throwable $e, array $delaysMs, int $attempt): int
    {
        $retryAfter = self::retryAfterMs($e);
        if ($retryAfter !== null) {
            return min(60000, max(200, $retryAfter));
        }
        $idx = max(0, $attempt - 1);

        return $delaysMs[$idx] ?? ($delaysMs[array_key_last($delaysMs)] ?? 1000);
    }

    public static function retryAfterMs(Throwable $e): ?int
    {
        if (! $e instanceof RequestException || ! $e->hasResponse()) {
            return null;
        }
        $header = $e->getResponse()->getHeaderLine('Retry-After');
        if ($header === '') {
            return null;
        }
        if (is_numeric($header)) {
            return (int) round(((float) $header) * 1000);
        }

        return null;
    }

    public static function valuesMatch(?float $live, ?float $desired, float $tolerance): bool
    {
        if ($live === null || $desired === null) {
            return false;
        }
        if (! is_finite($live) || ! is_finite($desired)) {
            return false;
        }

        return abs($live - $desired) <= $tolerance;
    }
}
