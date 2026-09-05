<?php

namespace App\Support\Marketplace;

use App\Models\Ebay2Metric;
use App\Models\EbayTwoDataView;
use App\Models\ProductMaster;

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

        $shopifyData = ListingCountsEngine::shopifyMap($skus);
        $nrValues = ListingCountsEngine::loadNrValues(EbayTwoDataView::class, $skus);

        $listedIds = ListingCountsEngine::listedIdsFromColumn(Ebay2Metric::class, $skus, 'item_id');

        $reqCount = 0;
        $nrlCount = 0;
        $listedCount = 0;
        $missingL = 0;

        foreach ($productMasters as $item) {
            $sku = trim((string) $item->sku);
            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                continue;
            }

            $inv = ListingCountsEngine::shopifyInv(ListingCountsEngine::shopifyRow($shopifyData, $sku, (string) $item->sku));
            if ($inv <= 0) {
                continue;
            }

            $nrReq = self::nrReqFromDataView(ListingCountsEngine::lookupNrValue($nrValues, $sku));
            if ($nrReq === 'REQ') {
                $reqCount++;
            } else {
                $nrlCount++;
            }

            $itemId = ListingCountsEngine::listingIdFromMap($listedIds, $sku);
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
