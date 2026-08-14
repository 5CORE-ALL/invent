<?php

namespace App\Services\MarketplaceManager;

use App\Jobs\WarmTikTok2LiveListingsCache;
use App\Jobs\WarmTikTokLiveListingsCache;
use App\Models\MarketplaceSyncSettings;
use App\Models\ShopifySku;
use App\Models\TikTokProduct;
use App\Models\TikTokProductTwo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Shopify-first listings page + mismatch inventory sync for TikTok 1 / 2
 * (same tab model as Reverb/TopDawg).
 */
class TikTokListingsPageBuilder
{
    public function __construct(
        protected string $channel
    ) {
        $this->channel = strtolower($channel);
        if (! in_array($this->channel, ['tiktok', 'tiktok2'], true)) {
            throw new \InvalidArgumentException('Channel must be tiktok or tiktok2.');
        }
    }

    public static function for(string $channel): self
    {
        return new self($channel);
    }

    public function syncProducts(Request $request): View
    {
        $searchSku = trim((string) $request->input('search_sku', ''));
        $searchName = trim((string) $request->input('search_name', ''));
        $linkTab = strtolower((string) $request->input('link', 'all'));
        if (in_array($linkTab, ['not_in_shopify', 'linked', 'linked_with_inv'], true)) {
            $linkTab = 'matched';
        }
        if ($linkTab === 'linked_zero') {
            $linkTab = 'zero';
        }
        if (! in_array($linkTab, ['all', 'matched', 'matched_inactive', 'mismatch', 'mismatch_inactive', 'zero', 'unlinked'], true)) {
            $linkTab = 'all';
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 50;
        $apiError = null;
        $forceLive = $request->boolean('refresh_live');
        $clearCache = $request->boolean('clear_cache');
        $emptyCounts = ['all' => 0, 'matched' => 0, 'matched_inactive' => 0, 'mismatch' => 0, 'mismatch_inactive' => 0, 'zero' => 0, 'unlinked' => 0, 'linked' => 0, 'tiktok_products' => 0, 'tiktok_skus' => 0];
        $liveLinkTabs = ['matched', 'matched_inactive', 'mismatch', 'mismatch_inactive', 'zero'];
        $liveService = $this->liveService();
        $label = $this->label();
        $slug = $this->channel;

        if ($clearCache) {
            $liveService->clearCache();
        }

        if (! Schema::hasTable('shopify_skus')) {
            $apiError = 'shopify_skus table missing. Run Shopify inventory sync first.';

            return view('marketplace.'.$slug.'.products', [
                'products' => new LengthAwarePaginator([], 0, $perPage, $page),
                'title' => $label.' — Listings',
                'searchSku' => $searchSku,
                'searchName' => $searchName,
                'linkTab' => $linkTab,
                'stateTab' => 'all',
                'counts' => $emptyCounts,
                'stateCounts' => ['all' => 0, 'active' => 0, 'inactive' => 0, 'other' => 0],
                'stateCacheReady' => false,
                'apiError' => $apiError,
                'connected' => $this->isConnected(),
                'shopifyCatalogSyncedAt' => null,
            ]);
        }

        if ($forceLive) {
            $this->dispatchWarmJob();
        }

        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        $linkedSkus = $this->linkedSkus();
        $allLinkedVerified = $catalog->filterLinkedToVerified($linkedSkus);
        $mpStock = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            null,
            $this->stockMapForSkus($allLinkedVerified)
        );
        $classified = $catalog->classifyLinkedInventoryMatch($linkedSkus, $mpStock);
        $counts = $classified['counts'] ?? $emptyCounts;
        $counts['all'] = $catalog->countDistinctAllSkus();
        $counts['matched_inactive'] = 0;
        $counts['mismatch_inactive'] = 0;

        if (! $catalog->hasAnyActive()) {
            $apiError = trim(($apiError ? $apiError.' ' : '').'Shared Shopify live catalog is empty — refresh Shopify from Marketplace Manager.');
        }

        $matchedQty = $classified['matched'] ?? [];
        $mismatchQty = $classified['mismatch'] ?? [];
        $zeroQty = $classified['zero'] ?? [];
        $counts['matched'] = count($matchedQty);
        $counts['mismatch'] = count($mismatchQty);
        $counts['zero'] = count($zeroQty);
        $counts['linked'] = $counts['matched'] + $counts['mismatch'] + $counts['zero'];
        $counts['tiktok_products'] = $this->tiktokProductCount();
        $counts['tiktok_skus'] = count($linkedSkus);

        // TikTok products table has no publish-state column — treat all as active.
        $matchedActive = $matchedQty;
        $matchedInactive = [];
        $mismatchActive = $mismatchQty;
        $mismatchInactive = [];
        $counts['matched'] = count($matchedActive);
        $counts['matched_inactive'] = 0;
        $counts['mismatch'] = count($mismatchActive);
        $counts['mismatch_inactive'] = 0;

        $linkedVerified = match ($linkTab) {
            'mismatch' => $mismatchActive,
            'mismatch_inactive' => $mismatchInactive,
            'zero' => $zeroQty,
            'matched_inactive' => $matchedInactive,
            'matched' => $matchedActive,
            default => [],
        };

        $cacheReady = $liveService->peekCached() !== null;
        if (in_array($linkTab, $liveLinkTabs, true) && ! $cacheReady && ! $forceLive) {
            $this->dispatchWarmJob();
        }

        $query = ShopifySku::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '');

