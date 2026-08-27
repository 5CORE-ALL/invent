<?php

namespace App\Support\Marketplace;

use App\Models\AliexpressDataView;
use App\Models\AliexpressMetric;
use App\Models\AliexpressPricingPrice;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Schema;

/**
 * Aliexpress listing status counts — same pattern as /listing-ebaytwo.
 *
 * Rules (per ProductMaster SKU, deleted_at null):
 * - skip PARENT SKUs
 * - skip INV <= 0 (shopify_skus.inv — Product Master, not live catalog)
 * - nr_req from AliexpressDataView.value.NRL (NRL/NR → NR, else REQ)
 * - listed if onSelling aliexpress_metric.product_id OR sku in aliexpress_pricing_prices
 *   with price > 0 (normalized SKU: spaces / hyphens / case). Offline / deleted IDs do not count.
 * - Missing L (Pending) = REQ and not listed
 */
class AliexpressListingCounts
{
    /**
     * @return array{REQ: int, NRL: int, Listed: int, Pending: int, MissingL: int}
     */
    public static function counts(): array
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->filter()->values()->all();

        $shopifyData = ShopifySku::mapByProductSkus($skus);

        $nrValues = AliexpressDataView::whereIn('sku', $skus)
            ->get(['sku', 'value'])
            ->mapWithKeys(function ($row) {
                return [strtoupper(trim((string) $row->sku)) => $row->value];
            });

        $metricsByNorm = self::metricsByNormalizedSku();
        $pricingByNorm = self::pricingSkusByNormalizedSku();

        $reqCount = 0;
        $nrlCount = 0;
        $listedCount = 0;
        $missingL = 0;

        foreach ($productMasters as $item) {
            $sku = trim((string) $item->sku);
            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                continue;
            }

            $inv = (float) ($shopifyData[$sku]->inv ?? 0);
            if ($inv <= 0) {
                continue;
            }

            $nrReq = self::nrReqFromDataView($nrValues->get(strtoupper($sku)));
            if ($nrReq === 'REQ') {
                $reqCount++;
            } else {
                $nrlCount++;
            }

