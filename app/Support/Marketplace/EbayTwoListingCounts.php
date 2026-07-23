<?php

namespace App\Support\Marketplace;

use App\Models\Ebay2Metric;
use App\Models\EbayTwoDataView;
use App\Models\ProductMaster;
use App\Models\ShopifySku;

/**
 * EbayTwo listing status counts — same source as /listing-ebaytwo.
 *
 * Rules (per ProductMaster SKU, deleted_at null):
 * - skip PARENT SKUs
 * - skip INV <= 0 (Shopify)
 * - nr_req from EbayTwoDataView.value.NRL (NRL/NR → NR, else REQ)
 * - listed from ebay_2_metrics.item_id (non-empty = Listed)
 * - Missing L (Pending) = REQ and no item_id
 */
class EbayTwoListingCounts
{
    /**
     * @return array{REQ: int, NRL: int, Listed: int, Pending: int, MissingL: int}
     */
    public static function counts(): array
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->filter()->values()->all();

        $shopifyData = ShopifySku::mapByProductSkus($skus);

        $nrValues = EbayTwoDataView::whereIn('sku', $skus)
            ->get(['sku', 'value'])
            ->mapWithKeys(function ($row) {
                return [strtoupper(trim((string) $row->sku)) => $row->value];
            });

        $ebayMetrics = Ebay2Metric::whereIn('sku', $skus)
            ->get(['sku', 'item_id'])
            ->mapWithKeys(function ($row) {
                return [strtolower(trim((string) $row->sku)) => $row];
            });

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

            $ebayMetric = $ebayMetrics->get(strtolower($sku));
            $itemId = $ebayMetric ? trim((string) ($ebayMetric->item_id ?? '')) : '';
            if ($itemId !== '') {
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
     * Normalize EbayTwoDataView NRL value to REQ|NR (same as /listing-ebaytwo).
     */
    public static function nrReqFromDataView(mixed $raw): string
    {
        if (!is_array($raw)) {
            $raw = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        }
        $nrlRaw = strtoupper(trim((string) ($raw['NRL'] ?? '')));

        return in_array($nrlRaw, ['NRL', 'NR'], true) ? 'NR' : 'REQ';
    }
}