        if ($searchSku !== '') {
            $query->where('sku', 'like', '%'.$searchSku.'%');
        }
        if ($searchName !== '') {
            $query->where(function ($q) use ($searchName) {
                $q->where('product_title', 'like', '%'.$searchName.'%')
                    ->orWhere('variant_title', 'like', '%'.$searchName.'%')
                    ->orWhere('sku', 'like', '%'.$searchName.'%');
            });
        }

        if (in_array($linkTab, $liveLinkTabs, true)) {
            if ($linkedVerified === []) {
                $query->whereRaw('1 = 0');
            } else {
                $catalog->restrictShopifySkuQuery($query, $linkedVerified);
            }
        } elseif ($linkTab === 'all') {
            $catalog->restrictShopifySkuQuery($query, null, false);
        } else {
            $catalog->restrictShopifySkuQuery($query, null, true);
            if ($allLinkedVerified !== []) {
                $query->whereNotIn('sku', $allLinkedVerified);
            }
        }

        $paginator = $query->orderBy('sku')->paginate($perPage, ['*'], 'page', $page)->withQueryString();
        $pageRows = collect($paginator->items())->all();
        $skus = collect($pageRows)->pluck('sku')->filter()->values()->all();
        $metricMap = $this->metricMapForSkus($skus);
        $stockMap = $this->stockMapForSkus($skus);
        $liveShopifyQty = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($skus);
        if ($liveShopifyQty === []) {
            $liveShopifyQty = MarketplaceListingStockResolver::dbShopifyQtyMapForRows($pageRows);
        }

        $pageLive = [];
        if (in_array($linkTab, $liveLinkTabs, true)) {
            $needIds = [];
            foreach ($skus as $sku) {
                $metric = $metricMap[$sku] ?? null;
                if (! $metric || ! $this->isLinked($metric, (string) $sku)) {
                    continue;
                }
                $needIds[] = (string) $metric->product_id;
            }
            if ($needIds !== []) {
                $pageLive = $liveService->liveDetailsByProductIds(array_slice(array_values(array_unique($needIds)), 0, 50));
            }
        }

