<?php

namespace App\Services\MarketplaceManager;

use App\Models\AliexpressMetric;
use App\Models\Ebay2Metric;
use App\Models\ReverbMetric;
use App\Models\SheinListingStatus;
use App\Models\ShopifySku;
use App\Services\AliExpressApiService;
use App\Services\AmazonSpApiService;
use App\Services\Business5CoreB2bApiService;
use App\Services\Ebay2ApiService;
use App\Services\FaireApiService;
use App\Services\NeweggApiService;
use App\Services\SheinApiService;
use App\Services\ShopifyCatalogSyncService;
use App\Services\Temu3ApiService;
use App\Services\TopDawgApiService;
use App\Services\WayfairApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Ask the marketplace what a listing's qty really is. After a push the sync
 * services store the push target as the local marketplace qty, so a SKU counted
 * as fixed until the next report import brought the real number back and it
 * reappeared on /map-issues. Channels without a per-SKU read stay unverified.
 */
final class MarketplaceQtyReadBack
{
    public const SUPPORTED = [
        'amazon', 'faire', 'topdawg', 'shein', 'newegg', 'reverb',
        'wayfair', 'ebay2', 'temu3', 'aliexpress', 'pls', 'b5cb2b',
    ];

    /** One API call per SKU (or per listing); a run reads at most this many. */
    public const PER_SKU_CAP = 80;

    /** Channels answered per SKU/listing; the rest take a batch (or whole-catalog) query. */
    private const PER_SKU_CHANNELS = ['amazon', 'topdawg', 'ebay2', 'aliexpress'];

    /** Whole-catalog reads: one paged pull answers every SKU, so slicing gains nothing. */
    private const WHOLE_CATALOG_CHANNELS = ['temu3', 'b5cb2b', 'pls'];

    /** Batch channels take this many SKUs per sweep run (keeps Wayfair/Faire/Newegg calls bounded). */
    public const BATCH_CAP = 400;

    /** PLS reads one variant per SKU up to here; above it, one paged catalog pull is cheaper. */
    private const PLS_PER_VARIANT_MAX = 30;

    /** @var array<string, array<string, mixed>> per-SKU extras (item_id, status, sku_id…) from the last read */
    private array $meta = [];

    public static function supports(string $channel): bool
    {
        return in_array(strtolower(trim($channel)), self::SUPPORTED, true);
    }

    /**
     * How many SKUs one sweep run should hand to read() for this channel.
     * Whole-catalog channels return PHP_INT_MAX (take everything).
     */
    public static function sweepSliceSize(string $channel): int
    {
        $channel = strtolower(trim($channel));
        if (in_array($channel, self::WHOLE_CATALOG_CHANNELS, true)) {
            return PHP_INT_MAX;
        }

        return in_array($channel, self::PER_SKU_CHANNELS, true) ? self::PER_SKU_CAP : self::BATCH_CAP;
    }