            $resolved = self::resolveListed($sku, $metricsByNorm, $pricingByNorm);
            if ($resolved['listed']) {
                $listedCount++;
            } elseif ($nrReq === 'REQ') {
                $missingL++;
            }
        }

        return [
            'REQ' => $reqCount,
            'NRL' => $nrlCount,
            'Listed' => $listedCount,
            'Pending' => $missingL,
            'MissingL' => $missingL,
        ];
    }

    public static function missingL(): int
    {
        return self::counts()['MissingL'];
    }

    /**
     * Real AE product id + listed flag (metric id or pricing-table SKU).
     *
     * @param  array<string, string>  $metricsByNorm
     * @param  array<string, string>  $pricingByNorm
     * @return array{product_id: string, listed: bool}
     */
    public static function resolveListed(string $sku, array $metricsByNorm, array $pricingByNorm): array
    {
        $productId = self::productIdForSku($sku, $metricsByNorm);
        $listed = $productId !== '' || self::inPricing($sku, $pricingByNorm);

        return [
            'product_id' => $productId,
            'listed' => $listed,
        ];
    }

    /**
     * @return array<string, string> normalized SKU => real product_id
     */
    public static function metricsByNormalizedSku(): array
    {
        $byNorm = [];
        if (! Schema::hasTable('aliexpress_metric')) {
            return $byNorm;
        }

        $hasListingStatus = Schema::hasColumn('aliexpress_metric', 'listing_status');
        $cols = ['id', 'sku', 'product_id'];
        if ($hasListingStatus) {
            $cols[] = 'listing_status';
        }

        AliexpressMetric::query()
            ->select($cols)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$byNorm, $hasListingStatus) {
                foreach ($rows as $row) {
                    $status = $hasListingStatus
                        ? strtolower(trim((string) ($row->listing_status ?? '')))
                        : '';
                    if (in_array($status, ['offline', 'service_delete'], true)) {
                        continue;
                    }
                    $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku);
                    $id = self::normalizeProductId($row->product_id, (string) $row->sku);
                    if ($norm !== '' && $id !== '' && ! isset($byNorm[$norm])) {
                        $byNorm[$norm] = $id;
                    }
                    if ($status === 'onselling' && $norm !== '' && $id !== '') {
                        $byNorm[$norm] = $id;
                    }
                    $compact = self::compactSku((string) $row->sku);
                    if ($compact !== '' && $id !== '' && ! isset($byNorm['c:'.$compact])) {
                        $byNorm['c:'.$compact] = $id;
                    }
                }
            });

        self::mergeLiveListingsIntoMetrics($byNorm);

        return $byNorm;
    }

    /**
     * @return array<string, string> normalized SKU => pricing-table SKU
     */
    public static function pricingSkusByNormalizedSku(): array
    {
        $byNorm = [];
        if (! Schema::hasTable('aliexpress_pricing_prices')) {
            return $byNorm;
        }

        AliexpressPricingPrice::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where('price', '>', 0)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$byNorm) {
                foreach ($rows as $row) {
                    $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku);
                    if ($norm !== '' && ! isset($byNorm[$norm])) {
                        $byNorm[$norm] = trim((string) $row->sku);
                    }
                    $compact = self::compactSku((string) $row->sku);
                    if ($compact !== '' && ! isset($byNorm['c:'.$compact])) {
                        $byNorm['c:'.$compact] = trim((string) $row->sku);
                    }
                }
            });

        return $byNorm;
    }

    /**
     * @param  array<string, string>  $metricsByNorm
     */
    public static function productIdForSku(string $sku, array $metricsByNorm): string
    {
        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($norm !== '' && isset($metricsByNorm[$norm])) {
            return $metricsByNorm[$norm];
        }
        $compact = self::compactSku($sku);
        if ($compact !== '' && isset($metricsByNorm['c:'.$compact])) {
            return $metricsByNorm['c:'.$compact];
        }

        return '';
    }

    /**
     * @param  array<string, string>  $pricingByNorm
     */
    public static function inPricing(string $sku, array $pricingByNorm): bool
    {
        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($norm !== '' && isset($pricingByNorm[$norm])) {
            return true;
        }
        $compact = self::compactSku($sku);

        return $compact !== '' && isset($pricingByNorm['c:'.$compact]);
    }

    /**
     * Normalize AliexpressDataView NRL value to REQ|NR (same as /listing-ebaytwo).
     */
    public static function nrReqFromDataView(mixed $raw): string
    {
        if (! is_array($raw)) {
            $raw = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        }
        $nrlRaw = strtoupper(trim((string) ($raw['NRL'] ?? '')));
        if (in_array($nrlRaw, ['NRL', 'NR'], true)) {
            return 'NR';
        }
        if ($nrlRaw === 'REQ' || $nrlRaw === 'RL') {
            return 'REQ';
        }

        // Fallback NR field (same idea as AliexpressController::resolveAeNrFromMeta)
        $nr = strtoupper(trim((string) ($raw['NR'] ?? $raw['NRP'] ?? '')));
        if (in_array($nr, ['NR', 'NRL'], true)) {
            return 'NR';
        }

        return 'REQ';
    }

    /**
     * Real AE product id (ignore empty / sku-as-placeholder rows).
     */
    public static function normalizeProductId(mixed $productId, string $sku = ''): string
    {
        $id = trim((string) ($productId ?? ''));
        if ($id === '') {
            return '';
        }
        if ($sku !== '' && strcasecmp($id, trim($sku)) === 0) {
            return '';
        }

        return $id;
    }

    /**
     * Collapse spaces / punctuation so "MS 080 RED 2 PCS" matches "MS 080 RED 2PCS".
     */
    public static function compactSku(string $sku): string
    {
        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($norm === '') {
            return '';
        }

        return strtoupper((string) preg_replace('/[^A-Z0-9]+/i', '', $norm));
    }

    /**
     * Overlay cached live catalog SKUs (onSelling / auditing) onto the metric map.
     *
     * @param  array<string, string>  $byNorm
     */
    private static function mergeLiveListingsIntoMetrics(array &$byNorm): void
    {
        try {
            $cached = app(\App\Services\MarketplaceManager\AliexpressLiveListingsService::class)->peekCached();
        } catch (\Throwable) {
            return;
        }
        if (! is_array($cached) || $cached === []) {
            return;
        }
        foreach ($cached as $row) {
            if (! is_array($row)) {
                continue;
            }
            $state = strtolower(trim((string) ($row['state'] ?? '')));
            if (in_array($state, ['offline', 'service_delete', 'deleted'], true)) {
                continue;
            }
            $sku = trim((string) ($row['sku'] ?? ''));
            $id = self::normalizeProductId($row['product_id'] ?? '', $sku);
            if ($sku === '' || $id === '') {
                continue;
            }
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '' && ! isset($byNorm[$norm])) {
                $byNorm[$norm] = $id;
            }
            $compact = self::compactSku($sku);
            if ($compact !== '' && ! isset($byNorm['c:'.$compact])) {
                $byNorm['c:'.$compact] = $id;
            }
        }
    }
}
