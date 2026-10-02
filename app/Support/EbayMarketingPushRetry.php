<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Up to five tries for an eBay Marketing write. A rejected access token is
 * dropped and minted again from the refresh token before the next try.
 */
final class EbayMarketingPushRetry
{
    public const MAX_ATTEMPTS = 5;

    /** @var list<int> */
    private const DELAYS_MS = [400, 800, 1600, 3200, 6400];

    private string $token = '';

    /** @var callable(int): void|null */
    public $sleeper = null;

    public function __construct(private readonly object $api) {}

    public function token(): string
    {
        return $this->token;
    }

    /**
     * @return array{ok: bool, response: Response|null, error: string|null, attempts: int}
     */
    public function post(string $url, array $body): array
    {
        $lastError = 'Push failed';
        $lastResponse = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $lastResponse = Http::withToken($this->token)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->timeout(60)
                    ->post($url, $body);
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                $lastResponse = null;
                if ($attempt >= self::MAX_ATTEMPTS || ! self::isTransient($lastError, 0)) {
                    return $this->failed($lastError, $attempt, null);
                }
                $this->pause($attempt, $lastError);

                continue;
            }

            if ($lastResponse->successful()) {
                return [
                    'ok' => true,
                    'response' => $lastResponse,
                    'error' => null,
                    'attempts' => $attempt,
                ];
            }

            $reason = self::reasonFromResponse($lastResponse);
            $lastError = $reason;
            if (self::isTokenFailure($lastResponse->status(), $reason)) {
                try {
                    $this->revive();
                } catch (Throwable $e) {
                    return $this->failed('Token error: '.$e->getMessage(), $attempt, $lastResponse);
                }
                if ($attempt >= self::MAX_ATTEMPTS) {
                    return $this->failed($reason, $attempt, $lastResponse);
                }
                Log::warning('eBay marketing push retrying after token refresh', [
                    'attempt' => $attempt,
                    'url' => $url,
                ]);

                continue;
            }

            if ($attempt >= self::MAX_ATTEMPTS || ! self::isTransient($reason, $lastResponse->status())) {
                return $this->failed($reason, $attempt, $lastResponse);
            }
            $this->pause($attempt, $reason);
        }

        return $this->failed($lastError, self::MAX_ATTEMPTS, $lastResponse);
    }

    public function acquireToken(): string
    {
        $last = 'Token error';
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $minted = $this->api->generateBearerToken();
                $this->token = is_string($minted) ? $minted : '';
                if ($this->token === '') {
                    throw new \RuntimeException('No access token returned from eBay.');
                }

                return $this->token;
            } catch (Throwable $e) {
                $last = $e->getMessage();
                if ($attempt >= self::MAX_ATTEMPTS || self::isDeadRefreshToken($last)) {
                    throw new \RuntimeException($last, 0, $e);
                }
                $this->pause($attempt, $last);
            }
        }

        throw new \RuntimeException($last);
    }

    public static function isTokenFailure(int $status, string $reason): bool
    {
        if ($status === 401) {
            return true;
        }
        $blob = strtolower($reason);

        return str_contains($blob, 'invalid token')
            || str_contains($blob, 'invalid_token')
            || str_contains($blob, 'token expired')
            || str_contains($blob, 'token has expired')
            || str_contains($blob, 'auth token')
            || str_contains($blob, 'iaf token')
            || str_contains($blob, 'unauthorized');
    }

    public static function isDeadRefreshToken(string $reason): bool
    {
        $blob = strtolower($reason);

        return str_contains($blob, 'invalid_grant')
            || str_contains($blob, 'refresh token expired')
            || str_contains($blob, 'refresh token may be expired')
            || str_contains($blob, 're-authorize')
            || str_contains($blob, 'credentials not configured');
    }

    public static function isTransient(string $reason, int $status): bool
    {
        if ($status === 408 || $status === 429 || ($status >= 500 && $status <= 599)) {
            return true;
        }
        $blob = strtolower($reason);
        foreach (['timeout', 'timed out', 'rate limit', 'too many', 'temporarily', 'connection', 'bad gateway', 'unavailable'] as $needle) {
            if (str_contains($blob, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function reasonFromResponse(Response $response): string
    {
        $body = $response->json();
        $status = $response->status();
        if (! is_array($body)) {
            return (string) $status;
        }
        $msg = $body['errors'][0]['message']
            ?? $body['errors'][0]['longMessage']
            ?? $body['message']
            ?? null;

        return $msg ? ($status.': '.$msg) : (string) $status;
    }

    private function revive(): void
    {
        if (method_exists($this->api, 'forgetBearerToken')) {
            $this->api->forgetBearerToken();
        }
        $minted = $this->api->generateBearerToken();
        $this->token = is_string($minted) ? $minted : '';
        if ($this->token === '') {
            throw new \RuntimeException('No access token returned from eBay.');
        }
    }

    /**
     * @return array{ok: bool, response: Response|null, error: string, attempts: int}
     */
    private function failed(string $error, int $attempts, ?Response $response): array
    {
        if ($attempts > 1 && ! str_contains($error, 'attempt')) {
            $error .= ' after '.$attempts.' attempts';
        }

        return [
            'ok' => false,
            'response' => $response,
            'error' => $error,
            'attempts' => $attempts,
        ];
    }

    private function pause(int $attempt, string $reason): void
    {
        Log::warning('eBay marketing push retry', [
            'attempt' => $attempt,
            'reason' => $reason,
        ]);
        $ms = self::DELAYS_MS[min($attempt - 1, count(self::DELAYS_MS) - 1)];
        if ($this->sleeper !== null) {
            ($this->sleeper)($ms);

            return;
        }
        usleep($ms * 1000);
    }
}
