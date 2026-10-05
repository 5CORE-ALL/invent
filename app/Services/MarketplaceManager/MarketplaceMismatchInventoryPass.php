<?php

namespace App\Services\MarketplaceManager;

use App\Models\AlibabaMetric;
use App\Models\AliexpressMetric;
use App\Models\FaireMetric;
use App\Models\MarketplaceSyncSettings;
use App\Models\NeweggMetric;
use App\Models\ReverbMetric;
use App\Models\SheinListingStatus;
use App\Models\SheinMmMetric;
use App\Models\EbayMetric;
use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\ShopifySku;
use App\Models\TopDawgProduct;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Second pass after a full marketplace inventory sync: detect remaining
 * Shopify↔marketplace qty mismatches and push those SKUs once more.
 */
final class MarketplaceMismatchInventoryPass
{
    /**
     * @return array{attempted: int, updated: int, failed: int, skipped: int, remaining: int, rate_limited: bool, message: string}
     */
    public function run(string $channel, ?int $limit = null): array
    {
        $channel = strtolower(trim($channel));
        $empty = [
            'attempted' => 0,
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0,
            'remaining' => 0,
            'rate_limited' => false,
            'message' => 'Mismatch pass skipped.',
        ];

        if (! in_array($channel, ['newegg', 'shein', 'topdawg', 'temu', 'temu2', 'temu3', 'purchasingpower', 'wayfair', 'bestbuy', 'macy', 'doba', 'ebay1', 'ebay2', 'ebay3', 'reverb', 'aliexpress', 'alibaba', 'faire', 'amazon', 'tiktok', 'tiktok2', 'pls', 'b5cb2b'], true)) {
            return $empty;
        }

        if ($channel === 'amazon') {
            $zeros = app(AmazonInventorySyncService::class)->syncConfirmedZerosFromShopify(250);
            if ((int) ($zeros['updated'] ?? 0) > 0) {
                $empty['updated'] = (int) $zeros['updated'];
                $empty['failed'] = (int) ($zeros['failed'] ?? 0);
                $empty['skipped'] = (int) ($zeros['skipped'] ?? 0);
                $empty['message'] = (string) ($zeros['message'] ?? 'Pushed Shopify-zero SKUs to Amazon.');
            }
        }
        if (! self::syncEnabled($channel)) {
            if ($channel === 'amazon' && (int) ($empty['updated'] ?? 0) > 0) {
                return $empty;
            }

            return array_merge($empty, ['message' => 'Mismatch pass skipped (inventory and price sync off).']);
        }

        $linked = $this->linkedSkus($channel);
        if ($linked === []) {
            return array_merge($empty, ['message' => 'Mismatch pass: no linked SKUs.']);
        }

        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        if (! $catalog->tablesReady() || ! $catalog->hasAnyActive()) {
            return array_merge($empty, ['message' => 'Mismatch pass: Shopify live catalog empty.']);
        }

        $mpStock = $this->stockMap($channel, $catalog->filterLinkedToVerified($linked));
        $classified = $catalog->classifyLinkedInventoryMatch($linked, $mpStock, marketplace: $channel);
        $mismatch = array_values(array_unique(array_merge(
            $classified['mismatch'] ?? [],
            $classified['linked_mismatch'] ?? [],
            $this->pageMismatchSkus($channel)
        )));

        if ($mismatch === []) {
            return array_merge($empty, ['message' => 'Mismatch pass: no qty-mismatch SKUs remaining.']);
        }

        $remaining = 0;
        // PLS is a Shopify store (2 req/s, ~7 Admin calls per SKU), so rotate batches too.
        if (in_array($channel, ['ebay2', 'tiktok', 'tiktok2', 'shein', 'topdawg', 'pls'], true)) {
            $sliced = MarketplaceMismatchBatch::take($channel, $mismatch, $limit);
            $remaining = (int) ($sliced['remaining'] ?? 0);
            $mismatch = $sliced['batch'] ?? [];
            if ($mismatch === []) {
                return array_merge($empty, [
                    'remaining' => $remaining,
                    'message' => 'Mismatch pass: no qty-mismatch SKUs remaining.',
                ]);
            }
        }

        Log::info('MarketplaceMismatchInventoryPass: pushing mismatch SKUs', [
            'channel' => $channel,
            'count' => count($mismatch),
            'remaining' => $remaining,
        ]);

        $result = $this->pushSkus($channel, $mismatch);

        $updated = (int) ($result['updated'] ?? 0);
        $failed = (int) ($result['failed'] ?? 0);
        $skipped = (int) ($result['skipped'] ?? 0);
        $detail = trim((string) ($result['message'] ?? ''));
        $message = sprintf(
            'Mismatch pass: attempted %d, updated %d, failed %d, skipped %d.',
            count($mismatch),
            $updated,
            $failed,
            $skipped
        );
        if ($remaining > 0) {
            $message .= ' '.$remaining.' mismatch SKU(s) left for the next run.';
        }
        if ($detail !== '' && ($updated === 0 || ! empty($result['rate_limited']))) {
            $message .= ' '.$detail;
        }

        return [
            'attempted' => count($mismatch),
            'updated' => $updated,
            'failed' => $failed,
            'skipped' => $skipped,
            'remaining' => $remaining,
            'rate_limited' => ! empty($result['rate_limited']),
            'message' => $message,
        ];
    }