        $enriched = collect($pageRows)->map(function (ShopifySku $row) use ($metricMap, $stockMap, $liveShopifyQty, $pageLive) {
            $sku = (string) $row->sku;
            $metric = $metricMap[$sku] ?? null;
            $linked = $this->isLinked($metric, $sku);
            $shopifyQty = MarketplaceListingStockResolver::shopifyQtyFromLiveMapOrRow($liveShopifyQty, $row, $sku);
            $shopifyPrice = $row->b2c_price ?? $row->price ?? null;
            $metricSku = $linked ? (string) ($metric->sku ?? '') : null;
            $pid = $linked ? (string) ($metric->product_id ?? '') : '';
            $skuId = $linked ? trim((string) ($metric->sku_id ?? '')) : '';
            $mpQty = $linked
                ? MarketplaceListingStockResolver::qtyFromMap($stockMap, $sku, $metricSku)
                : null;
            $live = null;
            if ($skuId !== '' && isset($pageLive[$skuId])) {
                $live = $pageLive[$skuId];
            } elseif (isset($pageLive[strtoupper($sku)])) {
                $live = $pageLive[strtoupper($sku)];
            } elseif (isset($pageLive[$sku])) {
                $live = $pageLive[$sku];
            }
            if ($linked && $live !== null && array_key_exists('inventory', $live) && $live['inventory'] !== null) {
                $mpQty = (int) $live['inventory'];
            }

            return (object) [
                'shopify_sku_id' => $row->id,
                'product_id' => $linked ? ($pid !== '' ? $pid : null) : null,
                'sku_id' => $linked ? ($metric->sku_id ?? null) : null,
                'sku' => $sku,
                'title' => trim(($row->product_title ?? '').($row->variant_title ? ' — '.$row->variant_title : '')) ?: $sku,
                'tiktok_title' => $live['title'] ?? null,
                'image_src' => $row->image_src ?? null,
                'price' => isset($live['price']) ? $live['price'] : ($linked ? ($metric->price ?? null) : null),
                'shopify_price' => $shopifyPrice,
                'quantity' => $mpQty,
                'ae_quantity' => $mpQty,
                'shopify_quantity' => $shopifyQty,
                'linked' => $linked,
                'listing_status' => $linked ? 'linked' : 'unlinked',
                'tiktok_state' => $linked ? 'active' : null,
            ];
        });

        $paginator->setCollection($enriched);

