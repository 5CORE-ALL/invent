<?php

namespace App\Services\MarketplaceManager;

use App\Jobs\PushLinkedSkuInventoryFromShopify;
use App\Models\MmWebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Webhook-independent fallback: ask 5Core Shopify only for inventory levels
 * changed since the last poll, compare with the ledger, and push only the
 * SKUs whose quantity really moved. If the webhook already handled a change,
 * the ledger matches and nothing is pushed twice.
 */
class ShopifyInventoryChangePoller
{
    public const SINCE_CACHE_KEY = 'mm.shopify.inv_poll.since';

    public const LAST_RUN_CACHE_KEY = 'mm.shopify.inv_poll.last_run';

    public const LOCATION_CACHE_KEY = 'mm.shopify.inv_poll.location_ids';

    private const API_VERSION = '2025-01';

    /** First run / stale cursor: never look back further than this. */
    private const MAX_LOOKBACK_MINUTES = 60;

    /** Re-read a little before the cursor so a slow Shopify write is not missed. */
    private const OVERLAP_SECONDS = 90;

    public function __construct(
        protected InventoryLedgerService $ledger,
    ) {}

    /**
     * @return array{ok: bool, levels: int, changed: int, pushed: int, skipped: int, unresolved: int, since: string, message: string}
     */
    public function run(): array
    {
        $startedAt = CarbonImmutable::now('UTC');
        $since = $this->sinceCursor($startedAt);
        $out = [
            'ok' => false,
            'levels' => 0,
            'changed' => 0,
            'pushed' => 0,
            'skipped' => 0,
            'unresolved' => 0,
            'since' => $since->toIso8601String(),
            'message' => '',
        ];

        [$host, $token] = $this->credentials();
        if ($host === '' || $token === '') {
            $out['message'] = 'Shopify credentials missing.';

            return $out;
        }

        $locationIds = $this->locationIds($host, $token);
        if ($locationIds === []) {
            $out['message'] = 'No Shopify location found.';

            return $out;
        }

        $levels = $this->fetchChangedLevels($host, $token, $locationIds, $since);
        if ($levels === null) {
            $out['message'] = 'Shopify inventory_levels request failed; cursor not advanced.';

            return $out;
        }
        $out['levels'] = count($levels);

        $changedSkus = [];
        foreach ($levels as $level) {
            $itemId = preg_replace('/\D+/', '', (string) ($level['inventory_item_id'] ?? '')) ?: '';
            if ($itemId === '' || ! array_key_exists('available', $level) || ! is_numeric($level['available'])) {
                continue;
            }
            $available = (int) $level['available'];
            $locationId = isset($level['location_id']) ? (string) $level['location_id'] : null;

            $skus = ShopifyInventoryWebhookResolver::skusForInventoryItemId($itemId);
            if ($skus === []) {
                $out['unresolved']++;

                continue;
            }

            $known = $this->ledger->qtyBySkus($skus);
            $unchanged = true;
            foreach ($skus as $sku) {
                $ledgerQty = $known[$sku] ?? $known[strtoupper($sku)] ?? null;
                if ($ledgerQty === null || (int) $ledgerQty !== max(0, $available)) {
                    $unchanged = false;
                    break;
                }
            }
            if ($unchanged) {
                $out['skipped']++;

                continue;
            }

            try {
                $this->ledger->applyWebhook($itemId, $available, $locationId, $skus, 'poll');
            } catch (\Throwable $e) {
                Log::warning('ShopifyInventoryChangePoller: ledger update failed', [
                    'inventory_item_id' => $itemId,
                    'error' => $e->getMessage(),
                ]);
            }
            foreach ($skus as $sku) {
                $changedSkus[$sku] = true;
            }
            $out['changed']++;
        }

        $changedSkus = array_keys($changedSkus);
        if ($changedSkus !== []) {
            $out['pushed'] = PushLinkedSkuInventoryFromShopify::dispatchToEnabled($changedSkus);
        }

        Cache::put(self::SINCE_CACHE_KEY, $startedAt->toIso8601String(), now()->addDays(7));
        Cache::put(self::LAST_RUN_CACHE_KEY, now()->toIso8601String(), now()->addDays(7));

        $out['ok'] = true;
        $out['message'] = sprintf(
            '%d changed level(s) since %s; %d SKU(s) queued to %d channel(s); %d unchanged; %d unresolved.',
            $out['levels'],
            $since->toDateTimeString(),
            count($changedSkus),
            $out['pushed'],
            $out['skipped'],
            $out['unresolved']
        );

        if ($out['changed'] > 0) {
            Log::info('ShopifyInventoryChangePoller: '.$out['message'], ['skus' => $changedSkus]);
        }

        return $out;
    }