    /**
     * SKUs /map-issues counts for this channel. The pass's own classification can miss them
     * (different stock source), which left those SKUs for a manual push every day.
     *
     * @return list<string>
     */
    public function pageMismatchSkus(string $channel): array
    {
        try {
            return app(MarketplaceListingQtyMatchService::class)->activeMismatchSkus($channel, false);
        } catch (\Throwable $e) {
            Log::warning('MarketplaceMismatchInventoryPass: page mismatch list failed', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Manual "Sync mismatch" buttons run when inventory OR price sync is on; automatic runs follow the same rule.
     */
    public static function syncEnabled(string $channel): bool
    {
        $settings = MarketplaceSyncSettings::getFor(strtolower(trim($channel)));

        return (bool) ($settings['inventory']['inventory_sync'] ?? false)
            || (bool) ($settings['pricing']['price_sync'] ?? false);
    }

    /**
     * Push Shopify qty for the given SKUs with the channel's inventory sync service.
     *
     * @param  list<string>  $skus
     * @return array<string, mixed>
     */
    public function pushSkus(string $channel, array $skus): array
    {
        $channel = strtolower(trim($channel));
        if ($skus === []) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'No SKUs to push.'];
        }

        return match ($channel) {
            'newegg' => app(NeweggInventorySyncService::class)->syncSkusFromShopify($skus, null, true),
            'shein' => app(SheinInventorySyncService::class)->syncSkusFromShopify($skus, null, true),
            'topdawg' => app(TopDawgInventorySyncService::class)->syncSkusFromShopify($skus, null, true),
            'temu' => app(TemuInventorySyncService::class)->syncSkusFromShopify($skus, null, true),
            'temu2' => app(Temu2InventorySyncService::class)->syncSkusFromShopify($skus, null, true),
            'temu3' => app(Temu3InventorySyncService::class)->syncSkusFromShopify($skus, null, true),
            'pls' => app(PlsInventorySyncService::class)->syncSkusFromShopify($skus),
            'b5cb2b' => app(B5cB2bInventorySyncService::class)->syncSkusFromShopify($skus, null, true),
            'purchasingpower' => app(PurchasingPowerInventorySyncService::class)->syncSkusFromShopify($skus, null, true),
            'wayfair' => app(WayfairInventorySyncService::class)->syncSkusFromShopify($skus, null, true),
            'bestbuy' => app(BestBuyInventorySyncService::class)->syncSkusFromShopify($skus, null, true),
            'macy' => app(MacyInventorySyncService::class)->syncSkusFromShopify($skus, null, true),
            'doba' => app(DobaInventorySyncService::class)->syncSkusFromShopify($skus, null, true),
            'ebay1' => app(Ebay1InventorySyncService::class)->syncSkusFromShopify($skus, null, true),
            'ebay2' => app(Ebay2InventorySyncService::class)->syncSkusFromShopify($skus, null, true),
            'ebay3' => app(Ebay3InventorySyncService::class)->syncSkusFromShopify($skus, null, true),
            'reverb' => app(ReverbInventorySyncService::class)->syncSkusFromShopify($skus, null, true),
            'aliexpress' => app(AliexpressInventorySyncService::class)->syncSkusFromShopify($skus, null, true),
            'alibaba' => app(AlibabaInventorySyncService::class)->syncSkusFromShopify($skus, null, true),
            'faire' => app(FaireInventorySyncService::class)->syncSkusFromShopify($skus, null, true),
            'amazon' => app(AmazonInventorySyncService::class)->syncSkusFromShopify($skus, null, true),
            'tiktok' => app(TikTokInventorySyncService::class)->syncSkusFromShopify($skus, null, true),
            'tiktok2' => app(TikTok2InventorySyncService::class)->syncSkusFromShopify($skus, null, true),
            default => ['updated' => 0, 'failed' => 0, 'skipped' => count($skus), 'message' => 'Channel not supported.'],
        };
    }

    /**
     * Push a mismatch batch first. Non-null means the caller should skip the
     * full catalog crawl (more mismatch SKUs remain, or the channel is rate-limited).
     *
     * @return array{attempted: int, updated: int, failed: int, skipped: int, remaining: int, rate_limited: bool, message: string}|null
     */
    public function runAndStopIfMore(string $channel, int $limit = MarketplaceMismatchBatch::DEFAULT_LIMIT): ?array
    {
        $priority = $this->run($channel, $limit);
        if (empty($priority['rate_limited']) && (int) ($priority['remaining'] ?? 0) === 0) {
            return null;
        }

        $message = trim((string) ($priority['message'] ?? 'Pushed Inv SKU Mismatch first.'));
        if ((int) ($priority['remaining'] ?? 0) > 0) {
            $message .= ' Full catalog crawl skipped until remaining mismatch SKUs are pushed.';
        }
        $priority['message'] = $message;

        return $priority;
    }

    /**
     * @return list<string>
     */
    public function linkedSkus(string $channel): array
    {
        $table = match ($channel) {
            'newegg' => 'newegg_metric',
            'shein' => 'shein_metric',
            'topdawg' => 'topdawg_products',
            'temu' => 'temu_metrics',
            'temu2' => 'temu2_metrics',
            'temu3' => 'temu3_metrics',
            'purchasingpower' => 'purchasing_power_products',
            'wayfair' => 'wayfair_pricing_prices',
            'bestbuy' => 'bestbuy_usa_products',
            'macy' => 'macy_products',
            'doba' => 'doba_metrics',
            'ebay1' => 'ebay_metrics',
            'ebay2' => 'ebay_2_metrics',
            'ebay3' => 'ebay_3_metrics',
            'reverb' => 'reverb_metric',
            'aliexpress' => 'aliexpress_metric',
            'alibaba' => 'alibaba_metric',
            'faire' => 'faire_metric',
            'amazon' => 'amazon_listing_statuses',
            'tiktok' => 'tiktok_products',
            'tiktok2' => 'tiktok_products_two',
            default => null,
        };
        if ($table === null || ! Schema::hasTable($table)) {
            return [];
        }

        if ($channel === 'topdawg') {
            return TopDawgProduct::query()
                ->whereNotNull('sku')
                ->whereNotNull('topdawg_listing_id')
                ->where('sku', '!=', '')
                ->where('topdawg_listing_id', '!=', '')
                ->whereColumn('sku', '!=', 'topdawg_listing_id')
                ->pluck('sku')
                ->map(static fn ($sku) => trim((string) $sku))
                ->filter(static fn (string $sku) => $sku !== '')
                ->unique(static fn (string $sku) => ShopifySku::normalizeSkuForShopifyLookup($sku))
                ->values()
                ->all();
        }

        if ($channel === 'tiktok' || $channel === 'tiktok2') {
            return TikTokListingsPageBuilder::for($channel)->linkedSkus();
        }

        if ($channel === 'temu') {
            return \App\Models\TemuMetric::query()
                ->whereNotNull('sku')
                ->whereNotNull('goods_id')
                ->where('sku', '!=', '')
                ->where('goods_id', '!=', '')
                ->whereColumn('sku', '!=', 'goods_id')
                ->pluck('sku')
                ->map(static fn ($sku) => trim((string) $sku))
                ->filter(static fn (string $sku) => $sku !== '')
                ->unique(static fn (string $sku) => ShopifySku::normalizeSkuForShopifyLookup($sku))
                ->values()
                ->all();
        }

        if ($channel === 'temu2' || $channel === 'temu3') {
            $metricModel = $channel === 'temu3' ? \App\Models\Temu3Metric::class : \App\Models\Temu2Metric::class;

            return $metricModel::query()
                ->whereNotNull('sku')
                ->whereNotNull('goods_id')
                ->where('sku', '!=', '')
                ->where('goods_id', '!=', '')
                ->whereColumn('sku', '!=', 'goods_id')
                ->pluck('sku')
                ->map(static fn ($sku) => trim((string) $sku))
                ->filter(static fn (string $sku) => $sku !== '')
                ->unique(static fn (string $sku) => ShopifySku::normalizeSkuForShopifyLookup($sku))
                ->values()
                ->all();
        }

        if ($channel === 'purchasingpower') {
            return \App\Models\PurchasingPowerProduct::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->pluck('sku')
                ->map(static fn ($sku) => trim((string) $sku))
                ->filter(static fn (string $sku) => $sku !== '')
                ->unique(static fn (string $sku) => ShopifySku::normalizeSkuForShopifyLookup($sku))
                ->values()
                ->all();
        }

        if ($channel === 'wayfair') {
            return \App\Models\WayfairPricingPrice::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->pluck('sku')
                ->map(static fn ($sku) => trim((string) $sku))
                ->filter(static fn (string $sku) => $sku !== '')
                ->unique(static fn (string $sku) => ShopifySku::normalizeSkuForShopifyLookup($sku))
                ->values()
                ->all();
        }

        if ($channel === 'bestbuy') {
            return \App\Models\BestbuyUsaProduct::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->pluck('sku')
                ->map(static fn ($sku) => trim((string) $sku))
                ->filter(static fn (string $sku) => $sku !== '')
                ->unique(static fn (string $sku) => ShopifySku::normalizeSkuForShopifyLookup($sku))
                ->values()
                ->all();
        }

        if ($channel === 'macy') {
            return \App\Models\MacyProduct::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->pluck('sku')
                ->map(static fn ($sku) => trim((string) $sku))
                ->filter(static fn (string $sku) => $sku !== '')
                ->unique(static fn (string $sku) => ShopifySku::normalizeSkuForShopifyLookup($sku))
                ->values()
                ->all();
        }

        if ($channel === 'doba') {
            return \App\Models\DobaMetric::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->whereNotNull('item_id')
                ->where('item_id', '!=', '')
                ->pluck('sku')
                ->map(static fn ($sku) => trim((string) $sku))
                ->filter(static fn (string $sku) => $sku !== '')
                ->unique(static fn (string $sku) => ShopifySku::normalizeSkuForShopifyLookup($sku))
                ->values()
                ->all();
        }

        if ($channel === 'amazon') {
            return AmazonListingStatusHelper::linkedSkus();
        }

        if ($channel === 'pls') {
            return app(PlsListingsPageBuilder::class)->linkedSkus();
        }

        if ($channel === 'b5cb2b') {
            if (! Schema::hasTable('b5c_b2b_products')) {
                return [];
            }

            return \App\Models\B5cB2bProduct::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->pluck('sku')
                ->map(static fn ($sku) => trim((string) $sku))
                ->filter(static fn (string $sku) => $sku !== '')
                ->unique(static fn (string $sku) => ShopifySku::normalizeSkuForShopifyLookup($sku))
                ->values()
                ->all();
        }

        if ($channel === 'shein') {
            $fromMetric = SheinMmMetric::query()
                ->whereNotNull('sku')
                ->whereNotNull('product_id')
                ->where('sku', '!=', '')
                ->where('product_id', '!=', '')
                ->whereColumn('sku', '!=', 'product_id')
                ->pluck('sku')
                ->map(static fn ($sku) => trim((string) $sku))
                ->filter(static fn (string $sku) => $sku !== '')
                ->all();
            $fromListed = SheinListingStatus::listedSellerSkus();

            return collect(array_merge($fromMetric, $fromListed))
                ->unique(static fn (string $sku) => ShopifySku::normalizeSkuForShopifyLookup($sku))
                ->values()
                ->all();
        }

        if ($channel === 'ebay1' || $channel === 'ebay2' || $channel === 'ebay3') {
            $query = match ($channel) {
                'ebay1' => EbayMetric::query(),
                'ebay2' => Ebay2Metric::query(),
                default => Ebay3Metric::query(),
            };

            return $query
                ->whereNotNull('sku')
                ->whereNotNull('item_id')
                ->where('sku', '!=', '')
                ->where('item_id', '!=', '')
                ->whereColumn('sku', '!=', 'item_id')
                ->pluck('sku')
                ->map(static fn ($sku) => trim((string) $sku))
                ->filter(static fn (string $sku) => $sku !== '' && ! MarketplaceLiveInventoryRules::isParentPlaceholderSku($sku))
                ->unique(static fn (string $sku) => ShopifySku::normalizeSkuForShopifyLookup($sku))
                ->values()
                ->all();
        }

        $query = match ($channel) {
            'newegg' => NeweggMetric::query(),
            'shein' => SheinMmMetric::query(),
            'reverb' => ReverbMetric::query(),
            'aliexpress' => AliexpressMetric::query(),
            'alibaba' => AlibabaMetric::query(),
            'faire' => FaireMetric::query(),
            default => null,
        };
        if ($query === null) {
            return [];
        }

        return $query
            ->whereNotNull('sku')
            ->whereNotNull('product_id')
            ->where('sku', '!=', '')
            ->where('product_id', '!=', '')
            ->whereColumn('sku', '!=', 'product_id')
            ->pluck('sku')
            ->map(static fn ($sku) => trim((string) $sku))
            ->filter(static fn (string $sku) => $sku !== '')
            ->unique(static fn (string $sku) => ShopifySku::normalizeSkuForShopifyLookup($sku))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, int>
     */
    public function stockMap(string $channel, array $skus): array
    {
        if ($channel === 'alibaba') {
            return $this->alibabaLocalStockMap($skus);
        }

        $resolverChannel = match ($channel) {
            'newegg' => MarketplaceListingStockResolver::CHANNEL_NEWEGG,
            'shein' => MarketplaceListingStockResolver::CHANNEL_SHEIN,
            'topdawg' => MarketplaceListingStockResolver::CHANNEL_TOPDAWG,
            'temu' => MarketplaceListingStockResolver::CHANNEL_TEMU,
            'temu2' => MarketplaceListingStockResolver::CHANNEL_TEMU2,
            'temu3' => MarketplaceListingStockResolver::CHANNEL_TEMU3,
            'purchasingpower' => MarketplaceListingStockResolver::CHANNEL_PURCHASINGPOWER,
            'wayfair' => MarketplaceListingStockResolver::CHANNEL_WAYFAIR,
            'bestbuy' => MarketplaceListingStockResolver::CHANNEL_BESTBUY,
            'macy' => MarketplaceListingStockResolver::CHANNEL_MACY,
            'doba' => MarketplaceListingStockResolver::CHANNEL_DOBA,
            'ebay1' => MarketplaceListingStockResolver::CHANNEL_EBAY1,
            'ebay2' => MarketplaceListingStockResolver::CHANNEL_EBAY2,
            'ebay3' => MarketplaceListingStockResolver::CHANNEL_EBAY3,
            'reverb' => MarketplaceListingStockResolver::CHANNEL_REVERB,
            'aliexpress' => MarketplaceListingStockResolver::CHANNEL_ALIEXPRESS,
            'faire' => MarketplaceListingStockResolver::CHANNEL_FAIRE,
            'amazon' => MarketplaceListingStockResolver::CHANNEL_AMAZON,
            'tiktok' => MarketplaceListingStockResolver::CHANNEL_TIKTOK,
            'tiktok2' => MarketplaceListingStockResolver::CHANNEL_TIKTOK2,
            'pls' => MarketplaceListingStockResolver::CHANNEL_PLS,
            'b5cb2b' => MarketplaceListingStockResolver::CHANNEL_B5CB2B,
            default => $channel,
        };

        $local = MarketplaceListingStockResolver::stockMapForSkus($resolverChannel, $skus);

        $treatMissingLiveInventoryAsZero = ! in_array($channel, ['aliexpress'], true);

        return MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            $this->peekLiveRows($channel),
            $local,
            $treatMissingLiveInventoryAsZero
        );
    }

    /**
     * Warm listings-cache rows used to split Active vs Inactive SKU tabs.
     *
     * @return list<array<string, mixed>>|null
     */
    public function peekLiveRows(string $channel): ?array
    {
        $channel = strtolower(trim($channel));

        return match ($channel) {
            'newegg' => app(NeweggLiveListingsService::class)->peekCached(),
            'shein' => app(SheinLiveListingsService::class)->peekCached(),
            'topdawg' => app(TopDawgLiveListingsService::class)->peekCached(),
            'temu' => app(TemuLiveListingsService::class)->peekCached(),
            'temu2' => app(Temu2LiveListingsService::class)->peekCached(),
            'temu3' => app(Temu3LiveListingsService::class)->peekCached(),
            'purchasingpower' => app(PurchasingPowerLiveListingsService::class)->peekCached(),
            'wayfair' => app(WayfairLiveListingsService::class)->peekCached(),
            'bestbuy' => app(BestBuyLiveListingsService::class)->peekCached(),
            'macy' => app(MacyLiveListingsService::class)->peekCached(),
            'doba' => app(DobaLiveListingsService::class)->peekCached(),
            'ebay1' => app(Ebay1LiveListingsService::class)->peekCached(),
            'ebay2' => app(Ebay2LiveListingsService::class)->peekCached(),
            'ebay3' => app(Ebay3LiveListingsService::class)->peekCached(),
            'reverb' => app(ReverbLiveListingsService::class)->peekCached(),
            'aliexpress' => app(AliexpressLiveListingsService::class)->peekCached(),
            'faire' => app(FaireLiveListingsService::class)->peekCached(),
            'amazon' => app(AmazonLiveListingsService::class)->peekCached(),
            'alibaba' => app(AlibabaLiveListingsService::class)->peekCached(),
            'tiktok' => app(TikTokLiveListingsService::class)->peekCached(),
            'tiktok2' => app(TikTok2LiveListingsService::class)->peekCached(),
            'pls' => app(PlsLiveListingsService::class)->peekCached(),
            'b5cb2b' => app(B5cB2bLiveListingsService::class)->peekCached(),
            default => null,
        };
    }

    /**
     * Active/inactive split from each channel's local listings catalog.
     * Does not require a warm live cache. Reverb/AliExpress full API warm only when $allowApiWarm.
     *
     * @return list<array<string, mixed>>
     */
    public function localRowsForStateSplit(string $channel, bool $allowApiWarm = false): array
    {
        $channel = strtolower(trim($channel));
        $peeked = $this->peekLiveRows($channel);
        if (is_array($peeked) && $peeked !== []) {
            return $peeked;
        }

        if (in_array($channel, ['reverb', 'aliexpress'], true) && ! $allowApiWarm) {
            return [];
        }

        return $this->liveRowsForStateSplit($channel, true);
    }

    /**
     * Same rows the listings page uses to split Active vs Inactive.
     * Peeks the warm cache, then loads from the channel's local listings source.
     *
     * @return list<array<string, mixed>>
     */
    public function liveRowsForStateSplit(string $channel, bool $fetchIfCold = true): array
    {
        $peeked = $this->peekLiveRows($channel);
        if (is_array($peeked) && $peeked !== []) {
            return $peeked;
        }

        if (! $fetchIfCold) {
            return [];
        }

        $channel = strtolower(trim($channel));

        try {
            $rows = match ($channel) {
                'newegg' => app(NeweggLiveListingsService::class)->all(),
                'shein' => app(SheinLiveListingsService::class)->all(),
                'topdawg' => app(TopDawgLiveListingsService::class)->all(),
                'temu' => app(TemuLiveListingsService::class)->all(),
                'temu2' => app(Temu2LiveListingsService::class)->all(),
                'temu3' => app(Temu3LiveListingsService::class)->all(),
                'purchasingpower' => app(PurchasingPowerLiveListingsService::class)->all(),
                'wayfair' => app(WayfairLiveListingsService::class)->all(),
                'bestbuy' => app(BestBuyLiveListingsService::class)->all(),
                'macy' => app(MacyLiveListingsService::class)->all(),
                'doba' => app(DobaLiveListingsService::class)->all(),
                'ebay1' => app(Ebay1LiveListingsService::class)->all(),
                'ebay2' => app(Ebay2LiveListingsService::class)->all(),
                'ebay3' => app(Ebay3LiveListingsService::class)->all(),
                'reverb' => app(ReverbLiveListingsService::class)->all(),
                'aliexpress' => app(AliexpressLiveListingsService::class)->all(),
                'faire' => app(FaireLiveListingsService::class)->all(),
                'amazon' => app(AmazonLiveListingsService::class)->all(),
                'alibaba' => app(AlibabaLiveListingsService::class)->all(),
                'tiktok' => app(TikTokLiveListingsService::class)->all(),
                'tiktok2' => app(TikTok2LiveListingsService::class)->all(),
                'pls' => app(PlsLiveListingsService::class)->all(),
                'b5cb2b' => app(B5cB2bLiveListingsService::class)->all(),
                default => [],
            };
        } catch (\Throwable $e) {
            Log::warning('MarketplaceMismatchInventoryPass: liveRowsForStateSplit failed', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, int>
     */
    protected function alibabaLocalStockMap(array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable('alibaba_pricing_prices')) {
            return [];
        }

        $keys = [];
        foreach ($skus as $sku) {
            $trim = trim((string) $sku);
            if ($trim === '') {
                continue;
            }
            $keys[] = $trim;
            $keys[] = strtoupper($trim);
        }
        $keys = array_values(array_unique($keys));
        if ($keys === []) {
            return [];
        }

        $map = [];
        \App\Models\AlibabaPricingPrice::query()
            ->whereIn('sku', $keys)
            ->get(['sku', 'ab_stock'])
            ->each(function ($row) use (&$map) {
                $key = strtoupper(trim((string) $row->sku));
                if ($key !== '' && $row->ab_stock !== null) {
                    $map[$key] = (int) $row->ab_stock;
                    $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku);
                    if ($norm !== '') {
                        $map[$norm] = (int) $row->ab_stock;
                    }
                }
            });

        return $map;
    }
}
