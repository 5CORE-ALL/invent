<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\ProductStockMapping;
use App\Models\ShopifySku;
use App\Models\TikTokProduct;
use App\Services\ShopifyApiService;
use App\Services\TikTokShopService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TikTokInventorySyncService
{
    public function __construct(
        protected TikTokShopService $tiktokApi,
        protected ShopifyApiService $shopifyApi
    ) {}

    /**
     * @param  array<int, string>  $skus
     * @return array{updated: int, failed: int, skipped: int, message: string}
     */
    public function syncSkusFromShopify(array $skus, ?array $shopifyConfig = null, bool $exactShopifyQty = false): array
    {
        $skus = $this->normalizeRequestedSkus($skus);
        if ($skus === []) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'No SKUs to sync.'];
        }

        if (! $this->tiktokApi->isAuthenticated()) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'TikTok Shop API not authenticated.'];
        }

        $settings = MarketplaceSyncSettings::getFor('tiktok');
        $qtyPercent = max(0, min(100, (int) ($settings['inventory']['quantity_calc_percent'] ?? 100)));
        $maxQty = $settings['inventory']['max_quantity'] ?? null;
        if ($exactShopifyQty) {
            $qtyPercent = 100;
            $maxQty = null;
        }

        $shopifyQty = $this->shopifyQtyForPush($skus, $shopifyConfig);
        if ($exactShopifyQty) {
            $shopifyQty = MarketplaceLiveInventoryRules::overlayListingsShopifyQty($shopifyQty, $skus);
        }
        $metrics = $this->metricsForSkus($skus);

        $updated = 0;
        $failed = 0;
        $skipped = 0;
        $errorSamples = [];

        foreach ($metrics as $metric) {
            if ($this->tiktokApi->isIpAllowListBlocked()) {
                $skipped++;
                continue;
            }
            $outcome = $this->pushMetric($metric, $shopifyQty, $qtyPercent, $maxQty, false, $exactShopifyQty);
            $updated += $outcome['updated'];
            $failed += $outcome['failed'];
            $skipped += $outcome['skipped'];
            if ($outcome['error'] !== null) {
                $errorSamples[$outcome['error']] = ($errorSamples[$outcome['error']] ?? 0) + 1;
            }
        }

        $covered = $this->wantedSkuKeys(
            $metrics->map(static fn ($metric) => trim((string) $metric->sku))->all()
        );
        foreach ($skus as $requestedSku) {
            if (MarketplaceLiveInventoryRules::isParentPlaceholderSku($requestedSku)) {
                $skipped++;
                continue;
            }
            if (! $this->skuIsWanted($requestedSku, $covered)) {
                $failed++;
                $errorSamples['No linked TikTok product/sku_id'] = ($errorSamples['No linked TikTok product/sku_id'] ?? 0) + 1;
            }
        }

        $this->clearLiveCache();

        if ($this->tiktokApi->isIpAllowListBlocked()) {
            return $this->resultMessage(
                'Aborted: TikTok blocked this IP (Partner Center IP allow list). Updated %d SKU(s). Add this server IP and re-run from that host.',
                $updated,
                $failed,
                $skipped,
                $errorSamples
            );
        }

        return $this->resultMessage(
            'Synced %d SKU(s) to TikTok Shop from live Shopify.',
            $updated,
            $failed,
            $skipped,
            $errorSamples
        );
    }

    /**
     * @return array{updated: int, failed: int, skipped: int, message: string}
     */
    public function syncFromShopify(bool $dryRun = false): array
    {
        $settings = MarketplaceSyncSettings::getFor('tiktok');
        if (! ($settings['inventory']['inventory_sync'] ?? false)) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'Inventory sync is disabled in settings.',
            ];
        }

        if (! $this->tiktokApi->isAuthenticated()) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'TikTok Shop API not authenticated.',
            ];
        }

        if (! Schema::hasTable('tiktok_products')) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'tiktok_products table missing. Run Sync products first.',
            ];
        }

        $metrics = TikTokProduct::query()
            ->whereNotNull('sku')
            ->whereNotNull('product_id')
            ->whereNotNull('sku_id')
            ->where('sku', '!=', '')
            ->where('product_id', '!=', '')
            ->where('sku_id', '!=', '')
            ->get()
            ->filter(fn (TikTokProduct $metric) => ! MarketplaceLiveInventoryRules::isParentPlaceholderSku((string) $metric->sku))
            ->values();

        if ($metrics->isEmpty()) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'No TikTok Shop SKU mappings with sku_id found. Run Sync products first.',
            ];
        }

        $skus = $metrics->pluck('sku')->unique()->values()->all();
        Log::info('TikTokInventorySyncService: fetching Shopify inventory', ['sku_count' => count($skus)]);
        $shopifyQty = $this->shopifyQtyForPush($skus);

        $coverage = MarketplaceLiveInventoryRules::shopifyLiveCoverageReport(
            $skus,
            fn (string $sku) => $this->resolveShopifyQty($shopifyQty, $sku)
        );
        Log::info('TikTokInventorySyncService: Shopify coverage', $coverage);
        if (! $coverage['ok'] && ! $dryRun) {
            Log::error('TikTokInventorySyncService: aborting — Shopify coverage too low', $coverage);

            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => count($skus),
                'message' => $coverage['message'],
            ];
        }

        $qtyPercent = max(0, min(100, (int) ($settings['inventory']['quantity_calc_percent'] ?? 100)));
        $maxQty = $settings['inventory']['max_quantity'] ?? null;
        $updated = 0;
        $failed = 0;
        $skipped = 0;
        $errorSamples = [];

        foreach ($metrics as $metric) {
            if ($this->tiktokApi->isIpAllowListBlocked()) {
                $skipped++;
                continue;
            }
            $outcome = $this->pushMetric($metric, $shopifyQty, $qtyPercent, $maxQty, $dryRun);
            $updated += $outcome['updated'];
            $failed += $outcome['failed'];
            $skipped += $outcome['skipped'];
            if ($outcome['error'] !== null) {
                $errorSamples[$outcome['error']] = ($errorSamples[$outcome['error']] ?? 0) + 1;
            }
        }

        if (! $dryRun) {
            $this->clearLiveCache();
        }

        if ($dryRun) {
            return [
                'updated' => $updated,
                'failed' => 0,
                'skipped' => $skipped,
                'message' => "[dry-run] Would update {$updated} inventory row(s).",
            ];
        }

        if ($this->tiktokApi->isIpAllowListBlocked()) {
            return $this->resultMessage(
                "Aborted: TikTok blocked this IP (Partner Center IP allow list). Updated {$updated}; failed {$failed}; skipped {$skipped}. Add this server IP and re-run from that host.",
                $updated,
                $failed,
                $skipped,
                $errorSamples
            );
        }

        $pass = $this->appendMismatchPass();

        return $this->resultMessage(
            "Updated {$updated} inventory; failed {$failed}; skipped {$skipped}.",
            $updated,
            $failed,
            $skipped,
            $errorSamples,
            $pass
        );
    }

    /**
     * @param  array<string, int>  $shopifyQty
     * @return array{updated: int, failed: int, skipped: int, error: ?string}
     */
    protected function pushMetric(
        TikTokProduct $metric,
        array $shopifyQty,
        int $qtyPercent,
        mixed $maxQty,
        bool $dryRun,
        bool $exactShopifyQty = false
    ): array {
        $empty = ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'error' => null];
        $sku = trim((string) $metric->sku);
        $productId = trim((string) $metric->product_id);
        $skuId = trim((string) $metric->sku_id);

        if ($sku === '' || $productId === '' || $skuId === '' || MarketplaceLiveInventoryRules::isParentPlaceholderSku($sku)) {
            $empty['skipped'] = 1;

            return $empty;
        }

        $shopifyStock = $this->resolveShopifyQty($shopifyQty, $sku);
        $pushQty = MarketplaceLiveInventoryRules::qtyForMismatchPush(
            $shopifyStock,
            $exactShopifyQty,
            $qtyPercent,
            $maxQty
        );
        $pushQty = MarketplaceLiveInventoryRules::clampPushQty($pushQty, $shopifyStock ?? 0);

        if (! $exactShopifyQty) {
            if ($metric->stock !== null && (int) $metric->stock === $pushQty) {
                $empty['skipped'] = 1;

                return $empty;
            }
            if ($shopifyStock !== null && $shopifyStock > 0
                && MarketplaceLiveInventoryRules::qtyWithinMismatchTolerance((int) $shopifyStock, $metric->stock !== null ? (int) $metric->stock : null)) {
                $empty['skipped'] = 1;

                return $empty;
            }
        }

        if ($dryRun) {
            $empty['updated'] = 1;

            return $empty;
        }

        $result = $this->tiktokApi->updateProductInventory($productId, $skuId, $pushQty);
        usleep(50000);
        if (! empty($result['success'])) {
            $this->updateLocalStock($sku, $pushQty, $shopifyStock ?? 0);
            $empty['updated'] = 1;

            return $empty;
        }

        $msg = trim((string) ($result['message'] ?? 'TikTok inventory update failed.'));
        Log::warning('TikTokInventorySyncService: push failed', [
            'sku' => $sku,
            'product_id' => $productId,
            'sku_id' => $skuId,
            'qty' => $pushQty,
            'message' => $msg,
        ]);
        $empty['failed'] = 1;
        $empty['error'] = $msg !== '' ? $msg : 'TikTok inventory update failed.';

        return $empty;
    }

    /**
     * @param  array<int, string>  $skus
     * @return \Illuminate\Support\Collection<int, TikTokProduct>
     */
    protected function metricsForSkus(array $skus)
    {
        $wanted = $this->wantedSkuKeys($skus);
        if ($wanted === []) {
            return collect();
        }

        return TikTokProduct::query()
            ->whereNotNull('product_id')
            ->whereNotNull('sku_id')
            ->where('sku', '!=', '')
            ->where('product_id', '!=', '')
            ->where('sku_id', '!=', '')
            ->get()
            ->filter(fn (TikTokProduct $metric) => $this->skuIsWanted((string) $metric->sku, $wanted))
            ->values();
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, true>
     */
    protected function wantedSkuKeys(array $skus): array
    {
        $wanted = [];
        foreach ($skus as $sku) {
            $trim = trim((string) $sku);
            if ($trim === '') {
                continue;
            }
            $wanted[strtoupper($trim)] = true;
            $norm = ShopifySku::normalizeSkuForShopifyLookup($trim);
            if ($norm !== '') {
                $wanted[$norm] = true;
                $wanted[strtoupper($norm)] = true;
            }
            $compact = ShopifySku::compactSkuForLookup($trim);
            if ($compact !== '') {
                $wanted[$compact] = true;
            }
        }

        return $wanted;
    }

    /**
     * @param  array<string, true>  $wanted
     */
    protected function skuIsWanted(string $sku, array $wanted): bool
    {
        $sku = trim($sku);
        if ($sku === '') {
            return false;
        }
        if (isset($wanted[strtoupper($sku)])) {
            return true;
        }
        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($norm !== '' && (isset($wanted[$norm]) || isset($wanted[strtoupper($norm)]))) {
            return true;
        }
        $compact = ShopifySku::compactSkuForLookup($sku);

        return $compact !== '' && isset($wanted[$compact]);
    }

    /**
     * Same Shopify qty the listings page uses (shopify_skus), with live API fill for misses.
     *
     * @param  array<int, string>  $skus
     * @param  array{store_url?: string, token?: string}|null  $shopifyConfig
     * @return array<string, int>
     */
    protected function shopifyQtyForPush(array $skus, ?array $shopifyConfig = null): array
    {
        $map = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($skus);
        if ($map === []) {
            $map = MarketplaceListingStockResolver::catalogShopifyQtyMapForSkus($skus);
        }

        $missing = [];
        foreach ($skus as $sku) {
            if ($this->resolveShopifyQty($map, (string) $sku) === null) {
                $missing[] = (string) $sku;
            }
        }
        if ($missing !== []) {
            foreach ($this->fetchLiveShopifyQuantities($missing, $shopifyConfig) as $sku => $qty) {
                $map[$sku] = (int) $qty;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $skus
     * @param  array{store_url?: string, token?: string}|null  $shopifyConfig
     * @return array<string, int>
     */
    protected function fetchLiveShopifyQuantities(array $skus, ?array $shopifyConfig = null): array
    {
        try {
            if ($shopifyConfig) {
                return $this->shopifyApi->getInventoryQuantitiesBySku($skus, $shopifyConfig);
            }

            return $this->shopifyApi->getInventoryQuantitiesBySku($skus);
        } catch (\Throwable $e) {
            Log::warning('TikTokInventorySyncService: live Shopify fetch failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param  array<string, int>  $shopifyQty
     */
    protected function resolveShopifyQty(array $shopifyQty, string $sku): ?int
    {
        if (array_key_exists($sku, $shopifyQty)) {
            return (int) $shopifyQty[$sku];
        }

        $upper = strtoupper(trim($sku));
        if ($upper !== '' && array_key_exists($upper, $shopifyQty)) {
            return (int) $shopifyQty[$upper];
        }

        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($norm !== '' && array_key_exists($norm, $shopifyQty)) {
            return (int) $shopifyQty[$norm];
        }

        $compact = ShopifySku::compactSkuForLookup($sku);
        if ($compact !== '' && array_key_exists($compact, $shopifyQty)) {
            return (int) $shopifyQty[$compact];
        }

        foreach ($shopifyQty as $key => $qty) {
            if (strtoupper(trim((string) $key)) === $upper) {
                return (int) $qty;
            }
            if ($norm !== '' && ShopifySku::normalizeSkuForShopifyLookup((string) $key) === $norm) {
                return (int) $qty;
            }
            if ($compact !== '' && ShopifySku::compactSkuForLookup((string) $key) === $compact) {
                return (int) $qty;
            }
        }

        return null;
    }

    protected function updateLocalStock(string $sku, int $pushQty, int $shopifyQty): void
    {
        $sku = trim($sku);
        if ($sku === '') {
            return;
        }

        if (Schema::hasTable('tiktok_products') && Schema::hasColumn('tiktok_products', 'stock')) {
            $wanted = $this->wantedSkuKeys([$sku]);
            TikTokProduct::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->get(['id', 'sku'])
                ->filter(fn ($row) => $this->skuIsWanted((string) $row->sku, $wanted))
                ->each(function ($row) use ($pushQty) {
                    TikTokProduct::query()->whereKey($row->id)->update(['stock' => $pushQty]);
                });
        }

        if (! Schema::hasTable('product_stock_mappings')) {
            return;
        }

        $payload = ['inventory_shopify' => $shopifyQty];
        if (Schema::hasColumn('product_stock_mappings', 'inventory_tiktok')) {
            $payload['inventory_tiktok'] = $pushQty;
        }

        ProductStockMapping::query()
            ->where(function ($q) use ($sku) {
                $q->where('sku', $sku)->orWhere('sku', strtoupper($sku));
            })
            ->update($payload);
    }

    protected function clearLiveCache(): void
    {
        try {
            // Drop the listings cache only. all(true) re-crawls the whole TikTok
            // catalog and overwrites stock we just pushed with stale live qty.
            app(TikTokLiveListingsService::class)->clearCache();
        } catch (\Throwable $e) {
            // ignore
        }
    }

    protected function appendMismatchPass(): string
    {
        $pass = app(MarketplaceMismatchInventoryPass::class)->run('tiktok');

        return ' '.($pass['message'] ?? '');
    }

    /**
     * @param  array<int, string>  $skus
     * @return list<string>
     */
    protected function normalizeRequestedSkus(array $skus): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($sku) => trim((string) $sku),
            $skus
        ), static fn ($sku) => $sku !== '' && ! MarketplaceLiveInventoryRules::isParentPlaceholderSku($sku))));
    }

    /**
     * @param  array<int, string>  $skus
     * @return list<string>
     */
    protected function expandSkuKeys(array $skus): array
    {
        $keys = [];
        foreach ($skus as $sku) {
            $trim = trim((string) $sku);
            if ($trim === '') {
                continue;
            }
            $keys[] = $trim;
            $keys[] = strtoupper($trim);
            $norm = ShopifySku::normalizeSkuForShopifyLookup($trim);
            if ($norm !== '') {
                $keys[] = $norm;
            }
            $compact = ShopifySku::compactSkuForLookup($trim);
            if ($compact !== '') {
                $keys[] = $compact;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  array<string, int>  $errorSamples
     * @return array{updated: int, failed: int, skipped: int, message: string}
     */
    protected function resultMessage(
        string $base,
        int $updated,
        int $failed,
        int $skipped,
        array $errorSamples,
        string $suffix = ''
    ): array {
        $message = str_contains($base, '%d')
            ? sprintf($base, $updated)
            : $base;
        if ($failed > 0 && $errorSamples !== []) {
            arsort($errorSamples);
            $top = [];
            foreach (array_slice($errorSamples, 0, 3, true) as $msg => $count) {
                $top[] = "{$msg} ({$count})";
            }
            $message .= ' Failures: '.implode('; ', $top).'.';
        }

        return [
            'updated' => $updated,
            'failed' => $failed,
            'skipped' => $skipped,
            'message' => trim($message.$suffix),
        ];
    }
}