    /**
     * Minutes since the last Shopify inventory webhook was received, or null
     * when none exists. Used by /map-issues to warn that real-time push is down.
     */
    public static function minutesSinceLastWebhook(): ?int
    {
        try {
            if (! Schema::hasTable('mm_webhook_events')) {
                return null;
            }
            $latest = MmWebhookEvent::query()
                ->where('source', 'shopify')
                ->whereIn('topic', ['inventory_levels/update', 'inventory_levels/connect'])
                ->max('created_at');
        } catch (\Throwable $e) {
            return null;
        }
        if (! $latest) {
            return null;
        }

        return (int) CarbonImmutable::parse((string) $latest)->diffInMinutes(now());
    }

    protected function sinceCursor(CarbonImmutable $now): CarbonImmutable
    {
        $floor = $now->subMinutes(self::MAX_LOOKBACK_MINUTES);
        try {
            $raw = Cache::get(self::SINCE_CACHE_KEY);
            if (is_string($raw) && $raw !== '') {
                $since = CarbonImmutable::parse($raw)->subSeconds(self::OVERLAP_SECONDS);

                return $since->greaterThan($floor) ? $since : $floor;
            }
        } catch (\Throwable $e) {
            // fall through
        }

        return $now->subMinutes(15);
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function credentials(): array
    {
        $host = (string) preg_replace('#^https?://#', '', rtrim((string) config('services.shopify.store_url'), '/'));
        $token = (string) (config('services.shopify.access_token') ?: config('services.shopify.password') ?: '');

        return [$host, $token];
    }

    /**
     * @return list<int>
     */
    protected function locationIds(string $host, string $token): array
    {
        $cached = Cache::get(self::LOCATION_CACHE_KEY);
        if (is_array($cached) && $cached !== []) {
            return array_values(array_map('intval', $cached));
        }

        try {
            $res = $this->http($token)->get("https://{$host}/admin/api/".self::API_VERSION.'/locations.json');
        } catch (\Throwable $e) {
            Log::warning('ShopifyInventoryChangePoller: locations request failed', ['error' => $e->getMessage()]);

            return [];
        }
        if (! $res->successful()) {
            return [];
        }

        $ids = [];
        foreach ($res->json('locations') ?? [] as $loc) {
            if (! is_array($loc) || (($loc['active'] ?? true) === false)) {
                continue;
            }
            $id = (int) ($loc['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_slice(array_values(array_unique($ids)), 0, 50);
        if ($ids !== []) {
            Cache::put(self::LOCATION_CACHE_KEY, $ids, now()->addHours(12));
        }

        return $ids;
    }

    /**
     * @param  list<int>  $locationIds
     * @return list<array<string, mixed>>|null null on request failure
     */
    protected function fetchChangedLevels(string $host, string $token, array $locationIds, CarbonImmutable $since): ?array
    {
        $url = "https://{$host}/admin/api/".self::API_VERSION.'/inventory_levels.json';
        $query = [
            'location_ids' => implode(',', $locationIds),
            'updated_at_min' => $since->toIso8601String(),
            'limit' => 250,
        ];

        $levels = [];
        for ($page = 0; $page < 40; $page++) {
            $response = null;
            for ($attempt = 1; $attempt <= 4; $attempt++) {
                try {
                    $response = $this->http($token)->get($url, $query);
                } catch (\Throwable $e) {
                    Log::warning('ShopifyInventoryChangePoller: inventory_levels request failed', ['error' => $e->getMessage()]);

                    return null;
                }
                if ($response->status() === 429) {
                    sleep(max(2, (int) ($response->header('Retry-After') ?: ($attempt * 2))));

                    continue;
                }
                break;
            }
            if (! $response || ! $response->successful()) {
                Log::warning('ShopifyInventoryChangePoller: inventory_levels page failed', [
                    'status' => $response ? $response->status() : null,
                    'body' => $response ? substr($response->body(), 0, 300) : null,
                ]);

                return null;
            }

            foreach ($response->json('inventory_levels') ?? [] as $level) {
                if (is_array($level)) {
                    $levels[] = $level;
                }
            }

            $next = $this->nextPageInfo((string) $response->header('Link'));
            if ($next === null) {
                break;
            }
            // Cursor pages only accept limit + page_info.
            $query = ['limit' => 250, 'page_info' => $next];
            usleep(250000);
        }

        return $levels;
    }

    protected function nextPageInfo(string $linkHeader): ?string
    {
        if ($linkHeader === '') {
            return null;
        }
        foreach (explode(',', $linkHeader) as $part) {
            if (! str_contains($part, 'rel="next"')) {
                continue;
            }
            if (preg_match('/page_info=([^&>]+)/', $part, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    protected function http(string $token): \Illuminate\Http\Client\PendingRequest
    {
        $http = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Content-Type' => 'application/json',
        ])->timeout(60)->connectTimeout(15);

        if (config('filesystems.default') === 'local' || env('FILESYSTEM_DRIVER') === 'local') {
            $http = $http->withoutVerifying();
        }

        return $http;
    }
}
