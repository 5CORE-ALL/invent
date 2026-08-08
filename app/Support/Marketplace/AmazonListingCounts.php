<?php

namespace App\Support\Marketplace;

use App\Models\AmazonDataView;
use App\Models\AmazonDatasheet;
use App\Models\AmazonListingRaw;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Schema;

/**
 * Amazon listing status counts — same pattern as /listing-ebaytwo.
 *
 * Rules (per ProductMaster SKU, deleted_at null):
 * - skip PARENT SKUs
 * - skip INV <= 0 (Shopify)
 * - nr_req from AmazonDataView.value.NRL (NRL → NR, else REQ)
 * - listed from amazon_listings_raw (SP-API listings report) — ASIN present
 * - Missing L (Pending) = REQ and not listed
 */
class AmazonListingCounts
{
    /**
     * @return array{REQ: int, NRL: int, Listed: int, Pending: int, MissingL: int}
     */
    public static function counts(): array
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->filter()->values()->all();

        $shopifyData = ShopifySku::mapByProductSkus($skus);

        $nrValues = AmazonDataView::whereIn('sku', $skus)
            ->get(['sku', 'value'])
            ->mapWithKeys(function ($row) {
                return [strtoupper(trim((string) $row->sku)) => $row->value];
            });

        $listingsByNorm = self::listingsByNormalizedSku();

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

            $listing = self::pickListingForProductSku($sku, $listingsByNorm);
            $isListed = self::isListedFromApi($listing);
            if ($isListed) {
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
     * Normalize AmazonDataView NRL value to REQ|NR (same as /listing-ebaytwo / amazon-tabulator).
     */
    public static function nrReqFromDataView(mixed $raw): string
    {
        if (! is_array($raw)) {
            $raw = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        }
        $nrlRaw = strtoupper(trim((string) ($raw['NRL'] ?? '')));

        return in_array($nrlRaw, ['NRL', 'NR'], true) ? 'NR' : 'REQ';
    }

    /**
     * @return array<string, array{asin: string, seller_sku: string}>
     */
    public static function listingsByNormalizedSku(): array
    {
        if (! Schema::hasTable('amazon_listings_raw')) {
            return [];
        }

        $map = [];
        AmazonListingRaw::query()
            ->whereNotNull('seller_sku')
            ->where('seller_sku', '!=', '')
            ->get(['seller_sku', 'asin1'])
            ->each(function (AmazonListingRaw $row) use (&$map) {
                $sellerSku = trim((string) $row->seller_sku);
                if ($sellerSku === '') {
                    return;
                }
                $asin = trim((string) ($row->asin1 ?? ''));
                if ($asin === '') {
                    return;
                }

                $base = trim((string) preg_replace('/\s+(FBA|FBM)$/i', '', $sellerSku));
                foreach (array_unique([$sellerSku, $base]) as $cand) {
                    $norm = AmazonDatasheet::normalizeSkuForLookup($cand);
                    if ($norm === '' || isset($map[$norm])) {
                        continue;
                    }
                    $map[$norm] = [
                        'asin' => $asin,
                        'seller_sku' => $sellerSku,
                    ];
                }
            });

        return $map;
    }

    /**
     * @param  array<string, array{asin: string, seller_sku: string}>|null  $listingsByNorm
     * @return array{asin: string, seller_sku: string}|null
     */
    public static function pickListingForProductSku(string $sku, ?array $listingsByNorm = null): ?array
    {
        $listingsByNorm ??= self::listingsByNormalizedSku();
        $norm = AmazonDatasheet::normalizeSkuForLookup($sku);
        if ($norm === '') {
            return null;
        }

        return $listingsByNorm[$norm] ?? null;
    }

    /**
     * @param  array{asin: string, seller_sku: string}|null  $listing
     */
    public static function isListedFromApi(?array $listing): bool
    {
        return $listing !== null && trim((string) ($listing['asin'] ?? '')) !== '';
    }

    /**
     * @param  array{asin: string, seller_sku: string}|null  $listing
     */
    public static function asinFromApi(?array $listing): string
    {
        return trim((string) ($listing['asin'] ?? ''));
    }

    /** @deprecated Prefer isListedFromApi — kept for callers still on datasheet. */
    public static function isListedFromDatasheet(?AmazonDatasheet $sheet): bool
    {
        if ($sheet === null) {
            return false;
        }

        return (float) ($sheet->price ?? 0) > 0;
    }

    /** @deprecated Prefer asinFromApi — kept for callers still on datasheet. */
    public static function asinFromDatasheet(?AmazonDatasheet $sheet): string
    {
        if ($sheet === null) {
            return '';
        }

        return trim((string) ($sheet->asin ?? ''));
    }
}
