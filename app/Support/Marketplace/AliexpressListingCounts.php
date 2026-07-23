<?php

namespace App\Support\Marketplace;

use App\Models\AliexpressDataView;
use App\Models\AliexpressMetric;
use App\Models\ProductMaster;
use App\Models\ShopifySku;

/**
 * Aliexpress listing status counts — same pattern as /listing-ebaytwo.
 *
 * Rules (per ProductMaster SKU, deleted_at null):
 * - skip PARENT SKUs
 * - skip INV <= 0 (Shopify)
 * - nr_req from AliexpressDataView.value.NRL (NRL/NR → NR, else REQ)
 * - listed from aliexpress_metric.product_id (non-empty real id = Listed)
 * - Missing L (Pending) = REQ and no product_id
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

        $metrics = AliexpressMetric::whereIn('sku', $skus)
            ->get(['sku', 'product_id'])
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

            $metric = $metrics->get(strtolower($sku));
            $productId = self::normalizeProductId($metric?->product_id, $sku);
            if ($productId !== '') {
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
}