    /**
     * Marketplace qty keyed by the requested SKU. SKUs the marketplace did not return are left out.
     *
     * @param  list<string>  $skus
     * @return array<string, int>
     */
    public function read(string $channel, array $skus): array
    {
        $channel = strtolower(trim($channel));
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ), static fn (string $s) => $s !== '')));
        if ($skus === [] || ! self::supports($channel)) {
            return [];
        }

        $this->meta = [];
        try {
            return match ($channel) {
                'amazon' => $this->amazon($skus),
                'faire' => $this->faire($skus),
                'topdawg' => $this->topdawg($skus),
                'shein' => $this->shein($skus),
                'newegg' => $this->newegg($skus),
                'reverb' => $this->reverb($skus),
                'wayfair' => $this->wayfair($skus),
                'ebay2' => $this->ebay2($skus),
                'temu3' => $this->temu3($skus),
                'aliexpress' => $this->aliexpress($skus),
                'pls' => $this->pls($skus),
                'b5cb2b' => $this->b5cb2b($skus),
                default => [],
            };
        } catch (\Throwable $e) {
            Log::warning('MarketplaceQtyReadBack: read failed', [
                'channel' => $channel,
                'skus' => count($skus),
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Read the marketplace and store that qty as the channel's local listing qty,
     * so /map-issues and the retry loop judge the real listing, not the push target.
     *
     * @param  list<string>  $skus
     * @return array<string, int> what the marketplace reported, keyed by requested SKU
     */
    public function record(string $channel, array $skus): array
    {
        $channel = strtolower(trim($channel));
        $read = $this->read($channel, $skus);
        if ($read === []) {
            return [];
        }

        try {
            match ($channel) {
                'amazon' => app(AmazonInventorySyncService::class)->recordMarketplaceQty($read),
                'faire' => app(FaireInventorySyncService::class)->recordMarketplaceQty($read),
                'topdawg' => app(TopDawgInventorySyncService::class)->recordMarketplaceQty($read),
                'shein' => app(SheinInventorySyncService::class)->recordMarketplaceQty($read),
                'newegg' => app(NeweggInventorySyncService::class)->recordMarketplaceQty($read),
                'reverb' => app(ReverbInventorySyncService::class)->recordMarketplaceQty($read),
                'wayfair' => app(WayfairInventorySyncService::class)->recordMarketplaceQty($read),
                'ebay2' => app(Ebay2InventorySyncService::class)->recordMarketplaceQty($read, $this->meta),
                'temu3' => app(Temu3InventorySyncService::class)->recordMarketplaceQty($read, $this->meta),
                'aliexpress' => app(AliexpressInventorySyncService::class)->recordMarketplaceQty($read),
                'pls' => app(PlsInventorySyncService::class)->recordMarketplaceQty($read),
                'b5cb2b' => app(B5cB2bInventorySyncService::class)->recordMarketplaceQty($read),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::warning('MarketplaceQtyReadBack: local record failed', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }

        return $read;
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, int>
     */
    private function amazon(array $skus): array
    {
        $api = app(AmazonSpApiService::class);
        if (method_exists($api, 'isConfigured') && ! $api->isConfigured()) {
            return [];
        }
        $out = [];
        foreach (array_slice($skus, 0, self::PER_SKU_CAP) as $i => $sku) {
            if ($i > 0) {
                usleep(250000);
            }
            $details = $api->getListingsItemFullDetails($sku);
            if (isset($details['quantity']) && is_numeric($details['quantity'])) {
                $out[$sku] = max(0, (int) $details['quantity']);
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, int>
     */
    private function faire(array $skus): array
    {
        $live = app(FaireApiService::class)->getInventoryBySkus($skus);
        $byUpper = [];
        foreach ($live as $faireSku => $row) {
            if (is_array($row) && isset($row['qty']) && is_numeric($row['qty'])) {
                $byUpper[strtoupper(trim((string) $faireSku))] = max(0, (int) $row['qty']);
            }
        }

        return $this->matchRequested($skus, $byUpper);
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, int>
     */
    private function topdawg(array $skus): array
    {
        $api = app(TopDawgApiService::class);
        if (! $api->isConfigured()) {
            return [];
        }
        $out = [];
        foreach (array_slice($skus, 0, self::PER_SKU_CAP) as $sku) {
            $qty = $api->readLiveQty($sku);
            if ($qty !== null) {
                $out[$sku] = max(0, (int) $qty);
            }
        }

        return $out;
    }

    /**
     * Shein full-detail takes platform skuCodes, never the seller SKU.
     *
     * @param  list<string>  $skus
     * @return array<string, int>
     */
    private function shein(array $skus): array
    {
        $api = app(SheinApiService::class);
        $resolved = $api->resolvePlatformSkuCodesForSellerSkus(
            $skus,
            SheinListingStatus::spuNamesForSellerSkus($skus),
            true
        );

        $skuByCode = [];
        foreach ($skus as $sku) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            $hit = ($norm !== '' && isset($resolved[$norm])) ? $resolved[$norm] : ($resolved[$sku] ?? null);
            $code = trim((string) ($hit['sku_code'] ?? ''));
            if ($code !== '' && $api->isPlatformSkuCode($code, $sku)) {
                $skuByCode[$code] = $sku;
            }
        }
        if ($skuByCode === []) {
            return [];
        }

        $out = [];
        foreach ($api->getStock(array_keys($skuByCode)) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $code = trim((string) ($row['shein_sku_code'] ?? ''));
            $sku = $skuByCode[$code] ?? null;
            if ($sku === null || ! isset($row['quantity']) || ! is_numeric($row['quantity'])) {
                continue;
            }
            $out[$sku] = max(0, (int) $row['quantity']);
        }

        return $out;
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, int>
     */
    private function newegg(array $skus): array
    {
        $api = app(NeweggApiService::class);
        $out = [];
        foreach (array_chunk($skus, 100) as $chunk) {
            $res = $api->getBatchInventory($chunk);
            if (empty($res['ok']) || ! is_array($res['json'] ?? null)) {
                continue;
            }
            $items = $api->indexBatchItemsBySellerPartNumber($api->extractBatchItemList($res['json']));
            foreach ($chunk as $sku) {
                $item = $items[$api->batchSellerPartKey($sku)] ?? null;
                if (! is_array($item)) {
                    continue;
                }
                $row = $api->extractInventoryRowForCountry($item);
                if (is_array($row) && isset($row['AvailableQuantity']) && is_numeric($row['AvailableQuantity'])) {
                    $out[$sku] = max(0, (int) $row['AvailableQuantity']);
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, int>
     */
    private function reverb(array $skus): array
    {
        if (! Schema::hasTable('reverb_metric')) {
            return [];
        }
        $skuByListingId = [];
        $upperToRequested = [];
        foreach ($skus as $sku) {
            $upperToRequested[strtoupper($sku)] = $sku;
        }
        ReverbMetric::query()
            ->whereIn('sku', $skus)
            ->whereNotNull('product_id')
            ->where('product_id', '!=', '')
            ->get(['sku', 'product_id'])
            ->each(function ($metric) use (&$skuByListingId, $upperToRequested) {
                $sku = trim((string) $metric->sku);
                $listingId = trim((string) $metric->product_id);
                if ($listingId === '' || ! MarketplaceLiveInventoryRules::isLinked($listingId, $sku)) {
                    return;
                }
                $requested = $upperToRequested[strtoupper($sku)] ?? $sku;
                $skuByListingId[$listingId] = $requested;
            });
        if ($skuByListingId === []) {
            return [];
        }

        $out = [];
        $details = app(ReverbLiveListingsService::class)->liveDetailsByListingIds(array_keys($skuByListingId));
        foreach ($details as $listingId => $row) {
            $sku = $skuByListingId[(string) $listingId] ?? null;
            if ($sku === null || ! is_array($row) || ! isset($row['inventory']) || ! is_numeric($row['inventory'])) {
                continue;
            }
            $out[$sku] = max(0, (int) $row['inventory']);
        }

        return $out;
    }

    /**
     * Wayfair catalog quantityOnHand. Only the catalog source counts: the supplier-catalog
     * answer hardcodes 0 and the purchase-order fallback sums PO lines, neither is stock.
     *
     * @param  list<string>  $skus
     * @return array<string, int>
     */
    private function wayfair(array $skus): array
    {
        $api = app(WayfairApiService::class);
        if (! $api->isConfigured()) {
            return [];
        }
        $byUpper = [];
        foreach (array_chunk($skus, 100) as $chunk) {
            $res = $api->lookupInventoryBySkus($chunk);
            if (($res['source'] ?? '') !== 'catalog') {
                if (! empty($res['error'])) {
                    Log::info('MarketplaceQtyReadBack: wayfair catalog read unavailable', ['error' => $res['error'], 'source' => $res['source'] ?? null]);
                }
                continue;
            }
            foreach ($res['items'] ?? [] as $key => $item) {
                if (! is_array($item) || ! isset($item['quantity']) || ! is_numeric($item['quantity'])) {
                    continue;
                }
                $qty = max(0, (int) $item['quantity']);
                $byUpper[strtoupper(trim((string) $key))] = $qty;
                $sku = trim((string) ($item['sku'] ?? ''));
                if ($sku !== '') {
                    $byUpper[strtoupper($sku)] = $qty;
                }
            }
        }

        return $this->matchRequested($skus, $byUpper);
    }

    /**
     * eBay 2 GetItem per listing (variations share one call). Ended listings read as 0 and
     * their status is recorded so they leave the mismatch tab.
     *
     * @param  list<string>  $skus
     * @return array<string, int>
     */
    private function ebay2(array $skus): array
    {
        $api = app(Ebay2ApiService::class);
        if (! $api->isConfigured() || ! Schema::hasTable('ebay_2_metrics') || Ebay2InventorySyncService::isTradingLimited()) {
            return [];
        }
        $hasStatus = Schema::hasColumn('ebay_2_metrics', 'listing_status');

        $upperToRequested = [];
        foreach ($skus as $sku) {
            $upperToRequested[strtoupper($sku)] = $sku;
        }
        $skusByItem = [];
        $endedOnly = [];
        Ebay2Metric::query()
            ->whereNotNull('item_id')
            ->where('item_id', '!=', '')
            ->where(function ($q) use ($skus) {
                $q->whereIn('sku', $skus)->orWhereIn('sku', array_map('strtoupper', $skus));
            })
            ->get($hasStatus ? ['sku', 'item_id', 'listing_status'] : ['sku', 'item_id'])
            ->each(function ($metric) use (&$skusByItem, &$endedOnly, $upperToRequested, $hasStatus) {
                $sku = trim((string) $metric->sku);
                $itemId = trim((string) $metric->item_id);
                if ($itemId === '' || ! MarketplaceLiveInventoryRules::isLinked($itemId, $sku)) {
                    return;
                }
                $requested = $upperToRequested[strtoupper($sku)] ?? $sku;
                $status = $hasStatus ? strtoupper(trim((string) $metric->listing_status)) : '';
                if (in_array($status, ['ENDED', 'SOLD', 'UNSOLD', 'COMPLETED'], true)) {
                    $endedOnly[$requested][] = $itemId;

                    return;
                }
                $skusByItem[$itemId][$requested] = true;
            });
        // A SKU with only ended rows: still read one of them (it may have been relisted under that id).
        foreach ($endedOnly as $requested => $itemIds) {
            $alreadyLive = false;
            foreach ($skusByItem as $members) {
                if (isset($members[$requested])) {
                    $alreadyLive = true;
                    break;
                }
            }
            if (! $alreadyLive) {
                $skusByItem[(string) $itemIds[0]][$requested] = true;
            }
        }
        if ($skusByItem === []) {
            return [];
        }

        $out = [];
        $calls = 0;
        foreach ($skusByItem as $itemId => $members) {
            if ($calls >= self::PER_SKU_CAP) {
                break;
            }
            $calls++;
            $raw = $api->getItem((string) $itemId);
            if (! is_array($raw) || ! isset($raw['Item']) || ! is_array($raw['Item'])) {
                continue;
            }
            $item = $raw['Item'];
            $status = strtolower(trim((string) ($item['SellingStatus']['ListingStatus'] ?? '')));
            $ended = in_array($status, ['completed', 'ended'], true);
            foreach (array_keys($members) as $sku) {
                $sku = (string) $sku;
                $qty = $ended ? 0 : EbayLiveListingMapper::quantityFromGetItem($item, $sku);
                if ($qty === null) {
                    continue;
                }
                $out[$sku] = max(0, (int) $qty);
                $this->meta[$sku] = ['item_id' => (string) $itemId, 'status' => $status];
            }
            usleep(150000);
        }

        return $out;
    }

    /**
     * Temu 3 per-SKU stock from bg.local.goods.sku.list.query (one paged pull covers the shop).
     * Also hands back sku_id / goods_id / status so rows created without them get repaired.
     *
     * @param  list<string>  $skus
     * @return array<string, int>
     */
    private function temu3(array $skus): array
    {
        $api = app(Temu3ApiService::class);
        if (! $api->isConfigured()) {
            return [];
        }
        $snapshot = $api->skuStockSnapshotFromApi();
        if ($snapshot === []) {
            return [];
        }
        $byUpper = [];
        $metaByUpper = [];
        foreach ($snapshot as $upper => $row) {
            if (! isset($row['qty']) || $row['qty'] === null) {
                continue;
            }
            $byUpper[$upper] = (int) $row['qty'];
            $metaByUpper[$upper] = ['sku_id' => $row['sku_id'], 'goods_id' => $row['goods_id'], 'status' => $row['status']];
        }
        $out = $this->matchRequested($skus, $byUpper);
        foreach ($out as $sku => $qty) {
            $upper = strtoupper($sku);
            $meta = $metaByUpper[$upper] ?? null;
            if ($meta === null) {
                $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
                foreach ($metaByUpper as $candidateUpper => $candidate) {
                    if ($norm !== '' && ShopifySku::normalizeSkuForShopifyLookup((string) $candidateUpper) === $norm) {
                        $meta = $candidate;
                        break;
                    }
                }
            }
            if ($meta !== null) {
                $this->meta[$sku] = $meta;
            }
        }

        return $out;
    }

    /**
     * AliExpress product info per product id (all SKUs of a product in one call).
     *
     * @param  list<string>  $skus
     * @return array<string, int>
     */
    private function aliexpress(array $skus): array
    {
        $api = app(AliExpressApiService::class);
        if (empty($api->getAccessToken()) || ! Schema::hasTable('aliexpress_metric')) {
            return [];
        }
        $upperToRequested = [];
        foreach ($skus as $sku) {
            $upperToRequested[strtoupper($sku)] = $sku;
        }
        $skusByProduct = [];
        AliexpressMetric::query()
            ->whereNotNull('product_id')
            ->where('product_id', '!=', '')
            ->where(function ($q) use ($skus) {
                $q->whereIn('sku', $skus)->orWhereIn('sku', array_map('strtoupper', $skus));
            })
            ->get(['sku', 'product_id'])
            ->each(function ($metric) use (&$skusByProduct, $upperToRequested) {
                $sku = trim((string) $metric->sku);
                $productId = trim((string) $metric->product_id);
                if ($productId === '' || ! MarketplaceLiveInventoryRules::isLinked($productId, $sku)) {
                    return;
                }
                $skusByProduct[$productId][] = $upperToRequested[strtoupper($sku)] ?? $sku;
            });
        if ($skusByProduct === []) {
            return [];
        }

        $out = [];
        $calls = 0;
        foreach ($skusByProduct as $productId => $members) {
            if ($calls >= self::PER_SKU_CAP) {
                break;
            }
            $calls++;
            $info = $api->getProductInfo((string) $productId);
            if (empty($info['success']) || ! is_array($info['data'] ?? null)) {
                continue;
            }
            $byUpper = [];
            foreach ($api->extractSkuRowsFromProductInfo($info['data'], (string) $productId) as $row) {
                $aeSku = trim((string) ($row['sku'] ?? ''));
                if ($aeSku === '' || ! isset($row['stock']) || $row['stock'] === null) {
                    continue;
                }
                $byUpper[strtoupper($aeSku)] = max(0, (int) $row['stock']);
            }
            foreach ($this->matchRequested(array_values(array_unique($members)), $byUpper) as $sku => $qty) {
                $out[$sku] = $qty;
            }
            usleep(100000);
        }

        return $out;
    }

    /**
     * PLS Shopify store qty: per-variant reads for a short list, otherwise one paged catalog pull.
     *
     * @param  list<string>  $skus
     * @return array<string, int>
     */
    private function pls(array $skus): array
    {
        $service = app(PlsInventorySyncService::class);
        if (count($skus) <= self::PLS_PER_VARIANT_MAX) {
            return $service->readLiveQtyBySkus($skus);
        }

        $byNorm = app(ShopifyCatalogSyncService::class)->pullInventoryByNormalizedSku('pls');
        if ($byNorm === []) {
            return [];
        }
        $out = [];
        foreach ($skus as $sku) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '' && array_key_exists($norm, $byNorm) && is_numeric($byNorm[$norm])) {
                $out[$sku] = max(0, (int) $byNorm[$norm]);
            }
        }

        return $out;
    }

    /**
     * Business 5 Core B2B /api/inventory (one paged pull).
     *
     * @param  list<string>  $skus
     * @return array<string, int>
     */
    private function b5cb2b(array $skus): array
    {
        $api = app(Business5CoreB2bApiService::class);
        if (! $api->isConfigured()) {
            return [];
        }
        $byUpper = [];
        foreach ($api->fetchAllInventoryRows() as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku !== '' && isset($row['qty']) && is_numeric($row['qty'])) {
                $byUpper[strtoupper($sku)] = max(0, (int) $row['qty']);
            }
            foreach (is_array($row['variants'] ?? null) ? $row['variants'] : [] as $variant) {
                if (! is_array($variant)) {
                    continue;
                }
                $vSku = trim((string) ($variant['sku'] ?? ''));
                $vQty = $variant['qty'] ?? $row['qty'] ?? null;
                if ($vSku !== '' && is_numeric($vQty)) {
                    $byUpper[strtoupper($vSku)] = max(0, (int) $vQty);
                }
            }
        }

        return $this->matchRequested($skus, $byUpper);
    }

    /**
     * @param  list<string>  $skus
     * @param  array<string, int>  $byUpper
     * @return array<string, int>
     */
    private function matchRequested(array $skus, array $byUpper): array
    {
        $byNorm = [];
        foreach ($byUpper as $upper => $qty) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $upper);
            if ($norm !== '' && ! isset($byNorm[$norm])) {
                $byNorm[$norm] = $qty;
            }
        }

        $out = [];
        foreach ($skus as $sku) {
            $upper = strtoupper($sku);
            if (array_key_exists($upper, $byUpper)) {
                $out[$sku] = $byUpper[$upper];
                continue;
            }
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '' && array_key_exists($norm, $byNorm)) {
                $out[$sku] = $byNorm[$norm];
            }
        }

        return $out;
    }
}
