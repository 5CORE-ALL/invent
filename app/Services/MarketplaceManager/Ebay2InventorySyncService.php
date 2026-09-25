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

    public const PROGRESS_CACHE_KEY = 'mm.ebay2.inv.sync.progress';

    public const MISMATCH_TRIED_KEY = 'mm.ebay2.mismatch.tried';

    public const MISMATCH_BATCH = 16;

    /**
     * @param  array{state?: string, qty_percent?: int, message?: string, updated?: int, failed?: int, skipped?: int}  $data
     */
    public static function setProgress(array $data): void
    {
        $current = self::progress();
        Cache::put(self::PROGRESS_CACHE_KEY, array_merge($current, $data, [
            'updated_at' => now()->toIso8601String(),
        ]), now()->addHours(2));
    }

    /**
     * @return array{state: string, qty_percent: int|null, message: string, updated: int, failed: int, skipped: int}
     */
    public static function progress(): array
    {
        $row = Cache::get(self::PROGRESS_CACHE_KEY);

        return array_merge([
            'state' => 'idle',
            'qty_percent' => null,
            'message' => '',
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0,
        ], is_array($row) ? $row : []);
    }

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

        $preferFixedPrice = self::isTradingLimited();

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

        if ($exactShopifyQty) {
            // Mismatch button / hourly pass: the listings column is shopify_skus.
            // Do not crawl products.json (that times out a 4-SKU batch before eBay is called).
            $shopifyQty = MarketplaceLiveInventoryRules::applyListingsShopifyQtyForPush([], $fetchSkus, true);
            $shopifyQty = $this->overlayExactLiveShopifyQty($shopifyQty, $fetchSkus);
        } else {
            $shopifyQty = app(ShopifyQtySource::class)->fetchQuantitiesForPush(
                $fetchSkus,
                fn (array $need) => $this->fetchLiveShopifyQuantities($need, $shopifyConfig)
            );
            $shopifyQty = MarketplaceLiveInventoryRules::applyListingsShopifyQtyForPush(
                $shopifyQty,
                $fetchSkus,
                false
            );
        }
        $shopifyQty = $this->mergeLocalShopifyQtyFallback($shopifyQty, $fetchSkus);

        $this->ensureMetricsForSkus($skus, false);

        $metrics = $this->metricsForRequestedSkus($skus, $wantedNorms, $wantedUppers);

        $liveMpQty = $this->ebay2QtyMapForSkip($metrics->pluck('sku')->all());
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

            // Mismatch pass already classified live eBay vs the % target — always push.
            // Full SKU sync: skip only when listings qty already equals the exact % target.
            if (! $exactShopifyQty
                && $this->marketplaceQtyAlreadyAtTarget($liveMpQty, $sku, $pushQty)) {
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

        $invResult = $this->pushInventoryRows($inventoryRows, ! $exactShopifyQty, $preferFixedPrice, $exactShopifyQty);
        $pushedRows = $invResult['rows'] ?? [];
        $skipped += (int) ($invResult['skipped'] ?? 0);
        if ($pushedRows !== []) {
            $this->updateLocalStock($pushedRows);
            $this->updateLocalPlatformQuantities($pushedRows);
            $this->updateLocalPrices($this->rowsWithPositivePrice($pushedRows));
            app(Ebay2LiveListingsService::class)->applyPushedInventory($pushedRows);

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
        $qtyPercent = max(0, min(100, (int) ($settings['inventory']['quantity_calc_percent'] ?? 100)));
        $maxQty = $settings['inventory']['max_quantity'] ?? null;
        Log::info('Ebay2InventorySyncService: loaded inventory rule', [
            'qty_percent' => $qtyPercent,
            'inventory_sync' => (bool) ($settings['inventory']['inventory_sync'] ?? false),
            'dry_run' => $dryRun,
        ]);

        $finish = function (array $result) use ($dryRun, $qtyPercent): array {
            if (! $dryRun) {
                $failed = (int) ($result['failed'] ?? 0);
                $updated = (int) ($result['updated'] ?? 0);
                self::setProgress([
                    'state' => ($failed > 0 && $updated === 0) ? 'failed' : 'done',
                    'qty_percent' => $qtyPercent,
                    'message' => (string) ($result['message'] ?? ''),
                    'updated' => $updated,
                    'failed' => $failed,
                    'skipped' => (int) ($result['skipped'] ?? 0),
                ]);
            }

            return $result;
        };

        if (! $dryRun) {
            self::setProgress([
                'state' => 'running',
                'qty_percent' => $qtyPercent,
                'message' => 'Using saved Qty % of Shopify ('.$qtyPercent.'%). Matching eBay 2 inventory…',
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
            ]);
        }

        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return $finish([
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'Inventory and price sync are disabled in settings.',
            ]);
        }

        if (! $this->ebay2Api->isConfigured()) {
            return $finish([
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'eBay 2 API credentials missing.',
            ]);
        }

        // Inv SKU Mismatch first. A full catalog crawl burns Trading API 518
        // before those SKUs get Shopify qty. When ReviseInventoryStatus is
        // already capped, the mismatch pass still pushes via ReviseFixedPriceItem.
        if (! $dryRun && ($settings['inventory']['inventory_sync'] ?? false)) {
            $priority = app(MarketplaceMismatchInventoryPass::class)->run('ebay2', self::MISMATCH_BATCH);
            $remaining = (int) ($priority['remaining'] ?? 0);
            $rateLimited = ! empty($priority['rate_limited']);
            if ($rateLimited || $remaining > 0) {
                $message = trim((string) ($priority['message'] ?? 'Pushed Inv SKU Mismatch first.'));
                if ($remaining > 0) {
                    $message .= ' Full eBay 2 catalog crawl skipped until remaining mismatch SKUs are pushed.';
                }

                return $finish([
                    'updated' => (int) ($priority['updated'] ?? 0),
                    'failed' => (int) ($priority['failed'] ?? 0),
                    'skipped' => (int) ($priority['skipped'] ?? 0),
                    'price_updated' => 0,
                    'rate_limited' => $rateLimited,
                    'message' => $message,
                ]);
            }
        }

        if (! Schema::hasTable('ebay_2_metrics')) {
            return $finish([
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'ebay_2_metrics table missing. Run Sync link map on Listings first.',
            ]);
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
            return $finish([
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'No eBay 2 SKU mappings found. Run Sync link map on Listings first.',
            ]);
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
            $known = MarketplaceLiveInventoryRules::skusWithKnownQty(
                $skus,
                fn (string $sku) => $this->resolveShopifyQty($shopifyQty, $sku)
            );
            Log::warning('Ebay2InventorySyncService: Shopify coverage low — pushing confirmed qtys only', $coverage + ['kept' => count($known)]);
            if ($known === []) {
                return $finish([
                    'updated' => 0,
                    'failed' => 0,
                    'skipped' => count($skus),
                    'price_updated' => 0,
                    'message' => $coverage['message'],
                ]);
            }
            $skus = $known;
        }

        $shopifyDetails = ($settings['pricing']['price_sync'] ?? false)
            ? $this->shopifyApi->getProductDetailsBySkuMap($skus)
            : [];

        $useSalePrice = (bool) ($settings['pricing']['use_sale_price'] ?? false);

        $liveMpQty = $this->ebay2QtyMapForSkip($skus);
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
                // Skip only when listings qty already equals the exact % target.
                // Do not use ebay_stock or the 3-unit match bar — those hid 20% pushes
                // while live eBay still had full Shopify qty.
                if ($this->marketplaceQtyAlreadyAtTarget($liveMpQty, $sku, $pushQty)) {
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

        Log::info('Ebay2InventorySyncService: inventory rows queued', [
            'qty_percent' => $qtyPercent,
            'queued' => count($inventoryRows),
            'skipped_already_at_target' => $skipped,
        ]);

        if ($dryRun) {
            return $finish([
                'updated' => count($inventoryRows),
                'failed' => 0,
                'skipped' => $skipped,
                'price_updated' => count($priceRows),
                'message' => '[dry-run] Would update '.count($inventoryRows).' inventory row(s), '.count($priceRows).' price-only row(s).',
            ]);
        }

        $updated = 0;
        $failed = 0;
        $priceUpdated = 0;
        $rateLimited = false;
        $invNote = '';

        if ($inventoryRows !== []) {
            $invResult = $this->pushInventoryRows($inventoryRows);
            $updated = (int) ($invResult['pushed'] ?? 0);
            $failed = (int) ($invResult['failed'] ?? 0);
            $skipped += (int) ($invResult['skipped'] ?? 0);
            $rateLimited = ! empty($invResult['rate_limited']);
            $invNote = trim((string) ($invResult['message'] ?? ''));
            $pushedRows = $invResult['rows'] ?? [];
            if ($pushedRows !== []) {
                $this->updateLocalStock($pushedRows);
                $this->updateLocalPlatformQuantities($pushedRows);
                $this->updateLocalPrices($this->rowsWithPositivePrice($pushedRows));
                app(Ebay2LiveListingsService::class)->clearCache();
            } elseif ($failed > 0 || $rateLimited) {
                Log::warning('Ebay2InventorySyncService: inventory push failed', $invResult);
            }
        }

        if ($priceRows !== []) {
            $successfulPriceRows = [];
            foreach ($priceRows as $row) {
                $result = $this->ebay2Api->reviseFixedPriceItem(
                    (string) $row['product_id'],
                    (float) $row['price'],
                    null,
                    (string) $row['sku_code']
                );
                if (! empty($result['success'])) {
                    $priceUpdated++;
                    $successfulPriceRows[] = $row;
                }
            }
            if ($successfulPriceRows !== []) {
                $this->updateLocalPrices($successfulPriceRows);
            }
        }

        $message = "Updated {$updated} inventory, {$priceUpdated} price(s); failed {$failed}; skipped {$skipped}.";
        if ($invNote !== '' && ($updated === 0 || $failed > 0 || $rateLimited)) {
            $message .= ' '.$invNote;
        }

        return $finish([
            'updated' => $updated,
            'failed' => $failed,
            'skipped' => $skipped,
            'price_updated' => $priceUpdated,
            'rate_limited' => $rateLimited,
            'message' => $message,
        ]);
    }

    protected function appendMismatchPass(bool $run, bool $rateLimited = false): string
    {
        if ($rateLimited) {
            return ' Mismatch pass skipped (eBay 2 API usage limit).';
        }
        if (! $run) {
            return '';
        }

        $pass = app(MarketplaceMismatchInventoryPass::class)->run('ebay2');

        return ' '.trim((string) ($pass['message'] ?? ''));
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int, price?: float|null}>  $inventoryRows
     * @return array{success: bool, pushed: int, failed: int, skipped: int, rate_limited: bool, message?: string, rows: array<int, array<string, mixed>>}
     */
    protected function pushInventoryRows(array $inventoryRows, bool $allowRelist = true, bool $preferFixedPrice = false, bool $confirmLive = false): array
    {
        $pushed = 0;
        $failed = 0;
        $skipped = 0;
        $lastMessage = null;
        $errorSamples = [];
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

            $batchCounted = false;
            if (! $confirmLive && ! $preferFixedPrice && count($chunk) >= 2) {
                $attempted += count($chunk);
                $batchCounted = true;
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
                if (self::looksLikeTradingLimit($msg)) {
                    $preferFixedPrice = true;
                    $lastMessage = $msg;
                    Log::warning('Ebay2InventorySyncService: ReviseInventoryStatus 518 — falling back to ReviseFixedPriceItem', [
                        'batch' => count($chunk),
                    ]);
                } elseif (! empty($result['success'])) {
                    $confirmed = array_fill_keys($result['confirmed_indexes'] ?? [], true);
                    $pending = [];
                    foreach ($chunk as $index => $row) {
                        if (isset($confirmed[$index])) {
                            $pushedRows[] = $row;
                            $pushed++;
                        } else {
                            $pending[] = $row;
                        }
                    }
                    foreach ($pending as $row) {
                        if ($rateLimited) {
                            break;
                        }
                        usleep(350000);
                        $one = $this->pushOneInventoryRow($row, $allowRelist, true, false);
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
                    continue;
                } else {
                    $lastMessage = $msg !== '' ? $msg : 'Batch ReviseInventoryStatus failed.';
                }
            }

            foreach ($chunk as $index => $row) {
                if ($rateLimited) {
                    break;
                }
                if (! $batchCounted) {
                    if ($attempted > 0) {
                        usleep(350000);
                    }
                    $attempted++;
                } elseif ($index > 0) {
                    usleep(350000);
                }
                $one = $this->pushOneInventoryRow($row, $allowRelist, $preferFixedPrice, $confirmLive);
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
                    $err = trim((string) ($one['message'] ?? ''));
                    if ($err !== '') {
                        $errorSamples[$err] = ($errorSamples[$err] ?? 0) + 1;
                    }
                }
            }
        }

        $remaining = max(0, count($valid) - $attempted);
        $message = $lastMessage;
        if ($failed > 0 && $errorSamples !== [] && ! $rateLimited) {
            arsort($errorSamples);
            $top = array_key_first($errorSamples);
            $message = $failed.' eBay 2 update(s) failed. '.$top;
        }
        if ($rateLimited) {
            $skipped += $remaining;
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
    protected function pushOneInventoryRow(array $row, bool $allowRelist = true, bool $preferFixedPrice = false, bool $confirmLive = false): array
    {
        $itemId = trim((string) ($row['product_id'] ?? ''));
        $sku = trim((string) ($row['sku_code'] ?? ''));
        $qty = max(0, (int) ($row['inventory'] ?? 0));
        $price = $row['price'] ?? null;
        $price = ($price !== null && (float) $price > 0) ? (float) $price : null;
        $usedQtyOnlyFallback = false;
        $triedVariationRevise = false;
        $result = ['success' => false, 'message' => ''];
        $msg = '';

        try {
            if (! $preferFixedPrice) {
                $result = $this->ebay2Api->reviseInventoryStatus($itemId, $qty, $sku, $price);
                $msg = (string) ($result['message'] ?? '');
                if (self::looksLikeTradingLimit($msg)) {
                    $preferFixedPrice = true;
                    $msg = '';
                }
            }
            if (! $preferFixedPrice && empty($result['success']) && self::looksLikeMissingNameValueList($msg)) {
                $itemLevel = $this->ebay2Api->reviseInventoryStatus($itemId, $qty, null, null);
                $itemMsg = (string) ($itemLevel['message'] ?? '');
                if (self::looksLikeTradingLimit($itemMsg)) {
                    $preferFixedPrice = true;
                } elseif (! empty($itemLevel['success'])) {
                    $result = $itemLevel;
                    $msg = $itemMsg;
                    $row['price'] = null;
                }
            }
            if (! $preferFixedPrice && empty($result['success']) && self::looksLikeSkuMismatch($msg)) {
                foreach (self::skuAliasesForPush($sku) as $alias) {
                    if (strcasecmp($alias, $sku) === 0) {
                        continue;
                    }
                    $aliasResult = $this->ebay2Api->reviseInventoryStatus($itemId, $qty, $alias, $price);
                    $aliasMsg = (string) ($aliasResult['message'] ?? '');
                    if (self::looksLikeTradingLimit($aliasMsg)) {
                        $preferFixedPrice = true;
                        break;
                    }
                    if (! empty($aliasResult['success'])) {
                        $result = $aliasResult;
                        $msg = $aliasMsg;
                        $row['sku_code'] = $alias;
                        $sku = $alias;
                        break;
                    }
                }
            }
            if (! $preferFixedPrice && empty($result['success']) && (! empty($result['ended']) || $this->ebay2Api->listingLooksEnded($msg))) {
                if ($allowRelist) {
                    $relist = $this->ebay2Api->relistFixedPriceItem($itemId, $sku, $qty);
                    $relistMsg = (string) ($relist['message'] ?? '');
                    if (self::looksLikeTradingLimit($relistMsg)) {
                        $preferFixedPrice = true;
                    } elseif (! empty($relist['success'])) {
                        $newId = trim((string) ($relist['item_id'] ?? $itemId));
                        if ($newId !== '' && $newId !== $itemId) {
                            Ebay2Metric::query()->where('item_id', $itemId)->update(['item_id' => $newId]);
                            $itemId = $newId;
                            $row['product_id'] = $itemId;
                        }
                        $result = $this->ebay2Api->reviseInventoryStatus($itemId, $qty, $sku, $price);
                        $msg = (string) ($result['message'] ?? '');
                        if (self::looksLikeTradingLimit($msg)) {
                            $preferFixedPrice = true;
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
            if ($preferFixedPrice || empty($result['success']) || (isset($result['quantity_confirmed']) && $result['quantity_confirmed'] === false)) {
                $triedVariationRevise = true;
                $fallback = $this->ebay2Api->reviseVariationQuantity($itemId, $sku, $qty);
                $fallbackMsg = (string) ($fallback['message'] ?? '');
                if (self::looksLikeTradingLimit($fallbackMsg)) {
                    self::markTradingLimited();

                    return ['ok' => false, 'rate_limited' => true, 'message' => $fallbackMsg];
                }
                if (! empty($fallback['success'])) {
                    $result = $fallback;
                    $usedQtyOnlyFallback = true;
                    $row['price'] = null;
                } elseif (empty($result['success'])) {
                    $result = $fallback;
                }
            }

            if (! empty($result['success'])) {
                $needsLiveCheck = $confirmLive || ! ($result['quantity_confirmed'] ?? true);
                if ($needsLiveCheck) {
                    $liveQty = $this->ebay2Api->variationAvailableQty($itemId, $sku);
                    $liveMatches = $liveQty !== null && (int) $liveQty === $qty;
                    if (! $liveMatches && ! $usedQtyOnlyFallback && ! $triedVariationRevise) {
                        $fallback = $this->ebay2Api->reviseVariationQuantity($itemId, $sku, $qty);
                        $fallbackMsg = (string) ($fallback['message'] ?? '');
                        if (self::looksLikeTradingLimit($fallbackMsg)) {
                            self::markTradingLimited();

                            return ['ok' => false, 'rate_limited' => true, 'message' => $fallbackMsg];
                        }
                        if (! empty($fallback['success'])) {
                            $result = $fallback;
                            $usedQtyOnlyFallback = true;
                            $row['price'] = null;
                            $liveQty = $this->ebay2Api->variationAvailableQty($itemId, $sku);
                            $liveMatches = $liveQty !== null && (int) $liveQty === $qty;
                        }
                    }
                    if (self::rejectUnconfirmedEbayQty($liveQty, $qty, $usedQtyOnlyFallback)) {
                        return [
                            'ok' => false,
                            'rate_limited' => false,
                            'message' => 'eBay 2 did not confirm quantity '.$qty.' (live '.$liveQty.')',
                        ];
                    }
                    if ($liveMatches) {
                        $row['inventory'] = (int) $liveQty;
                    }
                }
                if ($usedQtyOnlyFallback) {
                    $row['price'] = null;
                }

                return ['ok' => true, 'rate_limited' => false, 'row' => $row, 'message' => (string) ($result['message'] ?? '')];
            }

            $lastMessage = (string) ($result['message'] ?? 'ReviseInventoryStatus failed');
            if (self::looksLikeTradingLimit($lastMessage)) {
                self::markTradingLimited();

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
            $limited = self::looksLikeTradingLimit($e->getMessage());
            if ($limited) {
                self::markTradingLimited();
            }

            return [
                'ok' => false,
                'rate_limited' => $limited,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * ReviseFixedPriceItem often returns Success before GetItem shows the new
     * available qty. Keep that push. Reject only a ReviseInventoryStatus no-op
     * whose live qty is still the old number.
     */
    public static function rejectUnconfirmedEbayQty(?int $liveQty, int $desired, bool $fixedPriceAccepted): bool
    {
        if ($fixedPriceAccepted) {
            return false;
        }

        return $liveQty !== null && (int) $liveQty !== $desired;
    }

    /**
     * True eBay Trading API daily cap — not a random "518" inside an ItemID.
     */
    public static function looksLikeTradingLimit(?string $message): bool
    {
        $m = strtolower((string) $message);
        if ($m === '') {
            return false;
        }

        if (str_contains($m, 'usage limit')
            || str_contains($m, 'call usage')
            || str_contains($m, 'apiaccessrules')
            || str_contains($m, 'getapiaccessrules')
            || str_contains($m, 'ebay #518')
            || str_contains($m, 'error #518')
            || (bool) preg_match('/error\s+518\b/', $m)
            || (bool) preg_match('/errorcode["\s:>]*518\b/', $m)) {
            return true;
        }

        return str_contains($m, 'exceeded')
            && str_contains($m, 'limit')
            && (str_contains($m, 'call') || str_contains($m, 'usage'));
    }

    public static function looksLikeSkuMismatch(?string $message): bool
    {
        $m = strtolower((string) $message);
        if ($m === '') {
            return false;
        }

        return str_contains($m, 'sku does not exist')
            || str_contains($m, 'variation not found')
            || str_contains($m, 'invalid sku')
            || str_contains($m, 'no variation')
            || str_contains($m, '21916626')
            || str_contains($m, '21919188');
    }

    public static function looksLikeMissingNameValueList(?string $message): bool
    {
        $m = strtolower((string) $message);
        if ($m === '') {
            return false;
        }

        return str_contains($m, '21916587')
            || str_contains($m, 'missing name in name-value')
            || str_contains($m, 'missing name in name value');
    }

    /**
     * Hyphen / space / compact SKU forms eBay listings often store instead of Shopify.
     *
     * @return list<string>
     */
    public static function skuAliasesForPush(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return [];
        }

        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        $compact = ShopifySku::compactSkuForLookup($sku);
        $hyphen = $norm !== '' ? str_replace(' ', '-', $norm) : '';
        $out = [];
        foreach ([$sku, $norm, $hyphen, $compact] as $alias) {
            $alias = trim((string) $alias);
            if ($alias !== '') {
                $out[$alias] = $alias;
            }
        }

        return array_values($out);
    }

    /**
     * Rotate through mismatch SKUs so one failing batch cannot block the rest.
     *
     * @param  list<string>  $skus
     * @return array{batch: list<string>, remaining: int}
     */
    public static function takeMismatchBatch(array $skus, ?int $limit = null): array
    {
        return MarketplaceMismatchBatch::take('ebay2', $skus, $limit);
    }

    protected function isEbayUsageLimit(?string $message): bool
    {
        return self::looksLikeTradingLimit($message);
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
     * Linked eBay 2 rows for the SKUs we are about to push. Prefer ACTIVE listings
     * when the same SKU has ended duplicates.
     *
     * @param  list<string>  $skus
     * @param  array<string, true>  $wantedNorms
     * @param  array<string, true>  $wantedUppers
     * @return \Illuminate\Support\Collection<int, Ebay2Metric>
     */
    protected function metricsForRequestedSkus(array $skus, array $wantedNorms, array $wantedUppers)
    {
        $query = Ebay2Metric::query()
            ->whereNotNull('item_id')
            ->where('sku', '!=', '')
            ->whereColumn('sku', '!=', 'item_id')
            ->where(function ($q) use ($skus, $wantedUppers) {
                $q->whereIn('sku', $skus);
                foreach (array_keys($wantedUppers) as $upper) {
                    $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [$upper]);
                }
            });

        $hasStatus = Schema::hasColumn('ebay_2_metrics', 'listing_status');

        return $query->get()
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
            ->groupBy(function (Ebay2Metric $metric) {
                $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $metric->sku);

                return $norm !== '' ? $norm : strtoupper(trim((string) $metric->sku));
            })
            ->map(function ($group) use ($hasStatus) {
                if ($hasStatus) {
                    $active = $group->first(function (Ebay2Metric $metric) {
                        return in_array(strtoupper(trim((string) ($metric->listing_status ?? ''))), ['ACTIVE', 'LIVE'], true);
                    });
                    if ($active) {
                        return $active;
                    }
                }

                return $group->sortByDesc('id')->first();
            })
            ->filter()
            ->values();
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
     * Refresh the same Shopify qty the mismatch table shows, for this batch only.
     *
     * @param  array<string, int>  $shopifyQty
     * @param  list<string>  $skus
     * @return array<string, int>
     */
    protected function overlayExactLiveShopifyQty(array $shopifyQty, array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($sku) => trim((string) $sku),
            $skus
        ))));
        if ($skus === []) {
            return $shopifyQty;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($skus), '?'));
            $rows = ShopifySku::query()
                ->whereRaw('UPPER(TRIM(sku)) in ('.$placeholders.')', array_map('strtoupper', $skus))
                ->get()
                ->all();
            $live = MarketplaceListingStockResolver::liveShopifyQtyMapForRows($rows, true);
        } catch (\Throwable $e) {
            Log::warning('Ebay2InventorySyncService: exact Shopify qty refresh failed', [
                'error' => $e->getMessage(),
            ]);

            return $shopifyQty;
        }

        foreach ($live as $key => $qty) {
            $shopifyQty[(string) $key] = (int) $qty;
        }

        return $shopifyQty;
    }

    /**
     * @param  array<int, string>  $skus
     * @param  array{store_url?: string, token?: string}|null  $shopifyConfig
     * @return array<string, int>
     */
    protected function fetchLiveShopifyQuantities(array $skus, ?array $shopifyConfig = null): array
    {
        try {
            unset($shopifyConfig);

            return $this->shopifyApi->getInventoryQuantitiesBySku($skus);
        } catch (\Throwable $e) {
            Log::warning('Ebay2InventorySyncService: live Shopify fetch failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Same listings qty the mismatch pass uses (live cache + pricing/mappings).
     *
     * @param  array<int, string>  $skus
     * @return array<string, int>
     */
    protected function ebay2QtyMapForSkip(array $skus): array
    {
        return EbayInventoryUnchangedSkip::qtyMapForSkip(
            MarketplaceListingStockResolver::CHANNEL_EBAY2,
            $skus
        );
    }

    /**
     * Skip only when listings already show the exact % target. Never treat
     * stale ebay_2_metrics.ebay_stock or the 3-unit match bar as "already pushed".
     *
     * @param  array<string, int>  $liveMpQty
     */
    protected function marketplaceQtyAlreadyAtTarget(array $liveMpQty, string $sku, int $pushQty): bool
    {
        return EbayInventoryUnchangedSkip::qtyAlreadyAtTarget($liveMpQty, $sku, $pushQty);
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

            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            $query = Ebay2Metric::query()
                ->where(function ($q) use ($sku, $norm) {
                    $q->where('sku', $sku)->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)]);
                    if ($norm !== '' && $norm !== strtoupper(trim($sku))) {
                        $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [$norm]);
                    }
                });
            if ($itemId !== '') {
                $query->where('item_id', $itemId);
            }
            $query->update(['ebay_stock' => $qty]);
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
            $sku = trim((string) ($row['sku_code'] ?? ''));
            $price = $row['price'] ?? null;
            if ($sku === '' || $price === null || (float) $price <= 0) {
                continue;
            }
            Ebay2Metric::query()->where('sku', $sku)->update(['ebay_price' => (float) $price]);
        }
    }

    /**
     * @param  array<int, array{price?: float|null}>  $rows
     * @return list<array<string, mixed>>
     */
    protected function rowsWithPositivePrice(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $price = $row['price'] ?? null;
            if ($price !== null && (float) $price > 0) {
                $out[] = $row;
            }
        }

        return $out;
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
