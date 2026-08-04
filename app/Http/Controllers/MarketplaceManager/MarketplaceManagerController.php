<?php

namespace App\Http\Controllers\MarketplaceManager;

use App\Http\Controllers\Controller;
use App\Jobs\WarmShopifyLiveCatalogCache;
use App\Models\AlibabaMetric;
use App\Models\AliexpressListingStatus;
use App\Models\AliexpressMetric;
use App\Models\ChannelMaster;
use App\Models\MarketplaceSyncSettings;
use App\Models\FaireMetric;
use App\Models\NeweggMetric;
use App\Models\ReverbMetric;
use App\Models\SheinMmMetric;
use App\Models\AmazonListingStatus;
use App\Models\EbayMetric;
use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\TopDawgProduct;
use App\Models\TemuMetric;
use App\Models\Temu2Metric;
use App\Models\TikTokProductTwo;
use App\Models\PLSProduct;
use App\Models\ReverbProduct;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\ShopifyLiveVerifiedCatalogService;
use App\Services\Support\MarketplaceApiConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class MarketplaceManagerController extends Controller
{
    public function __construct(
        protected MarketplaceApiConfigService $apiConfig
    ) {}

    public function index(): View
    {
        $channels = $this->buildIndexRows();
        $catalog = app(ShopifyLiveVerifiedCatalogService::class);

        return view('marketplace-manager.index', [
            'title' => 'Marketplace Manager',
            'channels' => $channels,
            'mpChannelCount' => collect($channels)->where('mp_is_active', true)->count(),
            'shopifySkuCount' => $catalog->countDistinctAllSkus(),
            'shopifyActiveSkuCount' => $catalog->countDistinctActiveSkus(),
            'shopifyCatalogSyncedAt' => $catalog->latestSyncedAt(),
            'shopifyRefreshStatus' => Cache::get(WarmShopifyLiveCatalogCache::STATUS_CACHE_KEY),
        ]);
    }

    /**
     * One row per Active Channels Master channel (/all-marketplace-master),
     * with Marketplace Manager fields filled when a registry match exists.
     * Unmatched enabled manager channels (e.g. Inactive Alibaba) are appended.
     *
     * @return list<array<string, mixed>>
     */
    protected function buildIndexRows(): array
    {
        $mpRows = $this->allChannelMasterRows();
        $managerByMpKey = $this->managerChannelsByMpKey();
        $usedManagerSlugs = [];
        $rows = [];

        // All Active Channels Master names first (same list as /all-marketplace-master).
        foreach ($mpRows as $mp) {
            if (! $mp['is_active']) {
                continue;
            }

            $key = strtolower($mp['channel']);
            $manager = $managerByMpKey[$key] ?? null;
            if ($manager !== null) {
                $usedManagerSlugs[$manager['slug']] = true;
            }

            $rows[] = $this->mergeMpAndManagerRow($mp, $manager);
        }

        // Keep manager-only rows that are not Active on all-marketplace-master.
        foreach (MarketplaceManagerRegistry::channels() as $manager) {
            if (! ($manager['enabled'] ?? false)) {
                continue;
            }
            if (isset($usedManagerSlugs[$manager['slug']])) {
                continue;
            }

            $mpMatch = $this->resolveMpChannel($manager['mp_channel_keys'] ?? [], $this->channelMasterIndexFromRows($mpRows));
            $rows[] = $this->mergeMpAndManagerRow($mpMatch, $manager);
        }

        return $rows;
    }

    /**
     * @param  array{channel: string, alias: ?string, missing_link: ?string, is_active: bool}|null  $mp
     * @param  array<string, mixed>|null  $manager
     * @return array<string, mixed>
     */
    protected function mergeMpAndManagerRow(?array $mp, ?array $manager): array
    {
        $slug = $manager['slug'] ?? null;

        $row = [
            'slug' => $slug,
            'label' => $manager['label'] ?? null,
            'short' => $manager['short'] ?? null,
            'source_shop' => $manager['source_shop'] ?? null,
            'logo' => $manager['logo'] ?? null,
            'enabled' => (bool) ($manager['enabled'] ?? false),
            'has_manager' => $manager !== null,
            'connected' => $slug ? $this->apiConfig->isConfigured($slug) : false,
            'listings_count' => $slug ? $this->listingCount($slug) : 0,
            'sync_settings' => $slug ? $this->syncSettingsFor($slug) : [
                'inventory' => ['inventory_sync' => false],
                'order' => ['fetch_orders' => false, 'auto_import_to_shopify' => false],
            ],
            'mp_channel' => $mp['channel'] ?? null,
            'mp_alias' => $mp['alias'] ?? null,
            'mp_missing_link' => $mp['missing_link'] ?? null,
            'mp_is_active' => (bool) ($mp['is_active'] ?? false),
        ];

        return $row;
    }

    /**
     * All channel_master rows (active first), keyed lookup helpers.
     *
     * @return list<array{channel: string, alias: ?string, missing_link: ?string, is_active: bool}>
     */
    protected function allChannelMasterRows(): array
    {
        if (! Schema::hasTable('channel_master')) {
            return [];
        }

        $select = ['channel', 'status'];
        if (Schema::hasColumn('channel_master', 'alias')) {
            $select[] = 'alias';
        }
        if (Schema::hasColumn('channel_master', 'missing_link')) {
            $select[] = 'missing_link';
        }

        $rows = ChannelMaster::query()
            ->whereNotNull('channel')
            ->where('channel', '!=', '')
            ->orderBy('channel')
            ->get($select);

        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $name = trim((string) $row->channel);
            $key = strtolower($name);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'channel' => $name,
                'alias' => isset($row->alias) ? (trim((string) $row->alias) ?: null) : null,
                'missing_link' => isset($row->missing_link) ? (trim((string) $row->missing_link) ?: null) : null,
                'is_active' => strtolower(trim((string) ($row->status ?? ''))) === 'active',
            ];
        }

        return $out;
    }

    /**
     * Map lowercased channel_master name → enabled Marketplace Manager registry channel.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function managerChannelsByMpKey(): array
    {
        $map = [];
        foreach (MarketplaceManagerRegistry::channels() as $channel) {
            if (! ($channel['enabled'] ?? false)) {
                continue;
            }
            foreach (($channel['mp_channel_keys'] ?? []) as $candidate) {
                $key = strtolower(trim((string) $candidate));
                if ($key === '' || isset($map[$key])) {
                    continue;
                }
                $map[$key] = $channel;
            }
        }

        return $map;
    }

    /**
     * @param  list<array{channel: string, alias: ?string, missing_link: ?string, is_active: bool}>  $rows
     * @return array<string, array{channel: string, alias: ?string, missing_link: ?string, is_active: bool}>
     */
    protected function channelMasterIndexFromRows(array $rows): array
    {
        $index = [];
        foreach ($rows as $row) {
            $key = strtolower($row['channel']);
            if ($key === '' || isset($index[$key])) {
                continue;
            }
            $index[$key] = $row;
        }

        return $index;
    }

    /**
     * @param  list<string>  $candidateKeys
     * @param  array<string, array{channel: string, alias: ?string, missing_link: ?string, is_active: bool}>  $index
     * @return array{channel: string, alias: ?string, missing_link: ?string, is_active: bool}|null
     */
    protected function resolveMpChannel(array $candidateKeys, array $index): ?array
    {
        $activeMatch = null;
        $anyMatch = null;

        foreach ($candidateKeys as $candidate) {
            $key = strtolower(trim((string) $candidate));
            if ($key === '' || ! isset($index[$key])) {
                continue;
            }

            $match = $index[$key];
            $anyMatch ??= $match;
            if ($match['is_active']) {
                $activeMatch = $match;
                break;
            }
        }

        return $activeMatch ?? $anyMatch;
    }

    /**
     * Shared Shopify live master refresh (once for all marketplaces).
     */
    public function refreshShopify(): RedirectResponse
    {
        WarmShopifyLiveCatalogCache::dispatch();
        Cache::put(WarmShopifyLiveCatalogCache::STATUS_CACHE_KEY, [
            'status' => 'queued',
            'queued_at' => now()->toDateTimeString(),
        ], 3600);

        return redirect()
            ->route('marketplace.manager.index')
            ->with('success', 'Shopify live catalog refresh queued. SKUs + qty will update for all marketplaces shortly.');
    }

    public function refreshShopifyStatus(): JsonResponse
    {
        $catalog = app(ShopifyLiveVerifiedCatalogService::class);

        return response()->json([
            'status' => Cache::get(WarmShopifyLiveCatalogCache::STATUS_CACHE_KEY),
            'sku_count' => $catalog->countDistinctAllSkus(),
            'active_sku_count' => $catalog->countDistinctActiveSkus(),
            'synced_at' => $catalog->latestSyncedAt(),
        ]);
    }

    public function activeShopifySkus(Request $request): View
    {
        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        $search = trim((string) $request->query('q', ''));
        $status = strtolower(trim((string) $request->query('status', 'all')));
        if (! in_array($status, ['all', 'active', 'active_in_stock', 'active_oos', 'draft', 'archived', 'unlisted'], true)) {
            $status = 'all';
        }

        $statusCounts = $catalog->distinctSkuCountsByStatus();
        $rows = $catalog->tablesReady()
            ? $catalog->paginateSkuRows($search, $status, 50)
            : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 50);

        return view('marketplace-manager.active-shopify-skus', [
            'title' => 'Shopify Live SKUs',
            'rows' => $rows,
            'search' => $search,
            'status' => $status,
            'statusCounts' => $statusCounts,
            'activeCount' => $statusCounts['active'] ?? 0,
            'allCount' => $statusCounts['all'] ?? 0,
            'syncedAt' => $catalog->latestSyncedAt(),
        ]);
    }

    public function show(string $marketplace): View
    {
        $marketplace = strtolower($marketplace);
        $channel = MarketplaceManagerRegistry::find($marketplace);

        if ($channel === null) {
            abort(404, 'Marketplace not found');
        }

        return view('marketplace-manager.show', [
            'title' => $channel['label'].' — Marketplace Manager',
            'channel' => $channel,
            'connected' => $this->apiConfig->isConfigured($marketplace),
            'listings_count' => $this->listingCount($marketplace),
            'settings' => $this->syncSettingsFor($marketplace),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function syncSettingsFor(string $slug): array
    {
        return MarketplaceSyncSettings::getFor($slug);
    }

    protected function listingCount(string $slug): int
    {
        return match ($slug) {
            'aliexpress' => Schema::hasTable('aliexpress_metric')
                ? (int) AliexpressMetric::query()->whereNotNull('sku')->count()
                : (Schema::hasTable('aliexpress_listing_statuses')
                    ? (int) AliexpressListingStatus::query()->count()
                    : 0),
            'alibaba' => Schema::hasTable('alibaba_metrics')
                ? (int) AlibabaMetric::query()->whereNotNull('sku')->count()
                : 0,
            'reverb' => Schema::hasTable('reverb_metric')
                ? (int) ReverbMetric::query()->whereNotNull('sku')->count()
                : (Schema::hasTable('reverb_products')
                    ? (int) ReverbProduct::query()->whereNotNull('sku')->where('sku', 'not like', '%Parent%')->count()
                    : 0),
            'newegg' => Schema::hasTable('newegg_metric')
                ? (int) NeweggMetric::query()->whereNotNull('sku')->count()
                : 0,
            'shein' => Schema::hasTable('shein_metric')
                ? (int) SheinMmMetric::query()->whereNotNull('sku')->whereNotNull('product_id')->whereColumn('sku', '!=', 'product_id')->count()
                : 0,
            'amazon' => Schema::hasTable('amazon_listing_statuses')
                ? (int) AmazonListingStatus::query()->whereNotNull('sku')->where('sku', '!=', '')->count()
                : 0,
            'topdawg' => Schema::hasTable('topdawg_products')
                ? (int) TopDawgProduct::query()->whereNotNull('sku')->whereNotNull('topdawg_listing_id')->whereColumn('topdawg_listing_id', '!=', 'sku')->count()
                : 0,
            'temu' => Schema::hasTable('temu_metrics')
                ? (int) TemuMetric::query()->whereNotNull('goods_id')->whereNotNull('sku')->where('sku', '!=', '')->whereColumn('sku', '!=', 'goods_id')->count()
                : 0,
            'temu2' => Schema::hasTable('temu2_metrics')
                ? (int) Temu2Metric::query()->whereNotNull('goods_id')->whereNotNull('sku')->where('sku', '!=', '')->whereColumn('sku', '!=', 'goods_id')->count()
                : 0,
            'ebay1' => Schema::hasTable('ebay_metrics')
                ? (int) EbayMetric::query()->whereNotNull('sku')->whereNotNull('item_id')->whereColumn('item_id', '!=', 'sku')->count()
                : 0,
            'ebay2' => Schema::hasTable('ebay_2_metrics')
                ? (int) Ebay2Metric::query()->whereNotNull('sku')->whereNotNull('item_id')->whereColumn('item_id', '!=', 'sku')->count()
                : 0,
            'ebay3' => Schema::hasTable('ebay_3_metrics')
                ? (int) Ebay3Metric::query()->whereNotNull('sku')->whereNotNull('item_id')->whereColumn('item_id', '!=', 'sku')->count()
                : 0,
            'faire' => Schema::hasTable('faire_metric')
                ? (int) FaireMetric::query()->whereNotNull('sku')->count()
                : 0,
            'purchasingpower' => Schema::hasTable('purchasing_power_products')
                ? (int) \App\Models\PurchasingPowerProduct::query()->whereNotNull('sku')->where('sku', '!=', '')->count()
                : 0,
            'wayfair' => Schema::hasTable('wayfair_pricing_prices')
                ? (int) \App\Models\WayfairPricingPrice::query()->whereNotNull('sku')->where('sku', '!=', '')->count()
                : 0,
            'bestbuy' => Schema::hasTable('bestbuy_usa_products')
                ? (int) \App\Models\BestbuyUsaProduct::query()->whereNotNull('sku')->where('sku', '!=', '')->count()
                : 0,
            'macy' => Schema::hasTable('macy_products')
                ? (int) \App\Models\MacyProduct::query()->whereNotNull('sku')->where('sku', '!=', '')->count()
                : 0,
            'doba' => Schema::hasTable('doba_metrics')
                ? (int) \App\Models\DobaMetric::query()->whereNotNull('sku')->where('sku', '!=', '')->count()
                : 0,
            'tiktok2' => Schema::hasTable('tiktok_products_two')
                ? (int) TikTokProductTwo::query()->whereNotNull('sku')->where('sku', '!=', '')->count()
                : 0,
            'pls' => Schema::hasTable('pls_products')
                ? (int) PLSProduct::query()->whereNotNull('sku')->where('sku', '!=', '')->count()
                : 0,
            default => 0,
        };
    }
}
