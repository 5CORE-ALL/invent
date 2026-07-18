<?php

namespace App\Services\CronMonitor;

use Illuminate\Database\QueryException;
use Throwable;

class FailureClassifier
{
    public const CATEGORY_API = 'api';
    public const CATEGORY_DATABASE = 'database';
    public const CATEGORY_VALIDATION = 'validation';
    public const CATEGORY_AUTHENTICATION = 'authentication';
    public const CATEGORY_TIMEOUT = 'timeout';
    public const CATEGORY_RATE_LIMIT = 'rate_limit';
    public const CATEGORY_NETWORK = 'network';
    public const CATEGORY_LOGIC = 'logic';
    public const CATEGORY_UNKNOWN = 'unknown';

    /**
     * @return array{
     *   category: string,
     *   recoverable: bool,
     *   root_cause: string,
     *   http_status: int|null
     * }
     */
    public function classify(
        ?Throwable $exception = null,
        ?string $message = null,
        ?int $httpStatus = null,
        mixed $apiResponse = null
    ): array {
        $httpStatus ??= $this->extractHttpStatus($exception, $apiResponse);
        $text = strtolower(trim(($message ?? '') . ' ' . ($exception?->getMessage() ?? '')));

        if ($httpStatus === 429 || str_contains($text, 'rate limit') || str_contains($text, 'too many requests')) {
            return $this->result(self::CATEGORY_RATE_LIMIT, true, 'HTTP 429 Rate Limit', $httpStatus);
        }

        if (in_array($httpStatus, [401, 403], true) || $this->containsAny($text, ['unauthorized', 'forbidden', 'invalid token', 'token expired', 'authentication'])) {
            $recoverable = str_contains($text, 'expired') || str_contains($text, 'refresh');
            return $this->result(
                self::CATEGORY_AUTHENTICATION,
                $recoverable,
                $httpStatus ? "HTTP {$httpStatus} Authentication" : 'Authentication failure',
                $httpStatus
            );
        }

        if (in_array($httpStatus, [500, 502, 503, 504], true)) {
            return $this->result(self::CATEGORY_API, true, "HTTP {$httpStatus} Upstream error", $httpStatus);
        }

        if (
            $httpStatus === 408
            || $this->containsAny($text, ['timeout', 'timed out', 'curl error 28', 'operation timed out'])
        ) {
            return $this->result(self::CATEGORY_TIMEOUT, true, $httpStatus ? "HTTP {$httpStatus} Timeout" : 'API / network timeout', $httpStatus);
        }

        if (
            $this->containsAny($text, ['connection refused', 'could not resolve', 'network', 'ssl', 'curl error', 'failed to connect'])
        ) {
            return $this->result(self::CATEGORY_NETWORK, true, 'Network error', $httpStatus);
        }

        if (
            $exception instanceof QueryException
            || $this->containsAny($text, ['sqlstate', 'deadlock', 'lost connection', 'server has gone away', 'database', 'pdo'])
        ) {
            return $this->result(self::CATEGORY_DATABASE, true, 'Database connectivity / deadlock', $httpStatus);
        }

        if ($this->containsAny($text, ['file lock', 'resource temporarily unavailable', 'try again'])) {
            return $this->result(self::CATEGORY_API, true, 'Temporary lock / resource busy', $httpStatus);
        }

        if ($this->containsAny($text, [
            'validation',
            'invalid sku',
            'product not found',
            'not found',
            'business rule',
            'unprocessable',
        ]) || $httpStatus === 422 || $httpStatus === 404) {
            return $this->result(
                self::CATEGORY_VALIDATION,
                false,
                $httpStatus ? "HTTP {$httpStatus} Validation / not found" : 'Validation or business rule violation',
                $httpStatus
            );
        }

        if ($httpStatus !== null && $httpStatus >= 400) {
            $recoverable = in_array($httpStatus, config('cron-monitor.retry.recoverable_http', []), true);

            return $this->result(
                self::CATEGORY_API,
                $recoverable,
                "HTTP {$httpStatus}",
                $httpStatus
            );
        }

        if ($exception) {
            return $this->result(self::CATEGORY_LOGIC, false, class_basename($exception) . ': ' . $exception->getMessage(), $httpStatus);
        }

        return $this->result(self::CATEGORY_UNKNOWN, false, $message ?: 'Unknown failure', $httpStatus);
    }

    public function isRecoverable(string $category, ?int $httpStatus = null): bool
    {
        $non = config('cron-monitor.retry.non_recoverable_categories', []);
        if (in_array($category, $non, true)) {
            return false;
        }

        if ($httpStatus !== null && in_array($httpStatus, config('cron-monitor.retry.recoverable_http', []), true)) {
            return true;
        }

        return in_array($category, config('cron-monitor.retry.recoverable_categories', []), true);
    }

    protected function extractHttpStatus(?Throwable $exception, mixed $apiResponse): ?int
    {
        if (is_array($apiResponse)) {
            foreach (['status', 'statusCode', 'http_status', 'code'] as $key) {
                if (isset($apiResponse[$key]) && is_numeric($apiResponse[$key])) {
                    $code = (int) $apiResponse[$key];
                    if ($code >= 100 && $code < 600) {
                        return $code;
                    }
                }
            }
        }

        if ($exception && method_exists($exception, 'getCode')) {
            $code = (int) $exception->getCode();
            if ($code >= 100 && $code < 600) {
                return $code;
            }
        }

        if ($exception && preg_match('/\b([45]\d{2})\b/', $exception->getMessage(), $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * @param  list<string>  $needles
     */
    protected function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{category: string, recoverable: bool, root_cause: string, http_status: int|null}
     */
    protected function result(string $category, bool $recoverable, string $rootCause, ?int $httpStatus): array
    {
        // Honour config overrides for category recoverability
        if (in_array($category, config('cron-monitor.retry.non_recoverable_categories', []), true)) {
            $recoverable = false;
        } elseif (in_array($category, config('cron-monitor.retry.recoverable_categories', []), true)) {
            $recoverable = true;
        }

        return [
            'category' => $category,
            'recoverable' => $recoverable,
            'root_cause' => $rootCause,
            'http_status' => $httpStatus,
        ];
    }
}
