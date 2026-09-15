<?php

namespace App\Services\MarketplaceManager;

use App\Models\ShopifySku;
use App\Models\TopDawgProduct;
use App\Services\ShopifyCatalogSyncService;
use App\Services\ShopifyPlsTokenService;
use App\Services\TopDawgApiService;
use App\Support\Marketplace\ListingChannelCounts;
use App\Support\Marketplace\ListingCountsEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Refresh marketplace listed-catalogs from live APIs before /missing-listing counts.
 * CP Master + INV + NRL rules stay in ListingCountsEngine — this only refreshes "is it listed".
 */
class MissingListingCatalogRefresh
{
    public const FRESH_TTL_MINUTES = 30;

    /**
     * @return array<string, string>
     */
    public function refreshApiChannelsFromCpMaster(): array
    {
        ListingCountsEngine::countUniverseSkus(true);

        $results = [];
        foreach ([
            'pls', 'topdawg', 'faire', 'macy', 'bestbuy',
        ] as $channel) {
            $results[$channel] = $this->refreshChannel($channel);
        }

        return $results;
    }

    public function refreshChannel(string $channel): string
    {
        $key = ListingChannelCounts::normalize($channel);
        if ($key === '') {
            return 'skipped';
        }

        $freshKey = 'ml.listed_catalog.fresh.'.$key;
        try {
            if (Cache::get($freshKey)) {
                return 'fresh';
            }
        } catch (\Throwable $e) {
            // continue
        }

        $lock = Cache::lock('ml.listed_catalog.lock.'.$key, 600);
        if (! $lock->get()) {
            return 'busy';
        }

        try {
            if (Cache::get($freshKey)) {
                return 'fresh';
            }

            $status = match ($key) {
                'pls' => $this->refreshPls(),
                'topdawg' => $this->refreshTopDawg(),
                'faire' => $this->refreshFaire(),
                'macy', 'macys' => $this->refreshMirakl('macy'),
                'bestbuy', 'bestbuyusa' => $this->refreshMirakl('bestbuy'),
                'shopify', 'shopifyb2c' => $this->refreshShopifyMain(),
                default => 'skipped',
            };

            if (in_array($status, ['ok', 'skipped', 'fresh'], true)) {
                Cache::put($freshKey, 1, now()->addMinutes(self::FRESH_TTL_MINUTES));
            }

            return $status;
        } finally {
            optional($lock)->release();
        }
    }

    protected function refreshPls(): string
    {
        if (! app(ShopifyPlsTokenService::class)->isConfigured()) {
            return 'skipped';
        }

        @set_time_limit(180);
        $result = app(ShopifyCatalogSyncService::class)->syncCatalog('pls');
        Log::info('MissingListingCatalogRefresh: PLS catalog', $result);

        return ! empty($result['completed']) || (int) ($result['variants'] ?? 0) > 0
            ? 'ok'
            : 'failed';
    }

    protected function refreshShopifyMain(): string
    {
        @set_time_limit(180);
        $result = app(ShopifyCatalogSyncService::class)->syncCatalog('main');
        Log::info('MissingListingCatalogRefresh: Shopify catalog', $result);

        return ! empty($result['completed']) || (int) ($result['variants'] ?? 0) > 0
            ? 'ok'
            : 'failed';
    }

    protected function refreshTopDawg(): string
    {
        $api = app(TopDawgApiService::class);
        if (! $api->isConfigured()) {
            return 'skipped';
        }

        @set_time_limit(180);
        $result = $api->fetchProducts(null);
        $items = is_array($result['data'] ?? null) ? $result['data'] : [];
        if ($items === []) {
            Log::warning('MissingListingCatalogRefresh: TopDawg API returned no products');

            return 'failed';
        }

        $seen = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $sku = ShopifySku::normalizeSkuForShopifyLookup((string) ($item['product_code'] ?? $item['sku'] ?? ''));
            if ($sku === '') {
                $sku = trim((string) ($item['product_code'] ?? $item['sku'] ?? ''));
            }
            if ($sku === '') {
                continue;
            }
            $state = TopDawgApiService::listingStateFromItem($item);
            $listingId = trim((string) ($item['id'] ?? $item['tdid'] ?? ''));
            $tdid = trim((string) ($item['tdid'] ?? ''));
            TopDawgProduct::query()->updateOrCreate(
                ['sku' => $sku],
                array_filter([
                    'topdawg_listing_id' => $listingId !== '' ? $listingId : null,
                    'tdid' => $tdid !== '' ? $tdid : null,
                    'listing_state' => $state,
                ], static fn ($v) => $v !== null)
            );
            $seen[strtoupper($sku)] = $sku;
        }

        if (count($seen) >= 20) {
            TopDawgProduct::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->get(['id', 'sku'])
                ->each(function (TopDawgProduct $row) use ($seen) {
                    $sku = trim((string) $row->sku);
                    $norm = strtoupper(ShopifySku::normalizeSkuForShopifyLookup($sku) ?: $sku);
                    if ($sku !== '' && ! isset($seen[$norm]) && ! isset($seen[strtoupper($sku)])) {
                        $row->delete();
                    }
                });
        }

        try {
            app(TopDawgLiveListingsService::class)->clearCache();
        } catch (\Throwable $e) {
            // ignore
        }

        Log::info('MissingListingCatalogRefresh: TopDawg products', ['count' => count($seen)]);

        return 'ok';
    }

    protected function refreshFaire(): string
    {
        Cache::forget('mm.faire.portal_status_page_v1');
        $deadline = microtime(true) + 180;
        $last = ['ok' => false];
        do {
            $last = app(FaireLinkMapSyncService::class)->syncListingStatuses(60);
            if (! ($last['ok'] ?? false)) {
                return 'failed';
            }
        } while (! ($last['done'] ?? false) && microtime(true) < $deadline);

        return ($last['ok'] ?? false) ? 'ok' : 'failed';
    }

    protected function refreshMirakl(string $channel): string
    {
        $result = app(MiraklMcmOfferStatusSync::class)->sync($channel, 180);

        return ($result['ok'] ?? false) ? 'ok' : 'failed';
    }
}
