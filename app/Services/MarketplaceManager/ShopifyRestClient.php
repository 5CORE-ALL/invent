<?php

namespace App\Services\MarketplaceManager;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Shopify REST calls for the fulfillment path, sharing one rate-limit budget per process.
 *
 * Every job on the store shares the app's REST bucket (40 calls, refilled at 2/s). One
 * tracking row used to make ~15 calls — the same order read up to five times, plus fulfillment
 * reads repeated per API version on every 429 — so the bucket stayed empty and most rows
 * ended as "Could not load the Shopify order". Here an order's reads are fetched once, calls
 * slow down before the bucket is full, and a 429 waits for Retry-After before trying again.
 */
final class ShopifyRestClient
{
    /** Reads reused for this long; any write from this process clears them. */
    public const MEMO_SECONDS = 30;

    /** Start pacing once this share of the bucket is used. */
    public const PACE_FROM = 0.6;

    /** @var array<string, array{at: float, response: Response}> */
    private static array $memo = [];

    /** @var array<string, array{used: int, max: int, at: float}> */
    private static array $bucket = [];

    private static ?int $lastStatus = null;

    public static function request(
        string $method,
        string $url,
        string $token,
        array $payload = [],
        int $timeout = 30,
        int $attempts = 4
    ): Response {
        $method = strtoupper($method);
        $host = (string) parse_url($url, PHP_URL_HOST);

        if ($method === 'GET') {
            $payload = self::memoQuery($url, $payload);
            $key = self::memoKey($url, $payload);
            if ($key !== null && isset(self::$memo[$key]) && microtime(true) - self::$memo[$key]['at'] < self::MEMO_SECONDS) {
                self::$lastStatus = self::$memo[$key]['response']->status();

                return self::$memo[$key]['response'];
            }
        } else {
            self::$memo = [];
            $key = null;
        }

        $attempts = max(1, $attempts);
        $response = null;
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            self::pace($host);
            try {
                $req = Http::withoutVerifying()->withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(max(3, $timeout));
                $response = match ($method) {
                    'POST' => $req->post($url, $payload),
                    'PUT' => $req->put($url, $payload),
                    'DELETE' => $req->delete($url, $payload),
                    default => $req->get($url, $payload),
                };
            } catch (\Throwable $e) {
                self::$lastStatus = null;
                if ($attempt >= $attempts) {
                    throw $e;
                }
                sleep(2 * $attempt);

                continue;
            }

            self::$lastStatus = $response->status();
            self::recordBucket($host, $response);
            if ($response->status() !== 429) {
                break;
            }
            self::$bucket[$host] = ['used' => 40, 'max' => 40, 'at' => microtime(true)];
            $wait = (float) ($response->header('Retry-After') ?: 2);
            usleep((int) (max(1.0, min(10.0, $wait)) * 1_000_000));
        }

        if ($key !== null && $response !== null && $response->successful()) {
            self::$memo[$key] = ['at' => microtime(true), 'response' => $response];
        }

        return $response;
    }

    /** HTTP status of the last call (null after a connection error). */
    public static function lastStatus(): ?int
    {
        return self::$lastStatus;
    }

    public static function isRateLimited(?int $status): bool
    {
        return $status === 429;
    }

    public static function forget(): void
    {
        self::$memo = [];
    }

    /**
     * Seconds to wait before the next call so the bucket keeps room for other jobs.
     */
    public static function paceSeconds(int $used, int $max, float $elapsed): float
    {
        if ($max <= 0) {
            return 0.0;
        }
        $leaked = $elapsed * ($max / 20);
        $now = max(0.0, $used - $leaked);
        $share = $now / $max;
        if ($share < self::PACE_FROM) {
            return 0.0;
        }

        return round(min(5.0, ($share - self::PACE_FROM) * 10), 2);
    }

    /**
     * Order reads share one cached copy: the field filter is dropped, a full order serves every caller.
     */
    private static function memoQuery(string $url, array $payload): array
    {
        if (preg_match('#/orders/\d+\.json$#', (string) parse_url($url, PHP_URL_PATH))) {
            unset($payload['fields']);
        }

        return $payload;
    }

    private static function memoKey(string $url, array $payload): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (! preg_match('#/(orders/\d+(\.json|/fulfillment_orders\.json|/fulfillments\.json)|fulfillment_orders/\d+/fulfillments\.json)$#', $path)) {
            return null;
        }
        $path = (string) preg_replace('#/admin/api/[^/]+/#', '/admin/api/*/', $path);
        ksort($payload);

        return parse_url($url, PHP_URL_HOST).$path.'?'.http_build_query($payload);
    }

    private static function pace(string $host): void
    {
        $b = self::$bucket[$host] ?? null;
        if ($b === null) {
            return;
        }
        $wait = self::paceSeconds($b['used'], $b['max'], microtime(true) - $b['at']);
        if ($wait > 0) {
            usleep((int) ($wait * 1_000_000));
        }
    }

    private static function recordBucket(string $host, Response $response): void
    {
        $limit = (string) $response->header('X-Shopify-Shop-Api-Call-Limit');
        if (preg_match('#^(\d+)/(\d+)$#', trim($limit), $m)) {
            self::$bucket[$host] = ['used' => (int) $m[1], 'max' => (int) $m[2], 'at' => microtime(true)];
        }
    }
}
