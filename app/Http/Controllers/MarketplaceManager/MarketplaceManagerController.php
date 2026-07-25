<?php

namespace App\Http\Controllers\MarketplaceManager;

use App\Http\Controllers\Controller;
use App\Jobs\WarmShopifyLiveCatalogCache;
use App\Models\AlibabaMetric;
use App\Models\AliexpressListingStatus;
use App\Models\AliexpressMetric;
use App\Models\MarketplaceSyncSettings;
use App\Models\FaireMetric;
use App\Models\NeweggMetric;
use App\Models\ReverbMetric;
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
        $channels = [];

        foreach (MarketplaceManagerRegistry::channels() as $channel) {
            if (! $channel['enabled']) {
                continue;
            }

            $slug = $channel['slug'];
            $channels[] = array_merge($channel, [
                'connected' => $this->apiConfig->isConfigured($slug),
                'listings_count' => $this->listingCount($slug),
                'sync_settings' => $this->syncSettingsFor($slug),
            ]);
        }

        $catalog = app(ShopifyLiveVerifiedCatalogService::class);

        return view('marketplace-manager.index', [
            'title' => 'Marketplace Manager',
            'channels' => $channels,
            'shopifySkuCount' => $catalog->countDistinctAllSkus(),
            'shopifyActiveSkuCount' => $catalog->countDistinctActiveSkus(),
            'shopifyCatalogSyncedAt' => $catalog->latestSyncedAt(),
            'shopifyRefreshStatus' => Cache::get(WarmShopifyLiveCatalogCache::STATUS_CACHE_KEY),
        ]);
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
            'faire' => Schema::hasTable('faire_metric')
                ? (int) FaireMetric::query()->whereNotNull('sku')->count()
                : 0,
            default => 0,
        };
    }
}
