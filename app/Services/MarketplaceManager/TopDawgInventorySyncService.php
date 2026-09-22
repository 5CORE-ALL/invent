<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\ProductStockMapping;
use App\Models\ShopifySku;
use App\Models\TopDawgProduct;
use App\Services\ShopifyApiService;
use App\Services\TopDawgApiService;
use App\Support\Marketplace\MappingChannelCounts;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TopDawgInventorySyncService
{
    public function __construct(
        protected TopDawgApiService $topdawgApi,
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

        if (! $this->topdawgApi->isConfigured()) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'TopDawg API credentials missing.'];
        }

        $settings = MarketplaceSyncSettings::getFor('topdawg');
        $qtyPercent = max(0, min(100, (int) ($settings['inventory']['quantity_calc_percent'] ?? 100)));
        $maxQty = $settings['inventory']['max_quantity'] ?? null;

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

        $shopifyQty = MarketplaceLiveInventoryRules::applyListingsShopifyQtyForPush(
            $shopifyQty,
            $fetchSkus,
            $exactShopifyQty
        );

        $metrics = $this->metricsForRequestedSkus($skus, $wantedNorms);

        $inventoryRows = [];
        $skipped = 0;

        foreach ($metrics as $metric) {
            $sku = (string) $metric->sku;
            $productId = (string) $metric->topdawg_listing_id;
            if (! MarketplaceLiveInventoryRules::isLinked($productId, $sku)) {
                $skipped++;
                continue;
            }

            $shopifyStock = $this->resolveShopifyQty($shopifyQty, $sku);
            foreach ($skus as $requested) {
                if ($shopifyStock !== null) {
                    break;
                }
                if (ShopifySku::skusMatch($requested, $sku)) {
                    $shopifyStock = $this->resolveShopifyQty($shopifyQty, $requested);
                }
            }
            if ($shopifyStock === null) {
                $skipped++;
                continue;
            }

            $pushQty = MarketplaceLiveInventoryRules::qtyForMismatchPush(
                $shopifyStock,
                $exactShopifyQty,
                $qtyPercent,
                $maxQty
            );

            $inventoryRows[] = [
                'product_id' => $productId,
                'sku_code' => $sku,
                'inventory' => MarketplaceLiveInventoryRules::clampPushQty($pushQty, $shopifyStock),
                'shopify_qty' => $shopifyStock,
            ];
        }

        if ($inventoryRows === []) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => $skipped,
                'message' => 'No linked TopDawg SKUs found for inventory sync.',
            ];
        }

        $invResult = $this->pushInventoryRows($inventoryRows);
        $pushed = (int) ($invResult['pushed'] ?? 0);
        $failed = (int) ($invResult['failed'] ?? 0);
        $updatedSkus = $invResult['updated_skus'] ?? [];

        if ($pushed > 0) {
            $this->persistLocalStock($inventoryRows, $updatedSkus);
            $this->clearListingCaches();
        }

        return [
            'updated' => $pushed,
            'failed' => $failed,
            'skipped' => $skipped,
            'message' => $invResult['message']
                ?? ($pushed > 0
                    ? 'Synced '.$pushed.' SKU(s) to TopDawg from live Shopify.'
                    : 'TopDawg inventory update failed.'),
        ];
    }

    /**
     * Full inventory sync from Shopify → TopDawg for all linked SKUs.
     *
     * @return array{updated: int, failed: int, skipped: int, message: string}
     */
    public function syncAllFromShopify(bool $dryRun = false): array
    {
        $settings = MarketplaceSyncSettings::getFor('topdawg');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'Inventory/price sync disabled in TopDawg settings.',
            ];
        }

        if (! $this->topdawgApi->isConfigured()) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'TopDawg API credentials missing.',
            ];
        }

        $skus = TopDawgProduct::query()
            ->whereNotNull('sku')
            ->whereNotNull('topdawg_listing_id')
            ->where('sku', '!=', '')
            ->whereColumn('sku', '!=', 'topdawg_listing_id')
            ->pluck('sku')
            ->map(static fn ($s) => trim((string) $s))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($skus === []) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'No linked TopDawg SKUs. Run Sync TopDawg link map first.',
            ];
        }

        if ($dryRun) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => '[dry-run] Would sync '.count($skus).' linked SKU(s).',
            ];
        }

        $priority = app(MarketplaceMismatchInventoryPass::class)->runAndStopIfMore('topdawg');
        if ($priority !== null) {
            return [
                'updated' => (int) ($priority['updated'] ?? 0),
                'failed' => (int) ($priority['failed'] ?? 0),
                'skipped' => (int) ($priority['skipped'] ?? 0),
                'message' => (string) ($priority['message'] ?? ''),
            ];
        }

        return $this->syncSkusFromShopify($skus);
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int}>  $inventoryRows
     * @return array{success: bool, pushed: int, failed: int, message?: string, updated_skus: list<string>}
     */
    protected function pushInventoryRows(array $inventoryRows): array
    {
        $items = [];
        foreach ($inventoryRows as $row) {
            $items[] = [
                'sku' => (string) $row['sku_code'],
                'quantity' => (int) $row['inventory'],
            ];
        }

        $bulk = $this->topdawgApi->updateItemInventoryBulk($items);

        return [
            'success' => ($bulk['pushed'] ?? 0) > 0,
            'pushed' => (int) ($bulk['pushed'] ?? 0),
            'failed' => (int) ($bulk['failed'] ?? 0),
            'updated_skus' => $bulk['updated_skus'] ?? [],
            'message' => $bulk['error_message'] ?? null,
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
            Log::warning('TopDawgInventorySyncService: live Shopify fetch failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Hyphen / space / compact aliases so mismatch rows like "C10BP 20 10 R"
     * still find TopDawg product_code "C10BP-20-10-R".
     *
     * @return list<string>
     */
    public static function skuAliasesForPush(string $sku): array
    {
        $sku = trim($sku);
        $out = [];
        foreach ([
            $sku,
            strtoupper($sku),
            ShopifySku::normalizeSkuForShopifyLookup($sku),
            str_replace('-', ' ', $sku),
            preg_replace('/\s+/', '-', $sku) ?: '',
            str_replace(' ', '', $sku),
            ShopifySku::compactSkuForLookup($sku),
        ] as $alias) {
            $alias = trim((string) $alias);
            if ($alias !== '' && ! in_array($alias, $out, true)) {
                $out[] = $alias;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $skus
     * @param  array<string, true>  $wantedNorms
     * @return \Illuminate\Support\Collection<int, TopDawgProduct>
     */
    protected function metricsForRequestedSkus(array $skus, array $wantedNorms)
    {
        $aliases = [];
        $wantedCompact = [];
        foreach ($skus as $sku) {
            foreach (self::skuAliasesForPush($sku) as $alias) {
                $aliases[$alias] = true;
                $aliases[strtoupper($alias)] = true;
            }
            $compact = ShopifySku::compactSkuForLookup($sku);
            if ($compact !== '') {
                $wantedCompact[$compact] = true;
            }
        }
        $aliasList = array_keys($aliases);

        $found = TopDawgProduct::query()
            ->whereNotNull('topdawg_listing_id')
            ->where('sku', '!=', '')
            ->whereColumn('sku', '!=', 'topdawg_listing_id')
            ->where(function ($q) use ($skus, $aliasList) {
                $q->whereIn('sku', $skus);
                foreach (array_chunk($aliasList, 80) as $chunk) {
                    $q->orWhereIn('sku', $chunk);
                    $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                    $q->orWhereRaw('UPPER(TRIM(sku)) in ('.$placeholders.')', array_map('strtoupper', $chunk));
                }
            })
            ->get();

        $matched = $found->filter(function (TopDawgProduct $metric) use ($wantedNorms, $wantedCompact, $skus) {
            $raw = (string) $metric->sku;
            if (in_array($raw, $skus, true)) {
                return true;
            }
            $norm = ShopifySku::normalizeSkuForShopifyLookup($raw);
            if ($norm !== '' && isset($wantedNorms[$norm])) {
                return true;
            }
            $compact = ShopifySku::compactSkuForLookup($raw);

            return $compact !== '' && isset($wantedCompact[$compact]);
        })->values();

        $have = [];
        foreach ($matched as $metric) {
            $raw = (string) $metric->sku;
            $norm = ShopifySku::normalizeSkuForShopifyLookup($raw);
            if ($norm !== '') {
                $have[$norm] = true;
            }
            $compact = ShopifySku::compactSkuForLookup($raw);
            if ($compact !== '') {
                $have[$compact] = true;
            }
        }
        $missingNorms = array_diff_key($wantedNorms, $have);
        $missingCompact = array_diff_key($wantedCompact, $have);
        if ($missingNorms === [] && $missingCompact === []) {
            return $matched;
        }

        TopDawgProduct::query()
            ->whereNotNull('topdawg_listing_id')
            ->where('sku', '!=', '')
            ->whereColumn('sku', '!=', 'topdawg_listing_id')
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$matched, &$missingNorms, &$missingCompact) {
                foreach ($rows as $row) {
                    $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku);
                    $compact = ShopifySku::compactSkuForLookup((string) $row->sku);
                    $hit = ($norm !== '' && isset($missingNorms[$norm]))
                        || ($compact !== '' && isset($missingCompact[$compact]));
                    if (! $hit) {
                        continue;
                    }
                    $matched->push($row);
                    if ($norm !== '') {
                        unset($missingNorms[$norm]);
                    }
                    if ($compact !== '') {
                        unset($missingCompact[$compact]);
                    }
                }

                return $missingNorms !== [] || $missingCompact !== [];
            });

        return $matched->unique('id')->values();
    }

    /**
     * @param  array<string, int>  $map
     */
    protected function resolveShopifyQty(array $map, string $sku): ?int
    {
        foreach (self::skuAliasesForPush($sku) as $alias) {
            if (array_key_exists($alias, $map)) {
                return (int) $map[$alias];
            }
            $upper = strtoupper($alias);
            if (array_key_exists($upper, $map)) {
                return (int) $map[$upper];
            }
        }

        return null;
    }

    /**
     * After TopDawg accepts a push, store that qty as listings TopDawg Qty.
     *
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int}>  $rows
     * @param  list<string>  $updatedSkus
     */
    protected function persistLocalStock(array $rows, array $updatedSkus = []): void
    {
        $allow = [];
        foreach ($updatedSkus as $sku) {
            $sku = trim((string) $sku);
            if ($sku !== '') {
                $allow[strtoupper($sku)] = true;
            }
        }

        foreach ($rows as $row) {
            $sku = trim((string) $row['sku_code']);
            if ($sku === '') {
                continue;
            }
            if ($allow !== [] && ! isset($allow[strtoupper($sku)])) {
                continue;
            }
            $qty = max(0, (int) $row['inventory']);
            $upper = strtoupper($sku);

            TopDawgProduct::query()
                ->where(function ($q) use ($sku, $upper) {
                    $q->where('sku', $sku)
                        ->orWhereRaw('UPPER(TRIM(sku)) = ?', [$upper]);
                })
                ->update(['remaining_inventory' => $qty]);

            if (Schema::hasTable('product_stock_mappings')
                && Schema::hasColumn('product_stock_mappings', 'inventory_topdawg')) {
                ProductStockMapping::query()
                    ->where(function ($q) use ($sku, $upper) {
                        $q->where('sku', $sku)
                            ->orWhereRaw('UPPER(TRIM(sku)) = ?', [$upper]);
                    })
                    ->update(['inventory_topdawg' => $qty]);
            }
        }
    }

    protected function clearListingCaches(): void
    {
        try {
            app(TopDawgLiveListingsService::class)->clearCache();
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            Cache::forget(MarketplaceListingQtyMatchService::CACHE_PREFIX.'topdawg');
            MappingChannelCounts::forgetMasterCaches();
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