        return view('marketplace.'.$slug.'.products', [
            'products' => $paginator,
            'title' => $label.' — Listings',
            'searchSku' => $searchSku,
            'searchName' => $searchName,
            'linkTab' => $linkTab,
            'stateTab' => 'all',
            'counts' => $counts,
            'stateCounts' => [
                'all' => count($linkedVerified),
                'active' => count($linkedVerified),
                'inactive' => 0,
                'other' => 0,
            ],
            'stateCacheReady' => $cacheReady || $forceLive,
            'apiError' => $apiError,
            'connected' => $this->isConnected(),
            'shopifyCatalogSyncedAt' => $catalog->latestSyncedAt(),
        ]);
    }

    /**
     * @return array{success: bool, done?: bool, total?: int, offset?: int, batch?: int, updated?: int, failed?: int, skipped?: int, message: string, queued?: bool}
     */
    public function syncMismatchInventoryNow(Request $request): array
    {
        @set_time_limit(300);

        $settings = MarketplaceSyncSettings::getFor($this->channel);
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return [
                'success' => false,
                'message' => 'Turn on Inventory sync (or Price sync) in settings first.',
            ];
        }

        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        $liveService = $this->liveService();
        $linkedSkus = $this->linkedSkus();
        $verified = $catalog->filterLinkedToVerified($linkedSkus);
        $mpStock = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            null,
            $this->stockMapForSkus($verified)
        );
        $classified = $catalog->classifyLinkedInventoryMatch($linkedSkus, $mpStock);
        $mismatch = $classified['mismatch'] ?? [];

        $offset = max(0, (int) $request->input('offset', 0));
        $limit = max(1, min(40, (int) $request->input('limit', 25)));
        $total = count($mismatch);
        $batch = array_slice($mismatch, $offset, $limit);

        if ($batch === []) {
            return [
                'success' => true,
                'done' => true,
                'total' => $total,
                'offset' => $offset,
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => $total === 0 ? 'No mismatch SKUs to sync.' : 'All mismatch batches finished.',
            ];
        }

        $result = $this->inventoryService()->syncSkusFromShopify($batch);
        $nextOffset = $offset + count($batch);
        $done = $nextOffset >= $total;

        // Refresh local live cache after pushes so tabs update.
        $liveService->clearCache();

        return [
            'success' => true,
            'done' => $done,
            'queued' => false,
            'total' => $total,
            'offset' => $nextOffset,
            'batch' => count($batch),
            'updated' => (int) ($result['updated'] ?? 0),
            'failed' => (int) ($result['failed'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
            'message' => $result['message'] ?? ($done
                ? 'Mismatch inventory sync complete.'
                : 'Synced batch '.$nextOffset.' / '.$total.'…'),
        ];
    }

    public function showProduct(int $shopifySkuId): View
    {
        $row = ShopifySku::query()->findOrFail($shopifySkuId);
        $sku = (string) $row->sku;
        $metric = $this->metricMapForSkus([$sku])[$sku] ?? null;
        $linked = $this->isLinked($metric, $sku);
        $liveShopify = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus([$sku]);
        $shopifyQty = MarketplaceListingStockResolver::shopifyQtyFromLiveMapOrRow($liveShopify, $row, $sku);
        $stockMap = $this->stockMapForSkus([$sku]);
        $mpQty = $linked
            ? MarketplaceListingStockResolver::qtyFromMap($stockMap, $sku, (string) ($metric->sku ?? ''))
            : null;

        return view('marketplace.'.$this->channel.'.product-show', [
            'title' => $this->label().' — '.$sku,
            'shopifySkuId' => $shopifySkuId,
            'sku' => $sku,
            'shopify' => $row,
            'metric' => $metric,
            'linked' => $linked,
            'shopifyQty' => $shopifyQty,
            'tiktokQty' => $mpQty,
            'connected' => $this->isConnected(),
        ]);
    }

    /**
     * @return array{success: bool, message: string, updated?: int, failed?: int, skipped?: int}
     */
    public function pushProductInventory(int $shopifySkuId): array
    {
        $row = ShopifySku::query()->find($shopifySkuId);
        if (! $row) {
            return ['success' => false, 'message' => 'Shopify SKU not found.'];
        }
        $sku = trim((string) $row->sku);
        if ($sku === '') {
            return ['success' => false, 'message' => 'SKU is empty.'];
        }

        $settings = MarketplaceSyncSettings::getFor($this->channel);
        if (! ($settings['inventory']['inventory_sync'] ?? false)) {
            return ['success' => false, 'message' => 'Turn on Inventory sync in settings first.'];
        }

        $result = $this->inventoryService()->syncSkusFromShopify([$sku]);
        $this->liveService()->clearCache();

        return [
            'success' => ((int) ($result['updated'] ?? 0)) > 0 || ((int) ($result['failed'] ?? 0)) === 0,
            'message' => $result['message'] ?? 'Inventory push finished.',
            'updated' => (int) ($result['updated'] ?? 0),
            'failed' => (int) ($result['failed'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
        ];
    }

    /**
     * @return list<string>
     */
    public function linkedSkus(): array
    {
        if (! Schema::hasTable($this->table())) {
            return [];
        }

        return ($this->productModel())::query()
            ->whereNotNull('sku')
            ->whereNotNull('product_id')
            ->whereNotNull('sku_id')
            ->where('sku', '!=', '')
            ->where('product_id', '!=', '')
            ->where('sku_id', '!=', '')
            ->pluck('sku')
            ->map(static fn ($sku) => trim((string) $sku))
            ->filter(static fn (string $sku) => $sku !== '' && ! MarketplaceLiveInventoryRules::isParentPlaceholderSku($sku))
            ->unique(static fn (string $sku) => ShopifySku::normalizeSkuForShopifyLookup($sku))
            ->values()
            ->all();
    }

    protected function tiktokProductCount(): int
    {
        if (! Schema::hasTable($this->table())) {
            return 0;
        }

        return (int) ($this->productModel())::query()
            ->whereNotNull('product_id')
            ->where('product_id', '!=', '')
            ->selectRaw('COUNT(DISTINCT product_id) as c')
            ->value('c');
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, Model>
     */
    public function metricMapForSkus(array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ), static fn ($s) => $s !== '')));

        if ($skus === [] || ! Schema::hasTable($this->table())) {
            return [];
        }

        $keys = [];
        foreach ($skus as $sku) {
            $keys[] = $sku;
            $keys[] = strtoupper($sku);
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '') {
                $keys[] = $norm;
            }
        }
        $keys = array_values(array_unique($keys));

        $map = [];
        ($this->productModel())::query()
            ->whereIn('sku', $keys)
            ->get()
            ->each(function (Model $row) use (&$map) {
                $sku = (string) $row->sku;
                $map[$sku] = $row;
                $upper = strtoupper($sku);
                $map[$upper] = $row;
                $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
                if ($norm !== '') {
                    $map[$norm] = $row;
                }
            });

        $out = [];
        foreach ($skus as $sku) {
            $out[$sku] = $map[$sku]
                ?? $map[strtoupper($sku)]
                ?? $map[ShopifySku::normalizeSkuForShopifyLookup($sku)]
                ?? null;
        }

        return array_filter($out);
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, int>
     */
    public function stockMapForSkus(array $skus): array
    {
        return MarketplaceListingStockResolver::stockMapForSkus(
            $this->channel === 'tiktok2'
                ? MarketplaceListingStockResolver::CHANNEL_TIKTOK2
                : MarketplaceListingStockResolver::CHANNEL_TIKTOK,
            $skus
        );
    }

    public function isLinked(?Model $metric, string $shopifySku): bool
    {
        if (! $metric) {
            return false;
        }
        $productId = trim((string) ($metric->product_id ?? ''));
        $skuId = trim((string) ($metric->sku_id ?? ''));
        $sku = trim((string) ($metric->sku ?? ''));

        return $productId !== '' && $skuId !== '' && $sku !== '';
    }

    protected function liveService(): TikTokLiveListingsService
    {
        return $this->channel === 'tiktok2'
            ? app(TikTok2LiveListingsService::class)
            : app(TikTokLiveListingsService::class);
    }

    protected function inventoryService(): TikTokInventorySyncService|TikTok2InventorySyncService
    {
        return $this->channel === 'tiktok2'
            ? app(TikTok2InventorySyncService::class)
            : app(TikTokInventorySyncService::class);
    }

    /** @return class-string<Model> */
    protected function productModel(): string
    {
        return $this->channel === 'tiktok2' ? TikTokProductTwo::class : TikTokProduct::class;
    }

    protected function table(): string
    {
        return $this->channel === 'tiktok2' ? 'tiktok_products_two' : 'tiktok_products';
    }

    protected function label(): string
    {
        return $this->channel === 'tiktok2' ? 'TikTok 2' : 'TikTok Shop';
    }

    protected function isConnected(): bool
    {
        try {
            $api = $this->channel === 'tiktok2'
                ? app(\App\Services\TikTok2ShopService::class)
                : app(\App\Services\TikTokShopService::class);
            $cfg = app(\App\Services\Support\MarketplaceApiConfigService::class);

            return $cfg->isConfigured($this->channel) && $api->isAuthenticated();
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function dispatchWarmJob(): void
    {
        if ($this->channel === 'tiktok2') {
            WarmTikTok2LiveListingsCache::dispatch();
        } else {
            WarmTikTokLiveListingsCache::dispatch();
        }
    }
}
