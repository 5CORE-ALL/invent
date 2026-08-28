<?php

namespace App\Services\MarketplaceManager;

use App\Models\Ebay2Metric;
use App\Models\MarketplaceSyncSettings;
use App\Models\ProductStockMapping;
use App\Models\ShopifySku;
use App\Services\EbayTwoApiService;
use App\Services\ShopifyApiService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class Ebay2InventorySyncService
{
    public const TRADING_LIMIT_CACHE_KEY = 'mm.ebay2.trading.518.until';

    public function __construct(
        protected EbayTwoApiService $ebay2Api,
        protected ShopifyApiService $shopifyApi
    ) {}

    /**
     * @param  array<int, string>  $skus
     * @param  array{store_url?: string, token?: string}|null  $shopifyConfig
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

        if (! $this->ebay2Api->isConfigured()) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'eBay 2 API credentials missing.'];
        }

        if ($blocked = self::tradingLimitMessage()) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'rate_limited' => true,
                'message' => $blocked,
            ];
        }

        $settings = MarketplaceSyncSettings::getFor('ebay2');
        $qtyPercent = max(0, min(100, (int) ($settings['inventory']['quantity_calc_percent'] ?? 100)));
        $maxQty = $settings['inventory']['max_quantity'] ?? null;

        $fetchSkus = $skus;
        $wantedNorms = [];
        $wantedUppers = [];
        foreach ($skus as $sku) {
            $wantedUppers[strtoupper(trim($sku))] = true;
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '') {
                $wantedNorms[$norm] = true;
                $wantedUppers[$norm] = true;
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
        $shopifyQty = $this->mergeLocalShopifyQtyFallback($shopifyQty, $fetchSkus);

        $this->ensureMetricsForSkus($skus, false);

        $metrics = Ebay2Metric::query()
            ->whereNotNull('item_id')
            ->where('sku', '!=', '')
            ->whereColumn('sku', '!=', 'item_id')
            ->get()
            ->filter(function (Ebay2Metric $metric) use ($wantedNorms, $wantedUppers, $skus) {
                $raw = (string) $metric->sku;
                if (MarketplaceLiveInventoryRules::isParentPlaceholderSku($raw)) {
                    return false;
                }
                if (in_array($raw, $skus, true) || isset($wantedUppers[strtoupper(trim($raw))])) {
                    return true;
                }
                $norm = ShopifySku::normalizeSkuForShopifyLookup($raw);

                return $norm !== '' && isset($wantedNorms[$norm]);
            })
            ->values();

        $inventoryRows = [];
        $skipped = 0;

        foreach ($metrics as $metric) {
            $sku = (string) $metric->sku;
            $itemId = (string) $metric->item_id;
            if (! MarketplaceLiveInventoryRules::isLinked($itemId, $sku)) {
                $skipped++;
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
            if ($shopifyStock === null) {
                $onShopify = ShopifySku::query()
                    ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
                    ->exists();
                if (! $onShopify) {
                    $skipped++;
                    continue;
                }
            }
            $pushQty = MarketplaceLiveInventoryRules::qtyForMismatchPush(
                $shopifyStock,
                $exactShopifyQty,
                $qtyPercent,
                $maxQty
            );
            $pushQty = MarketplaceLiveInventoryRules::clampPushQty($pushQty, $shopifyStock ?? 0);

            $currentMp = $metric->ebay_stock !== null ? (int) $metric->ebay_stock : null;
            if ($currentMp !== null && $currentMp === $pushQty) {
                $skipped++;
                continue;
            }
            if ($shopifyStock !== null && $shopifyStock > 0
                && MarketplaceLiveInventoryRules::qtyWithinMismatchTolerance((int) $shopifyStock, $currentMp, 'ebay2')) {
                $skipped++;
                continue;
            }

            $inventoryRows[] = [
                'product_id' => $itemId,
                'sku_code' => $sku,
                'inventory' => $pushQty,
                'shopify_qty' => $shopifyStock ?? 0,
                // Qty-only on mismatch — StartPrice on variation listings fails ReviseInventoryStatus.
                'price' => $exactShopifyQty ? null : ($metric->ebay_price !== null ? (float) $metric->ebay_price : null),
            ];
        }

        if ($inventoryRows === []) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => $skipped,
                'rate_limited' => false,
                'message' => $skipped > 0
                    ? 'No eBay 2 SKUs needed a push (already at this marketplace Qty % of Shopify).'
                    : 'No linked eBay 2 SKUs found for inventory sync.',
            ];
        }

        $invResult = $this->pushInventoryRows($inventoryRows, ! $exactShopifyQty);
        $pushedRows = $invResult['rows'] ?? [];
        $skipped += (int) ($invResult['skipped'] ?? 0);
        if ($pushedRows !== []) {
            $this->updateLocalStock($pushedRows);
            $this->updateLocalPlatformQuantities($pushedRows);
            app(Ebay2LiveListingsService::class)->clearCache();

            return [
                'updated' => (int) ($invResult['pushed'] ?? count($pushedRows)),
                'failed' => (int) ($invResult['failed'] ?? 0),
                'skipped' => $skipped,
                'rate_limited' => ! empty($invResult['rate_limited']),
                'message' => $invResult['message'] ?? ('Synced '.((int) ($invResult['pushed'] ?? 0)).' SKU(s) to eBay 2 from live Shopify.'),
            ];
        }

        $this->updateLocalPlatformQuantities($inventoryRows, false);

        return [
            'updated' => (int) ($invResult['pushed'] ?? 0),
            'failed' => (int) ($invResult['failed'] ?? count($inventoryRows)),
            'skipped' => $skipped,
            'rate_limited' => ! empty($invResult['rate_limited']),
            'message' => $invResult['message'] ?? 'eBay 2 inventory update failed.',
        ];
    }

    /**
     * @return array{updated: int, failed: int, skipped: int, price_updated: int, message: string}
     */
    public function syncFromShopify(bool $dryRun = false): array
    {
        $settings = MarketplaceSyncSettings::getFor('ebay2');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'Inventory and price sync are disabled in settings.',
            ];
        }

        if (! $this->ebay2Api->isConfigured()) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'eBay 2 API credentials missing.',
            ];
        }

        if ($blocked = self::tradingLimitMessage()) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'rate_limited' => true,
                'message' => $blocked,
            ];
        }

        if (! Schema::hasTable('ebay_2_metrics')) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'ebay_2_metrics table missing. Run Sync link map on Listings first.',
            ];
        }

        $this->ensureMetricsForSkus(
            ShopifySku::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->pluck('sku')
                ->map(static fn ($sku) => trim((string) $sku))
                ->filter(static fn (string $sku) => $sku !== '' && ! MarketplaceLiveInventoryRules::isParentPlaceholderSku($sku))
                ->unique()
                ->values()
                ->all(),
            false
        );

        $metrics = Ebay2Metric::query()
            ->whereNotNull('sku')
            ->whereNotNull('item_id')
            ->where('sku', '!=', '')
            ->whereColumn('sku', '!=', 'item_id')
            ->get()
            ->filter(fn (Ebay2Metric $metric) => ! MarketplaceLiveInventoryRules::isParentPlaceholderSku((string) $metric->sku))
            ->values();

        if ($metrics->isEmpty()) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'No eBay 2 SKU mappings found. Run Sync link map on Listings first.',
            ];
        }

        $skus = $metrics->pluck('sku')->unique()->values()->all();
        Log::info('Ebay2InventorySyncService: fetching live Shopify inventory', ['sku_count' => count($skus)]);
        $shopifyQty = $this->shopifyApi->getInventoryQuantitiesBySku($skus);

        $missing = [];
        foreach ($skus as $sku) {
            if ($this->resolveShopifyQty($shopifyQty, (string) $sku) === null) {
                $missing[] = (string) $sku;
            }
        }
        if ($missing !== []) {
            Log::info('Ebay2InventorySyncService: live variant fallback for missing SKUs', ['count' => count($missing)]);
            foreach ($this->fetchLiveShopifyQuantities($missing) as $sku => $qty) {
                $shopifyQty[$sku] = $qty;
            }
        }
        $shopifyQty = $this->mergeLocalShopifyQtyFallback($shopifyQty, $skus);

        $coverage = MarketplaceLiveInventoryRules::shopifyLiveCoverageReport(
            $skus,
            fn (string $sku) => $this->resolveShopifyQty($shopifyQty, $sku)
        );
        Log::info('Ebay2InventorySyncService: Shopify live coverage', $coverage);
        if (! $coverage['ok'] && ($settings['inventory']['inventory_sync'] ?? false) && ! $dryRun) {
            Log::error('Ebay2InventorySyncService: aborting inventory push — Shopify live coverage too low', $coverage);

            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => count($skus),
                'price_updated' => 0,
                'message' => $coverage['message'],
            ];
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
            $itemId = (string) $metric->item_id;
            if (! MarketplaceLiveInventoryRules::isLinked($itemId, $sku)) {
                $skipped++;
                continue;
            }

            $shopifyStock = $this->resolveShopifyQty($shopifyQty, $sku);
            $pushQty = null;
            if ($settings['inventory']['inventory_sync'] ?? false) {
                $pushQty = $shopifyStock === null
                    ? MarketplaceLiveInventoryRules::qtyWhenMissingFromShopify()
                    : MarketplaceLiveInventoryRules::qtyFromLiveShopify($shopifyStock, $qtyPercent, $maxQty);
            }

            $price = null;
            if ($settings['pricing']['price_sync'] ?? false) {
                $detail = $shopifyDetails[$sku] ?? null;
                if (is_array($detail)) {
                    $price = $useSalePrice
                        ? ($detail['price'] ?? $detail['sale_price'] ?? null)
                        : ($detail['compare_at_price'] ?? $detail['price'] ?? null);
                }
                $price = $this->applyPriceAdjustment((float) ($price ?? 0), $settings['pricing'] ?? []);
                if ($price <= 0) {
                    $price = $metric->ebay_price !== null ? (float) $metric->ebay_price : null;
                }
            }

            if ($pushQty !== null) {
                $pushQty = MarketplaceLiveInventoryRules::clampPushQty($pushQty, $shopifyStock ?? 0);
                $currentMp = $metric->ebay_stock !== null ? (int) $metric->ebay_stock : null;
                $alreadyOk = $currentMp !== null && $currentMp === $pushQty;
                $withinTol = $shopifyStock !== null && $shopifyStock > 0
                    && MarketplaceLiveInventoryRules::qtyWithinMismatchTolerance((int) $shopifyStock, $currentMp, 'ebay2');
                if ($alreadyOk || $withinTol) {
                    $skipped++;
                } else {
                    $inventoryRows[] = [
                        'product_id' => $itemId,
                        'sku_code' => $sku,
                        'inventory' => $pushQty,
                        'shopify_qty' => $shopifyStock ?? 0,
                        'price' => $price,
                    ];
                }
            } elseif ($price !== null && $price > 0) {
                $priceRows[] = [
                    'product_id' => $itemId,
                    'sku_code' => $sku,
                    'price' => $price,
                ];
            }
        }

        if ($dryRun) {
            return [
                'updated' => count($inventoryRows),
                'failed' => 0,
                'skipped' => $skipped,
                'price_updated' => count($priceRows),
                'message' => '[dry-run] Would update '.count($inventoryRows).' inventory row(s), '.count($priceRows).' price-only row(s).',
            ];
        }

        $updated = 0;
        $failed = 0;
        $priceUpdated = 0;

        if ($inventoryRows !== []) {
            $invResult = $this->pushInventoryRows($inventoryRows);
            $updated = (int) ($invResult['pushed'] ?? 0);
            $failed = (int) ($invResult['failed'] ?? 0);
            $pushedRows = $invResult['rows'] ?? [];
            if ($pushedRows !== []) {
                $this->updateLocalStock($pushedRows);
                $this->updateLocalPlatformQuantities($pushedRows);
                app(Ebay2LiveListingsService::class)->clearCache();
            } elseif ($failed > 0) {
                Log::warning('Ebay2InventorySyncService: inventory push failed', $invResult);
            }
        }

        if ($priceRows !== []) {
            foreach ($priceRows as $row) {
                $result = $this->ebay2Api->reviseFixedPriceItem(
                    (string) $row['product_id'],
                    (float) $row['price'],
                    null,
                    (string) $row['sku_code']
                );
                if (! empty($result['success'])) {
                    $priceUpdated++;
                }
            }
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
                .$this->appendMismatchPass(! $dryRun && ($settings['inventory']['inventory_sync'] ?? false)),
        ];
    }

    protected function appendMismatchPass(bool $run): string
    {
        if (! $run) {
            return '';
        }

        $pass = app(MarketplaceMismatchInventoryPass::class)->run('ebay2');

        return ' '.$pass['message'];
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int, price?: float|null}>  $inventoryRows
     * @return array{success: bool, pushed: int, failed: int, skipped: int, rate_limited: bool, message?: string, rows: array<int, array<string, mixed>>}
     */
    protected function pushInventoryRows(array $inventoryRows, bool $allowRelist = true): array
    {
        $pushed = 0;
        $failed = 0;
        $skipped = 0;
        $lastMessage = null;
        $pushedRows = [];
        $rateLimited = false;
        $attempted = 0;

        $valid = [];
        foreach ($inventoryRows as $row) {
            $itemId = trim((string) ($row['product_id'] ?? ''));
            $sku = trim((string) ($row['sku_code'] ?? ''));
            if ($itemId === '' || $sku === '' || MarketplaceLiveInventoryRules::isParentPlaceholderSku($sku)) {
                $failed++;
                continue;
            }
            $price = $row['price'] ?? null;
            $row['inventory'] = max(0, (int) ($row['inventory'] ?? 0));
            $row['price'] = ($price !== null && (float) $price > 0) ? (float) $price : null;
            $valid[] = $row;
        }

        foreach (array_chunk($valid, 4) as $chunk) {
            if ($rateLimited) {
                break;
            }

            if ($attempted > 0) {
                usleep(350000);
            }

            if (count($chunk) >= 2) {
                $attempted += count($chunk);
                $batch = [];
                foreach ($chunk as $row) {
                    $batch[] = [
                        'item_id' => (string) $row['product_id'],
                        'sku' => (string) $row['sku_code'],
                        'quantity' => (int) $row['inventory'],
                        'price' => $row['price'],
                    ];
                }
                $result = $this->ebay2Api->reviseInventoryStatusMany($batch);
                $msg = (string) ($result['message'] ?? '');
                if ($this->isEbayUsageLimit($msg)) {
                    $rateLimited = true;
                    $lastMessage = $msg;
                    $failed += count($chunk);
                    Log::warning('Ebay2InventorySyncService: eBay usage limit — stopping remaining SKUs', [
                        'batch' => count($chunk),
                        'remaining' => max(0, count($valid) - $attempted),
                    ]);
                    break;
                }
                if (! empty($result['success'])) {
                    foreach ($chunk as $row) {
                        $pushedRows[] = $row;
                        $pushed++;
                    }
                    continue;
                }
                $lastMessage = $msg !== '' ? $msg : 'Batch ReviseInventoryStatus failed.';
            }

            foreach ($chunk as $index => $row) {
                if ($rateLimited) {
                    break;
                }
                if (count($chunk) < 2) {
                    if ($attempted > 0) {
                        usleep(350000);
                    }
                    $attempted++;
                } elseif ($index > 0) {
                    usleep(350000);
                }
                $one = $this->pushOneInventoryRow($row, $allowRelist);
                $lastMessage = $one['message'] ?? $lastMessage;
                if (! empty($one['rate_limited'])) {
                    $rateLimited = true;
                    $failed++;
                    break;
                }
                if (! empty($one['ok'])) {
                    $pushedRows[] = $one['row'] ?? $row;
                    $pushed++;
                } elseif (! empty($one['skipped'])) {
                    $skipped++;
                } else {
                    $failed++;
                }
            }
        }

        $remaining = max(0, count($valid) - $attempted);
        $message = $lastMessage;
        if ($rateLimited) {
            $until = self::tradingLimitMessage() ?: 'wait until after midnight Pacific (~12:50 PM IST)';
            $message = 'eBay 2 hit API usage limit (518) after '.$pushed.' update(s). '
                .$remaining.' SKU(s) left unattempted. '.$until
                .($lastMessage ? ' '.$lastMessage : '');
        }

        return [
            'success' => $pushed > 0,
            'pushed' => $pushed,
            'failed' => $failed,
            'skipped' => $skipped,
            'rate_limited' => $rateLimited,
            'message' => $message,
            'rows' => $pushedRows,
        ];
    }

    /**
     * @param  array{product_id: string, sku_code: string, inventory: int, price?: float|null}  $row
     * @return array{ok: bool, rate_limited: bool, skipped?: bool, row?: array, message?: string}
     */
    protected function pushOneInventoryRow(array $row, bool $allowRelist = true): array
    {
        $itemId = trim((string) ($row['product_id'] ?? ''));
        $sku = trim((string) ($row['sku_code'] ?? ''));
        $qty = max(0, (int) ($row['inventory'] ?? 0));
        $price = $row['price'] ?? null;
        $price = ($price !== null && (float) $price > 0) ? (float) $price : null;

        try {
            $result = $this->ebay2Api->reviseInventoryStatus($itemId, $qty, $sku, $price);
            $msg = (string) ($result['message'] ?? '');
            if ($this->isEbayUsageLimit($msg)) {
                return ['ok' => false, 'rate_limited' => true, 'message' => $msg];
            }
            if (empty($result['success']) && (! empty($result['ended']) || $this->ebay2Api->listingLooksEnded($msg))) {
                if ($allowRelist) {
                    $relist = $this->ebay2Api->relistFixedPriceItem($itemId, $sku, $qty);
                    $relistMsg = (string) ($relist['message'] ?? '');
                    if ($this->isEbayUsageLimit($relistMsg)) {
                        return ['ok' => false, 'rate_limited' => true, 'message' => $relistMsg];
                    }
                    if (! empty($relist['success'])) {
                        $newId = trim((string) ($relist['item_id'] ?? $itemId));
                        if ($newId !== '' && $newId !== $itemId) {
                            Ebay2Metric::query()->where('item_id', $itemId)->update(['item_id' => $newId]);
                            $itemId = $newId;
                            $row['product_id'] = $itemId;
                        }
                        $result = $this->ebay2Api->reviseInventoryStatus($itemId, $qty, $sku, $price);
                        $msg = (string) ($result['message'] ?? '');
                        if ($this->isEbayUsageLimit($msg)) {
                            return ['ok' => false, 'rate_limited' => true, 'message' => $msg];
                        }
                    } else {
                        Log::warning('Ebay2InventorySyncService: relist failed', [
                            'item_id' => $itemId,
                            'sku' => $sku,
                            'result' => $relist,
                        ]);

                        return [
                            'ok' => false,
                            'rate_limited' => false,
                            'message' => (string) ($relist['message'] ?? ($result['message'] ?? 'Relist failed')),
                        ];
                    }
                }
            }
            if (empty($result['success']) || (isset($result['quantity_confirmed']) && $result['quantity_confirmed'] === false)) {
                if ($this->isEbayUsageLimit((string) ($result['message'] ?? ''))) {
                    return ['ok' => false, 'rate_limited' => true, 'message' => (string) ($result['message'] ?? '')];
                }
                $fallback = $this->ebay2Api->reviseVariationQuantity($itemId, $sku, $qty);
                $fallbackMsg = (string) ($fallback['message'] ?? '');
                if ($this->isEbayUsageLimit($fallbackMsg)) {
                    return ['ok' => false, 'rate_limited' => true, 'message' => $fallbackMsg];
                }
                if (! empty($fallback['success'])) {
                    $result = $fallback;
                } elseif (empty($result['success'])) {
                    $result = $fallback;
                }
            }

            if (! empty($result['success'])) {
                if (! ($result['quantity_confirmed'] ?? true)) {
                    $liveQty = $this->ebay2Api->variationAvailableQty($itemId, $sku);
                    if ($liveQty !== null && (int) $liveQty === $qty) {
                        $row['inventory'] = $liveQty;
                    } else {
                        return [
                            'ok' => false,
                            'rate_limited' => false,
                            'message' => 'eBay 2 did not confirm quantity '.$qty
                                .($liveQty !== null ? ' (live '.$liveQty.')' : ''),
                        ];
                    }
                }

                return ['ok' => true, 'rate_limited' => false, 'row' => $row, 'message' => (string) ($result['message'] ?? '')];
            }

            $lastMessage = (string) ($result['message'] ?? 'ReviseInventoryStatus failed');
            if ($this->isEbayUsageLimit($lastMessage)) {
                return ['ok' => false, 'rate_limited' => true, 'message' => $lastMessage];
            }
            if (! $allowRelist && $this->ebay2Api->listingLooksEnded($lastMessage)) {
                return [
                    'ok' => false,
                    'skipped' => true,
                    'rate_limited' => false,
                    'message' => 'Listing ended or inactive — mismatch sync updates live qty only (no Relist).',
                ];
            }
            Log::warning('Ebay2InventorySyncService: revise inventory failed', [
                'item_id' => $itemId,
                'sku' => $sku,
                'qty' => $qty,
                'result' => $result,
            ]);

            return ['ok' => false, 'rate_limited' => false, 'message' => $lastMessage];
        } catch (\Throwable $e) {
            Log::warning('Ebay2InventorySyncService: revise inventory exception', [
                'item_id' => $itemId,
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'rate_limited' => $this->isEbayUsageLimit($e->getMessage()),
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function isEbayUsageLimit(?string $message): bool
    {
        $m = strtolower((string) $message);
        if ($m === '') {
            return false;
        }

        $hit = str_contains($m, 'usage limit')
            || str_contains($m, 'call usage')
            || str_contains($m, 'apiaccessrules')
            || str_contains($m, 'getapiaccessrules')
            || str_contains($m, 'ebay #518')
            || (str_contains($m, 'exceeded') && str_contains($m, 'limit'))
            || preg_match('/\b518\b/', $m) === 1;

        if ($hit) {
            self::markTradingLimited();
        }

        return $hit;
    }

    public static function isTradingLimited(): bool
    {
        try {
            $until = Cache::get(self::TRADING_LIMIT_CACHE_KEY);
        } catch (\Throwable $e) {
            return false;
        }

        return is_numeric($until) && now()->timestamp < (int) $until;
    }

    public static function tradingLimitMessage(): ?string
    {
        if (! self::isTradingLimited()) {
            return null;
        }

        try {
            $ts = (int) Cache::get(self::TRADING_LIMIT_CACHE_KEY);
        } catch (\Throwable $e) {
            $ts = 0;
        }
        $untilIst = $ts > 0
            ? Carbon::createFromTimestamp($ts, 'Asia/Kolkata')->format('g:i A')
            : 'midnight Pacific';

        return 'eBay 2 ReviseInventoryStatus daily limit is already used (error 518). '
            .'Do not click Sync Mismatch, Sync link map, or Refresh live until after '
            .$untilIst.' IST (eBay resets around midnight Pacific). '
            .'Scheduled eBay 2 inventory jobs will skip until then.';
    }

    public static function markTradingLimited(): void
    {
        $until = Carbon::now('America/Los_Angeles')->addDay()->startOfDay()->addMinutes(20);
        if ($until->lte(now())) {
            $until = now()->addHours(2);
        }
        try {
            Cache::put(self::TRADING_LIMIT_CACHE_KEY, $until->timestamp, $until);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Re-link Shopify SKUs that eBay still has (including ended / duplicate listings).
     *
     * @param  array<int, string>  $skus
     */
    protected function ensureMetricsForSkus(array $skus, bool $scanEbay = true): void
    {
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '' || MarketplaceLiveInventoryRules::isParentPlaceholderSku($sku)) {
                continue;
            }

            $hasChild = Ebay2Metric::query()
                ->where(function ($q) use ($sku) {
                    $q->where('sku', $sku)->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)]);
                    $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
                    if ($norm !== '' && $norm !== strtoupper($sku)) {
                        $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [$norm]);
                    }
                })
                ->whereNotNull('item_id')
                ->where('item_id', '!=', '')
                ->whereColumn('item_id', '!=', 'sku')
                ->exists();

            $ids = [];
            if ($scanEbay) {
                $ids = $this->ebay2Api->findItemIdsBySku($sku);
            }
            if ($ids === [] && ! $hasChild) {
                $ids = $this->guessItemIdsFromParentMetrics($sku);
            }
            foreach ($ids as $itemId) {
                $itemId = trim((string) $itemId);
                if ($itemId === '') {
                    continue;
                }
                $this->upsertEbay2MetricLink($itemId, $sku);
            }
        }
    }

    /**
     * @return list<string>
     */
    protected function guessItemIdsFromParentMetrics(string $sku): array
    {
        $upper = strtoupper($sku);
        $ids = [];
        Ebay2Metric::query()
            ->where('sku', 'like', 'PARENT%')
            ->whereNotNull('item_id')
            ->where('item_id', '!=', '')
            ->get(['item_id', 'sku'])
            ->each(function (Ebay2Metric $row) use ($upper, &$ids) {
                $key = strtoupper(trim((string) preg_replace('/^PARENT\s*/i', '', (string) $row->sku)));
                if ($key !== '' && str_contains($upper, $key)) {
                    $ids[] = (string) $row->item_id;
                }
            });

        return array_values(array_unique($ids));
    }

    protected function upsertEbay2MetricLink(string $itemId, string $sku): void
    {
        $existing = Ebay2Metric::query()
            ->where('item_id', $itemId)
            ->where('sku', $sku)
            ->first();
        if ($existing) {
            $existing->report_range = now()->toDateString();
            $existing->save();

            return;
        }

        $row = new Ebay2Metric();
        $row->id = ((int) Ebay2Metric::query()->max('id')) + 1;
        $row->item_id = $itemId;
        $row->sku = $sku;
        $row->report_range = now()->toDateString();
        $row->save();
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
            Log::warning('Ebay2InventorySyncService: live Shopify fetch failed', ['error' => $e->getMessage()]);

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
     * Products page qty comes from shopify_skus. If live API missed a SKU, use that
     * so eBay 2 is not zeroed while Shopify still shows stock.
     *
     * @param  array<string, int>  $shopifyQty
     * @param  array<int, string>  $skus
     * @return array<string, int>
     */
    protected function mergeLocalShopifyQtyFallback(array $shopifyQty, array $skus): array
    {
        $missing = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku !== '' && $this->resolveShopifyQty($shopifyQty, $sku) === null) {
                $missing[] = $sku;
            }
        }
        if ($missing === []) {
            return $shopifyQty;
        }

        $local = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($missing);
        if ($local === []) {
            $local = MarketplaceListingStockResolver::catalogShopifyQtyMapForSkus($missing);
        }
        foreach ($missing as $sku) {
            $qty = $this->resolveShopifyQty($local, $sku);
            if ($qty === null) {
                continue;
            }
            $shopifyQty[$sku] = $qty;
        }

        return $shopifyQty;
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int}>  $rows
     */
    protected function updateLocalStock(array $rows): void
    {
        foreach ($rows as $row) {
            $sku = trim((string) $row['sku_code']);
            $itemId = trim((string) $row['product_id']);
            if ($sku === '') {
                continue;
            }
            $qty = (int) $row['inventory'];

            if ($itemId !== '') {
                Ebay2Metric::query()->where('item_id', $itemId)->update(['ebay_stock' => $qty]);
            }

            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            Ebay2Metric::query()
                ->where(function ($q) use ($sku, $norm) {
                    $q->where('sku', $sku)->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)]);
                    if ($norm !== '' && $norm !== strtoupper(trim($sku))) {
                        $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [$norm]);
                    }
                })
                ->update(['ebay_stock' => $qty]);
        }
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int}>  $rows
     */
    protected function updateLocalPlatformQuantities(array $rows, bool $updateEbayStock = true): void
    {
        foreach ($rows as $row) {
            $sku = trim((string) $row['sku_code']);
            if ($sku === '') {
                continue;
            }

            $ebayQty = (int) $row['inventory'];
            $shopifyQty = array_key_exists('shopify_qty', $row) ? (int) $row['shopify_qty'] : $ebayQty;

            if (Schema::hasTable('product_stock_mappings')) {
                $payload = ['inventory_shopify' => $shopifyQty];
                if ($updateEbayStock && Schema::hasColumn('product_stock_mappings', 'inventory_ebay2')) {
                    $payload['inventory_ebay2'] = $ebayQty;
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
            Ebay2Metric::query()->where('sku', $sku)->update(['ebay_price' => (float) $row['price']]);
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
