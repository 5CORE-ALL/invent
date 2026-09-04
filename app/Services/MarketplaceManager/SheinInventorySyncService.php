<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\SheinListingStatus;
use App\Models\SheinMmMetric;
use App\Models\SheinPricingPrice;
use App\Models\ProductStockMapping;
use App\Models\ShopifySku;
use App\Services\SheinApiService;
use App\Services\ShopifyApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SheinInventorySyncService
{
    public function __construct(
        protected SheinApiService $sheinApi,
        protected ShopifyApiService $shopifyApi
    ) {}

    /**
     * @param  array<int, string>  $skus
     * @param  array{store_url?: string, token?: string}|null  $shopifyConfig
     * @param  bool  $exactShopifyQty  When true (mismatch button), push listings Shopify qty with no percent/max cap.
     * @return array{updated: int, failed: int, skipped: int, message: string}
     */
    public function syncSkusFromShopify(array $skus, ?array $shopifyConfig = null, bool $exactShopifyQty = false): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($sku) => trim((string) $sku),
            $skus
        ), static fn ($sku) => $sku !== '' && ! in_array($sku, ['__order__', '__unknown__'], true))));

        if ($skus === []) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'No SKUs to sync.'];
        }

        if (! $this->sheinApi->isConfigured()) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'Shein API credentials missing.'];
        }

        $settings = MarketplaceSyncSettings::getFor('shein');
        $qtyPercent = max(0, min(100, (int) ($settings['inventory']['quantity_calc_percent'] ?? 100)));
        $maxQty = $settings['inventory']['max_quantity'] ?? null;

        // Include NBSP-normalized forms so live Shopify Admin lookup matches Admin SKUs.
        $fetchSkus = $skus;
        $wantedNorms = [];
        foreach ($skus as $sku) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '') {
                $wantedNorms[$norm] = true;
                if ($norm !== $sku) {
                    $fetchSkus[] = $norm;
                }
            }
        }
        $fetchSkus = array_values(array_unique($fetchSkus));

        $shopifyQty = app(ShopifyQtySource::class)->fetchQuantitiesForPush(
            $fetchSkus,
            fn (array $need) => $this->fetchLiveShopifyQuantities($need, $shopifyConfig)
        );
        if ($exactShopifyQty) {
            $shopifyQty = MarketplaceLiveInventoryRules::overlayListingsShopifyQty($shopifyQty, $fetchSkus);
        }

        // Match Shein rows by normalized SKU (Shopify often stores NBSP; Shein uses normal spaces).
        $metrics = SheinMmMetric::query()
            ->whereNotNull('product_id')
            ->where('sku', '!=', '')
            ->whereColumn('sku', '!=', 'product_id')
            ->get()
            ->filter(function (SheinMmMetric $metric) use ($wantedNorms, $skus) {
                $raw = (string) $metric->sku;
                if (in_array($raw, $skus, true)) {
                    return true;
                }
                $norm = ShopifySku::normalizeSkuForShopifyLookup($raw);

                return $norm !== '' && isset($wantedNorms[$norm]);
            })
            ->values();

        $inventoryRows = [];
        $skipped = 0;
        $seenNorms = [];

        foreach ($metrics as $metric) {
            $sku = (string) $metric->sku;
            $productId = (string) $metric->product_id;
            if (! MarketplaceLiveInventoryRules::isLinked($productId, $sku)
                || ! $this->sheinApi->isPlatformSkuCode($productId, $sku)) {
                continue;
            }

            $shopifyStock = $this->resolveShopifyQty($shopifyQty, $sku);
            foreach ($skus as $requested) {
                if ($shopifyStock !== null) {
                    break;
                }
                if (ShopifySku::normalizeSkuForShopifyLookup($requested)
                    === ShopifySku::normalizeSkuForShopifyLookup($sku)) {
                    $shopifyStock = $this->resolveShopifyQty($shopifyQty, $requested);
                }
            }
            $pushQty = MarketplaceLiveInventoryRules::qtyForMismatchPush(
                $shopifyStock,
                $exactShopifyQty,
                $qtyPercent,
                $maxQty
            );

            $aliases = [$sku];
            foreach ($skus as $requested) {
                if (ShopifySku::normalizeSkuForShopifyLookup($requested)
                    === ShopifySku::normalizeSkuForShopifyLookup($sku)) {
                    $aliases[] = $requested;
                }
            }
            $inventoryRows[] = [
                'product_id' => $productId,
                'sku_code' => $sku,
                'inventory' => MarketplaceLiveInventoryRules::clampPushQty($pushQty, $shopifyStock ?? 0),
                'shopify_qty' => $shopifyStock ?? 0,
                'sku_aliases' => array_values(array_unique(array_filter($aliases))),
            ];
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '') {
                $seenNorms[$norm] = true;
            }
        }

        // Hyphen / Hub-listed rows often have no shein_metric product_id. Resolve the
        // real Shein skuCode (never send seller SKU — API returns 商品SKU不存在).
        $unresolved = [];
        foreach ($skus as $sku) {
            if (MarketplaceLiveInventoryRules::isParentPlaceholderSku($sku)) {
                $skipped++;
                continue;
            }
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '' && isset($seenNorms[$norm])) {
                continue;
            }
            $unresolved[] = $sku;
        }
        $resolved = [];
        if ($unresolved !== []) {
            $resolved = $this->sheinApi->resolvePlatformSkuCodesForSellerSkus(
                $unresolved,
                SheinListingStatus::spuNamesForSellerSkus($unresolved),
                true
            );
        }
        foreach ($unresolved as $sku) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            $hit = ($norm !== '' && isset($resolved[$norm])) ? $resolved[$norm] : ($resolved[$sku] ?? null);
            $skuCode = trim((string) ($hit['sku_code'] ?? ''));
            if (! $this->sheinApi->isPlatformSkuCode($skuCode, $sku)) {
                $skipped++;
                Log::info('SheinInventorySyncService: no platform skuCode, skip', ['sku' => $sku]);
                continue;
            }
            $shopifyStock = $this->resolveShopifyQty($shopifyQty, $sku);
            $pushQty = MarketplaceLiveInventoryRules::qtyForMismatchPush(
                $shopifyStock,
                $exactShopifyQty,
                $qtyPercent,
                $maxQty
            );
            $row = [
                'product_id' => $skuCode,
                'sku_code' => $sku,
                'inventory' => MarketplaceLiveInventoryRules::clampPushQty($pushQty, $shopifyStock ?? 0),
                'shopify_qty' => $shopifyStock ?? 0,
                'sku_aliases' => array_values(array_unique(array_filter([
                    $sku,
                    (string) ($hit['seller_sku'] ?? ''),
                ]))),
            ];
            $warehouse = trim((string) ($hit['warehouse_code'] ?? ''));
            if ($warehouse !== '') {
                $row['warehouse_code'] = $warehouse;
            }
            $inventoryRows[] = $row;
            if ($norm !== '') {
                $seenNorms[$norm] = true;
            }
        }

        if ($inventoryRows === []) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => $skipped,
                'message' => $skipped > 0
                    ? 'No Shein skuCode for '.$skipped.' listing(s). They are Listed in Seller Hub but Shein has not assigned a platform SKU yet.'
                    : 'No linked Shein SKUs found for inventory sync.',
            ];
        }

        $invResult = $this->pushInventoryRows($inventoryRows);
        $persisted = $this->successfulInventoryRows($inventoryRows, $invResult);
        if (($invResult['pushed'] ?? 0) > 0 && $persisted !== []) {
            $this->updateLocalStock($persisted);
            $this->updateLocalPlatformQuantities($persisted);
            try {
                app(SheinLiveListingsService::class)->clearCache();
            } catch (\Throwable $e) {
                // ignore
            }

            return [
                'updated' => (int) ($invResult['pushed'] ?? count($persisted)),
                'failed' => (int) ($invResult['failed'] ?? 0),
                'skipped' => $skipped,
                'message' => 'Synced '.((int) ($invResult['pushed'] ?? 0)).' SKU(s) to Shein from live Shopify.'
                    .($skipped > 0 ? ' Skipped '.$skipped.' without a Shein skuCode.' : ''),
            ];
        }

        $this->updateLocalPlatformQuantities($inventoryRows, false);

        return [
            'updated' => (int) ($invResult['pushed'] ?? 0),
            'failed' => (int) ($invResult['failed'] ?? count($inventoryRows)),
            'skipped' => $skipped,
            'message' => $invResult['message'] ?? 'Shein inventory update failed.',
        ];
    }

    /**
     * @return array{updated: int, failed: int, skipped: int, price_updated: int, message: string}
     */
    public function syncFromShopify(bool $dryRun = false): array
    {
        $settings = MarketplaceSyncSettings::getFor('shein');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'Inventory and price sync are disabled in settings.',
            ];
        }

        if (! $this->sheinApi->isConfigured()) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'Shein API credentials missing.',
            ];
        }

        if (! Schema::hasTable('shein_metric')) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'shein_metric table missing. Run Sync link map on Listings first.',
            ];
        }

        $metrics = SheinMmMetric::query()
            ->whereNotNull('sku')
            ->whereNotNull('product_id')
            ->where('sku', '!=', '')
            ->whereColumn('sku', '!=', 'product_id')
            ->get();

        if ($metrics->isEmpty()) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'No Shein SKU mappings found. Run Sync link map on Listings first.',
            ];
        }

        $skus = $metrics->pluck('sku')->unique()->values()->all();
        Log::info('SheinInventorySyncService: fetching live Shopify inventory', ['sku_count' => count($skus)]);
        $shopifyQty = $this->shopifyApi->getInventoryQuantitiesBySku($skus);

        $missing = [];
        foreach ($skus as $sku) {
            if ($this->resolveShopifyQty($shopifyQty, (string) $sku) === null) {
                $missing[] = (string) $sku;
            }
        }
        if ($missing !== []) {
            Log::info('SheinInventorySyncService: live variant fallback for missing SKUs', ['count' => count($missing)]);
            foreach ($this->fetchLiveShopifyQuantities($missing) as $sku => $qty) {
                $shopifyQty[$sku] = $qty;
            }
        }

        $coverage = MarketplaceLiveInventoryRules::shopifyLiveCoverageReport(
            $skus,
            fn (string $sku) => $this->resolveShopifyQty($shopifyQty, $sku)
        );
        Log::info('SheinInventorySyncService: Shopify live coverage', $coverage);
        if (! $coverage['ok'] && ($settings['inventory']['inventory_sync'] ?? false) && ! $dryRun) {
            $known = MarketplaceLiveInventoryRules::skusWithKnownQty(
                $skus,
                fn (string $sku) => $this->resolveShopifyQty($shopifyQty, $sku)
            );
            Log::warning('SheinInventorySyncService: Shopify coverage low — pushing confirmed qtys only', $coverage + ['kept' => count($known)]);
            if ($known === []) {
                return [
                    'updated' => 0,
                    'failed' => 0,
                    'skipped' => count($skus),
                    'price_updated' => 0,
                    'message' => $coverage['message'],
                ];
            }
            $skus = $known;
        }

        $shopifyDetails = ($settings['pricing']['price_sync'] ?? false)
            ? $this->shopifyApi->getProductDetailsBySkuMap($skus)
            : [];

        $qtyPercent = max(0, min(100, (int) ($settings['inventory']['quantity_calc_percent'] ?? 100)));
        $maxQty = $settings['inventory']['max_quantity'] ?? null;
        $useSalePrice = (bool) ($settings['pricing']['use_sale_price'] ?? false);

        $inventoryRows = [];
        $priceRows = [];
        $skipped = 0;

        foreach ($metrics as $metric) {
            $sku = (string) $metric->sku;
            $productId = (string) $metric->product_id;
            if (! MarketplaceLiveInventoryRules::isLinked($productId, $sku)) {
                $skipped++;
                continue;
            }

            if ($settings['inventory']['inventory_sync'] ?? false) {
                if ($this->sheinApi->isPlatformSkuCode($productId, $sku)) {
                    $shopifyStock = $this->resolveShopifyQty($shopifyQty, $sku);
                    $pushQty = $shopifyStock === null
                        ? MarketplaceLiveInventoryRules::qtyWhenMissingFromShopify()
                        : MarketplaceLiveInventoryRules::qtyFromLiveShopify($shopifyStock, $qtyPercent, $maxQty);

                    $inventoryRows[] = [
                        'product_id' => $productId,
                        'sku_code' => $sku,
                        'inventory' => MarketplaceLiveInventoryRules::clampPushQty($pushQty, $shopifyStock ?? 0),
                        'shopify_qty' => $shopifyStock ?? 0,
                        'sku_aliases' => [$sku],
                    ];
                }
            }

            if ($settings['pricing']['price_sync'] ?? false) {
                $detail = $shopifyDetails[$sku] ?? null;
                $price = null;
                if (is_array($detail)) {
                    $price = $useSalePrice
                        ? ($detail['price'] ?? $detail['sale_price'] ?? null)
                        : ($detail['compare_at_price'] ?? $detail['price'] ?? null);
                }
                $price = $this->applyPriceAdjustment((float) ($price ?? 0), $settings['pricing'] ?? []);
                if ($price > 0) {
                    $priceRows[] = [
                        'product_id' => $productId,
                        'sku_code' => $sku,
                        'price' => $price,
                    ];
                }
            }
        }

        if ($settings['inventory']['inventory_sync'] ?? false) {
            $seenNorms = [];
            foreach ($inventoryRows as $row) {
                $norm = ShopifySku::normalizeSkuForShopifyLookup((string) ($row['sku_code'] ?? ''));
                if ($norm !== '') {
                    $seenNorms[$norm] = true;
                }
            }
            $needResolve = [];
            foreach ($metrics as $metric) {
                $sku = trim((string) $metric->sku);
                $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
                if ($sku === '' || ($norm !== '' && isset($seenNorms[$norm]))) {
                    continue;
                }
                $needResolve[] = $sku;
            }
            foreach (SheinListingStatus::listedSellerSkus() as $sku) {
                $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
                if ($norm !== '' && isset($seenNorms[$norm])) {
                    continue;
                }
                $needResolve[] = $sku;
            }
            $needResolve = array_values(array_unique($needResolve));
            if ($needResolve !== []) {
                foreach ($this->shopifyApi->getInventoryQuantitiesBySku($needResolve) as $sku => $qty) {
                    $shopifyQty[$sku] = (int) $qty;
                }
                $resolved = $this->sheinApi->resolvePlatformSkuCodesForSellerSkus(
                    $needResolve,
                    SheinListingStatus::spuNamesForSellerSkus($needResolve),
                    true
                );
                foreach ($needResolve as $sku) {
                    $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
                    $hit = ($norm !== '' && isset($resolved[$norm])) ? $resolved[$norm] : ($resolved[$sku] ?? null);
                    $skuCode = trim((string) ($hit['sku_code'] ?? ''));
                    if (! $this->sheinApi->isPlatformSkuCode($skuCode, $sku)) {
                        $skipped++;
                        continue;
                    }
                    $shopifyStock = $this->resolveShopifyQty($shopifyQty, $sku);
                    $pushQty = $shopifyStock === null
                        ? MarketplaceLiveInventoryRules::qtyWhenMissingFromShopify()
                        : MarketplaceLiveInventoryRules::qtyFromLiveShopify($shopifyStock, $qtyPercent, $maxQty);
                    $inventoryRows[] = [
                        'product_id' => $skuCode,
                        'sku_code' => $sku,
                        'inventory' => MarketplaceLiveInventoryRules::clampPushQty($pushQty, $shopifyStock ?? 0),
                        'shopify_qty' => $shopifyStock ?? 0,
                        'sku_aliases' => array_values(array_unique(array_filter([
                            $sku,
                            (string) ($hit['seller_sku'] ?? ''),
                        ]))),
                    ];
                    if ($norm !== '') {
                        $seenNorms[$norm] = true;
                    }
                }
            }
        }

        if ($dryRun) {
            return [
                'updated' => count($inventoryRows),
                'failed' => 0,
                'skipped' => $skipped,
                'price_updated' => count($priceRows),
                'message' => '[dry-run] Would update '.count($inventoryRows).' inventory row(s), '.count($priceRows).' price row(s).',
            ];
        }

        $updated = 0;
        $failed = 0;
        $priceUpdated = 0;

        if ($inventoryRows !== []) {
            $invResult = $this->pushInventoryRows($inventoryRows);
            $updated = (int) ($invResult['pushed'] ?? 0);
            $failed = (int) ($invResult['failed'] ?? 0);
            $persisted = $this->successfulInventoryRows($inventoryRows, $invResult);
            if ($updated > 0 && $persisted !== []) {
                $this->updateLocalStock($persisted);
                $this->updateLocalPlatformQuantities($persisted);
                try {
                    app(SheinLiveListingsService::class)->clearCache();
                } catch (\Throwable $e) {
                    // ignore
                }
            } elseif ($failed > 0) {
                Log::warning('SheinInventorySyncService: inventory push failed', $invResult);
            }
        }

        if ($priceRows !== []) {
            $bulk = $this->sheinApi->updateItemPriceBulk(array_map(static fn ($r) => [
                'seller_part_number' => $r['sku_code'],
                'price' => $r['price'],
            ], $priceRows), 'USA');
            $priceUpdated = (int) ($bulk['pushed'] ?? 0);
            if ($priceUpdated > 0) {
                $this->updateLocalPrices($priceRows);
            }
        }

        return [
            'updated' => $updated,
            'failed' => $failed,
            'skipped' => $skipped,
            'price_updated' => $priceUpdated,
            'message' => "Updated {$updated} inventory, {$priceUpdated} price(s); failed {$failed}; skipped {$skipped}."
                .$this->appendMismatchPass(!$dryRun && ($settings['inventory']['inventory_sync'] ?? false)),
        ];
    }

    protected function appendMismatchPass(bool $run): string
    {
        if (! $run) {
            return '';
        }

        $pass = app(MarketplaceMismatchInventoryPass::class)->run('shein');

        return ' '.$pass['message'];
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int, sku_aliases?: list<string>}>  $inventoryRows
     * @param  array{pushed?: int, results?: list<array<string, mixed>>}  $invResult
     * @return array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int, sku_aliases?: list<string>}>
     */
    protected function successfulInventoryRows(array $inventoryRows, array $invResult): array
    {
        $okNorms = [];
        foreach ($invResult['results'] ?? [] as $row) {
            if (empty($row['success'])) {
                continue;
            }
            $seller = trim((string) ($row['seller_part_number'] ?? ''));
            if ($seller === '') {
                continue;
            }
            $okNorms[ShopifySku::normalizeSkuForShopifyLookup($seller) ?: strtoupper($seller)] = true;
        }
        if ($okNorms === []) {
            return ((int) ($invResult['pushed'] ?? 0)) > 0 ? $inventoryRows : [];
        }

        return array_values(array_filter(
            $inventoryRows,
            static function (array $row) use ($okNorms): bool {
                $candidates = array_merge(
                    [(string) ($row['sku_code'] ?? '')],
                    is_array($row['sku_aliases'] ?? null) ? $row['sku_aliases'] : []
                );
                foreach ($candidates as $sku) {
                    $sku = trim((string) $sku);
                    if ($sku === '') {
                        continue;
                    }
                    $norm = ShopifySku::normalizeSkuForShopifyLookup($sku) ?: strtoupper($sku);
                    if (isset($okNorms[$norm])) {
                        return true;
                    }
                }

                return false;
            }
        ));
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int}>  $inventoryRows
     * @return array{success: bool, pushed: int, failed: int, message?: string}
     */
    protected function pushInventoryRows(array $inventoryRows): array
    {
        $items = [];
        foreach ($inventoryRows as $row) {
            $sku = trim((string) ($row['sku_code'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $items[] = [
                'sku' => $sku,
                'sku_code' => trim((string) ($row['product_id'] ?? '')),
                'quantity' => max(0, (int) ($row['inventory'] ?? 0)),
                'warehouse_code' => trim((string) ($row['warehouse_code'] ?? '')),
            ];
        }

        if ($items === []) {
            return [
                'success' => false,
                'pushed' => 0,
                'failed' => 0,
                'message' => 'No inventory rows to push',
            ];
        }

        $bulk = $this->sheinApi->updateItemInventoryBulk($items);
        $pushed = (int) ($bulk['pushed'] ?? 0);
        $failed = (int) ($bulk['failed'] ?? 0);
        $lastMessage = $bulk['error_message'] ?? null;
        if ($pushed > 0 && ! empty($bulk['warehouse_code'])) {
            $lastMessage = trim((string) $lastMessage.' (warehouse '.$bulk['warehouse_code'].')');
        }

        return [
            'success' => $pushed > 0,
            'pushed' => $pushed,
            'failed' => $failed,
            'message' => $lastMessage,
            'results' => $bulk['results'] ?? [],
        ];
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
            Log::warning('SheinInventorySyncService: live Shopify fetch failed', ['error' => $e->getMessage()]);

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

        $needle = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($needle !== '') {
            foreach ($shopifyQty as $key => $qty) {
                if (ShopifySku::normalizeSkuForShopifyLookup((string) $key) === $needle) {
                    return (int) $qty;
                }
            }
        }

        $needleUpper = strtoupper(trim($sku));
        foreach ($shopifyQty as $key => $qty) {
            if (strtoupper(trim((string) $key)) === $needleUpper) {
                return (int) $qty;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int, sku_aliases?: list<string>}>  $rows
     */
    protected function updateLocalStock(array $rows): void
    {
        foreach ($rows as $row) {
            $qty = (int) $row['inventory'];
            $skus = array_merge(
                [(string) ($row['sku_code'] ?? '')],
                is_array($row['sku_aliases'] ?? null) ? $row['sku_aliases'] : []
            );
            $written = [];
            foreach ($skus as $sku) {
                $sku = trim((string) $sku);
                if ($sku === '' || isset($written[strtoupper($sku)])) {
                    continue;
                }
                $written[strtoupper($sku)] = true;

                if (Schema::hasTable('shein_pricing_prices')) {
                    SheinPricingPrice::updateOrCreate(
                        ['sku' => $sku],
                        ['shein_stock' => $qty]
                    );
                }
            }
        }
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int}>  $rows
     */
    protected function updateLocalPlatformQuantities(array $rows, bool $updateNeStock = true): void
    {
        foreach ($rows as $row) {
            $sku = trim((string) $row['sku_code']);
            if ($sku === '') {
                continue;
            }

            $neQty = (int) $row['inventory'];
            $shopifyQty = array_key_exists('shopify_qty', $row) ? (int) $row['shopify_qty'] : $neQty;

            // Never overwrite shopify_skus.available_to_sell — owned by SyncShopifyLiveInventory.

            if (Schema::hasTable('product_stock_mappings')) {
                $payload = ['inventory_shopify' => $shopifyQty];
                if ($updateNeStock && Schema::hasColumn('product_stock_mappings', 'inventory_shein')) {
                    $payload['inventory_shein'] = $neQty;
                }
                ProductStockMapping::query()
                    ->where(function ($q) use ($sku) {
                        $q->where('sku', $sku)->orWhere('sku', strtoupper($sku));
                    })
                    ->update($payload);
            }
        }
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, price: float|string}>  $rows
     */
    protected function updateLocalPrices(array $rows): void
    {
        foreach ($rows as $row) {
            $sku = (string) $row['sku_code'];
            SheinMmMetric::query()->where('sku', $sku)->update(['price' => (float) $row['price']]);

            if (Schema::hasTable('shein_pricing_prices')) {
                SheinPricingPrice::updateOrCreate(
                    ['sku' => trim($sku)],
                    ['price' => (float) $row['price']]
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $pricing
     */
    protected function applyPriceAdjustment(float $price, array $pricing): float
    {
        if ($price <= 0) {
            return 0.0;
        }

        $value = (float) ($pricing['adjustment_value'] ?? 0);
        if ($value == 0.0) {
            return round($price, 2);
        }

        $method = (string) ($pricing['adjustment_method'] ?? 'percent');
        $type = (string) ($pricing['adjustment_type'] ?? 'increase');
        $delta = $method === 'fixed' ? $value : ($price * $value / 100);
        if ($type === 'decrease') {
            $price -= $delta;
        } else {
            $price += $delta;
        }

        return round(max(0, $price), 2);
    }
}
