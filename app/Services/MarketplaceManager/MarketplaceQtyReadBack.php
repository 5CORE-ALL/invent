<?php

namespace App\Services\MarketplaceManager;

use App\Models\ReverbMetric;
use App\Models\SheinListingStatus;
use App\Models\ShopifySku;
use App\Services\AmazonSpApiService;
use App\Services\FaireApiService;
use App\Services\NeweggApiService;
use App\Services\SheinApiService;
use App\Services\TopDawgApiService;
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
    public const SUPPORTED = ['amazon', 'faire', 'topdawg', 'shein', 'newegg', 'reverb'];

    /** Per-SKU calls (Amazon, TopDawg) are capped so one channel cannot eat the run budget. */
    private const PER_SKU_CAP = 80;

    public static function supports(string $channel): bool
    {
        return in_array(strtolower(trim($channel)), self::SUPPORTED, true);
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

        try {
            return match ($channel) {
                'amazon' => $this->amazon($skus),
                'faire' => $this->faire($skus),
                'topdawg' => $this->topdawg($skus),
                'shein' => $this->shein($skus),
                'newegg' => $this->newegg($skus),
                'reverb' => $this->reverb($skus),
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
