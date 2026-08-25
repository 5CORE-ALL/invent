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
        if (is_string($raw)) {
            $trimmed = strtoupper(trim($raw));
            if (in_array($trimmed, ['NRL', 'NR'], true)) {
                return 'NR';
            }
            $raw = json_decode($raw, true) ?: [];
        } elseif (! is_array($raw)) {
            $raw = [];
        }
        $nrlRaw = strtoupper(trim((string) ($raw['NRL'] ?? $raw['NR'] ?? '')));

        return in_array($nrlRaw, ['NRL', 'NR'], true) ? 'NR' : 'REQ';
    }

    public static function isNrl(mixed $raw): bool
    {
        return self::nrReqFromDataView($raw) === 'NR';
    }

    /**
     * Lookup keys used by the Amazon blade for NRL/NRA (exact, collapsed spaces, no spaces, datasheet fold).
     *
     * @return list<string>
     */
    public static function skuLookupKeys(string $sku): array
    {
        $sku = trim(str_replace("\xC2\xA0", ' ', $sku));
        if ($sku === '') {
            return [];
        }

        $collapsed = strtoupper(preg_replace('/\s+/u', ' ', $sku) ?? $sku);
        $noSpace = strtoupper(str_replace(' ', '', $sku));
        $base = trim((string) preg_replace('/\s+(FBA|FBM)$/i', '', $sku));

        $keys = [
            strtoupper($sku),
            $collapsed,
            $noSpace,
        ];
        if ($base !== '' && strcasecmp($base, $sku) !== 0) {
            $keys = array_merge($keys, self::skuLookupKeys($base));
        }

        $norm = AmazonDatasheet::normalizeSkuForLookup($sku);
        if ($norm !== '') {
            $keys[] = $norm;
        }

        return array_values(array_unique(array_filter($keys)));
    }

    /**
     * Uppercased SKU keys marked NRL/NR in amazon_data_view (same source as the Amazon blade).
     * Matches exact SKU and space/FBA variants so refresh does not count those SKUs as Missing.
     *
     * @param  list<string>  $skus
     * @return array<string, true>
     */
    public static function nrlSetForSkus(array $skus): array
    {
        $set = [];
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ))));
        if ($skus === [] || ! Schema::hasTable('amazon_data_view')) {
            return $set;
        }

        $wanted = [];
        foreach ($skus as $sku) {
            foreach (self::skuLookupKeys($sku) as $key) {
                $wanted[$key] = true;
            }
        }

        // Same as /amazon-tabulator-view: load data-view rows, then match by normalized SKU.
        // Exact whereIn misses NRL when Product Master and amazon_data_view spacing differ.
        AmazonDataView::query()
            ->get(['sku', 'value'])
            ->each(function ($row) use (&$set, $wanted) {
                if (! self::isNrl($row->value ?? null)) {
                    return;
                }
                $sku = trim((string) ($row->sku ?? ''));
                if ($sku === '') {
                    return;
                }
                $keys = self::skuLookupKeys($sku);
                $hit = false;
                foreach ($keys as $key) {
                    if (isset($wanted[$key])) {
                        $hit = true;
                        break;
                    }
                }
                if (! $hit) {
                    return;
                }
                foreach ($keys as $key) {
                    $set[$key] = true;
                }
            });

        return $set;
    }

    /**
     * @param  array<string, true>  $nrlSet
     */
    public static function skuIsNrl(string $sku, array $nrlSet): bool
    {
        if ($nrlSet === []) {
            return false;
        }
        foreach (self::skuLookupKeys($sku) as $key) {
            if (isset($nrlSet[$key])) {
                return true;
            }
        }

        return false;
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
