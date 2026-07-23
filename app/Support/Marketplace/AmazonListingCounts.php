<?php

namespace App\Support\Marketplace;

use App\Models\AmazonDataView;
use App\Models\AmazonDatasheet;
use App\Models\ProductMaster;
use App\Models\ShopifySku;

/**
 * Amazon listing status counts — same pattern as /listing-ebaytwo.
 *
 * Rules (per ProductMaster SKU, deleted_at null):
 * - skip PARENT SKUs
 * - skip INV <= 0 (Shopify)
 * - nr_req from AmazonDataView.value.NRL (NRL → NR, else REQ)
 * - listed from amazon_datsheets (row exists + price > 0) — same Missing L inverse as Active Channel / amazon-tabulator
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

        $datasheetsByNorm = AmazonDatasheet::groupedByNormalizedSku();

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

            $sheet = AmazonDatasheet::pickBestForProductSku(
                $sku,
                $datasheetsByNorm->get(AmazonDatasheet::normalizeSkuForLookup($sku))
            );
            $isListed = self::isListedFromDatasheet($sheet);
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

    public static function isListedFromDatasheet(?AmazonDatasheet $sheet): bool
    {
        if ($sheet === null) {
            return false;
        }

        return (float) ($sheet->price ?? 0) > 0;
    }

    public static function asinFromDatasheet(?AmazonDatasheet $sheet): string
    {
        if ($sheet === null) {
            return '';
        }

        return trim((string) ($sheet->asin ?? ''));
    }
}
