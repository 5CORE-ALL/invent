<?php

namespace App\Http\Controllers;

use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use App\Models\EbayThreeDataView;
use App\Models\EbayTwoDataView;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;

class MapIssuesController extends Controller
{
    /**
     * Display the Map Issues Tabulator page.
     */
    public function index()
    {
        return view('map-issues');
    }

    /**
     * Fetch all SKUs from Product Master along with Shopify + eBay inventory,
     * flagging SKUs whose stored value on a site does not exactly match the
     * Product Master SKU (case / hidden-space / whitespace differences).
     */
    public function data(Request $request)
    {
        // 1. Base ProductMaster fetch (exclude PARENT rows)
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get()
            ->filter(function ($item) {
                return stripos($item->sku, 'PARENT') === false;
            })
            ->values();

        // 2. SKU list
        $skus = $productMasters->pluck('sku')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($skus)) {
            return response()->json([
                'data' => [],
                'not_map_count' => 0,
                'mismatch_count' => 0,
                'ebay2_not_map_count' => 0,
                'ebay2_mismatch_count' => 0,
                'ebay3_not_map_count' => 0,
                'ebay3_mismatch_count' => 0,
                'missing_listing_count' => 0,
                'ebay2_missing_listing_count' => 0,
                'ebay3_missing_listing_count' => 0,
            ]);
        }

        // 3. Normalized lookups (NBSP / case / whitespace safe) keyed by normalized SKU
        $shopifyByNorm = ShopifySku::buildShopifySkuLookupByNormalizedSku($skus);
        $ebayByNorm    = $this->buildMetricLookupByNormalizedSku(EbayMetric::class, $skus);
        $ebay2ByNorm   = $this->buildMetricLookupByNormalizedSku(Ebay2Metric::class, $skus);
        $ebay3ByNorm   = $this->buildMetricLookupByNormalizedSku(Ebay3Metric::class, $skus);

        // NR/REQ values per marketplace — used for the Missing Listing condition.
        $nrValues  = EbayDataView::whereIn('sku', $skus)->pluck('value', 'sku');
        $nrValues2 = EbayTwoDataView::whereIn('sku', $skus)->pluck('value', 'sku');
        $nrValues3 = EbayThreeDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        // 4. Build rows
        $result = [];
        $notMapCount = 0;          // eBay:  INV != eBay Stock (same logic as eBay page)
        $mismatchCount = 0;        // eBay:  SKU stored differently
        $ebay2NotMapCount = 0;     // eBay2: INV != eBay2 Stock
        $ebay2MismatchCount = 0;   // eBay2: SKU stored differently
        $ebay3NotMapCount = 0;     // eBay3: INV != eBay3 Stock
        $ebay3MismatchCount = 0;   // eBay3: SKU stored differently
        $missingListingCount = 0;       // eBay1: not listed, REQ, INV > 0
        $ebay2MissingListingCount = 0;  // eBay2: not listed, REQ, INV > 0
        $ebay3MissingListingCount = 0;  // eBay3: not listed, REQ, INV > 0
        foreach ($productMasters as $pm) {
            $key = ShopifySku::normalizeSkuForShopifyLookup($pm->sku);

            $shopify = $key !== '' ? ($shopifyByNorm[$key] ?? null) : null;
            $ebay    = $key !== '' ? ($ebayByNorm[$key] ?? null) : null;
            $ebay2   = $key !== '' ? ($ebay2ByNorm[$key] ?? null) : null;
            $ebay3   = $key !== '' ? ($ebay3ByNorm[$key] ?? null) : null;

            $inv = floatval($shopify->inv ?? 0);

            // ---- eBay ----
            $ebayStock = floatval($ebay->ebay_stock ?? 0);
            $ebayItemId = $ebay->item_id ?? '';
            $ebaySku = $ebay->sku ?? null;
            $ebayReason = $ebaySku !== null ? $this->skuDifferenceReason($pm->sku, $ebaySku) : '';
            $ebayHasIssue = $ebayReason !== '';
            if ($ebayHasIssue) {
                $mismatchCount++;
            }
            $ebayIsNotMap = false;
            if ($ebayItemId && $ebayItemId !== null && $ebayItemId !== '') {
                if ($inv > 0 && ($ebayStock === 0.0 || ($ebayStock > 0 && $inv !== $ebayStock))) {
                    $ebayIsNotMap = true;
                    $notMapCount++;
                }
            }

            // Missing Listing: not listed on the marketplace, INV > 0 (NRL rows are kept, just labelled).
            $ebayNrReq = $this->isReq($nrValues, $pm->sku) ? 'REQ' : 'NRL';
            $ebayNotListed = ! ($ebayItemId && $ebayItemId !== null && $ebayItemId !== '');
            $ebayMissingListing = $ebayNotListed && $inv > 0;
            if ($ebayMissingListing && $ebayNrReq === 'REQ') {
                $missingListingCount++;
            }

            // ---- eBay 2 ----
            $ebay2Stock = floatval($ebay2->ebay_stock ?? 0);
            $ebay2ItemId = $ebay2->item_id ?? '';
            $ebay2Sku = $ebay2->sku ?? null;
            $ebay2Reason = $ebay2Sku !== null ? $this->skuDifferenceReason($pm->sku, $ebay2Sku) : '';
            $ebay2HasIssue = $ebay2Reason !== '';
            if ($ebay2HasIssue) {
                $ebay2MismatchCount++;
            }
            $ebay2IsNotMap = false;
            if ($ebay2ItemId && $ebay2ItemId !== null && $ebay2ItemId !== '') {
                if ($inv > 0 && ($ebay2Stock === 0.0 || ($ebay2Stock > 0 && $inv !== $ebay2Stock))) {
                    $ebay2IsNotMap = true;
                    $ebay2NotMapCount++;
                }
            }
            $ebay2NrReq = $this->isReq($nrValues2, $pm->sku) ? 'REQ' : 'NRL';
            $ebay2NotListed = ! ($ebay2ItemId && $ebay2ItemId !== null && $ebay2ItemId !== '');
            $ebay2MissingListing = $ebay2NotListed && $inv > 0;
            if ($ebay2MissingListing && $ebay2NrReq === 'REQ') {
                $ebay2MissingListingCount++;
            }

            // ---- eBay 3 ----
            $ebay3Stock = floatval($ebay3->ebay_stock ?? 0);
            $ebay3ItemId = $ebay3->item_id ?? '';
            $ebay3Sku = $ebay3->sku ?? null;
            $ebay3Reason = $ebay3Sku !== null ? $this->skuDifferenceReason($pm->sku, $ebay3Sku) : '';
            $ebay3HasIssue = $ebay3Reason !== '';
            if ($ebay3HasIssue) {
                $ebay3MismatchCount++;
            }
            $ebay3IsNotMap = false;
            if ($ebay3ItemId && $ebay3ItemId !== null && $ebay3ItemId !== '') {
                if ($inv > 0 && ($ebay3Stock === 0.0 || ($ebay3Stock > 0 && $inv !== $ebay3Stock))) {
                    $ebay3IsNotMap = true;
                    $ebay3NotMapCount++;
                }
            }
            $ebay3NrReq = $this->isReq($nrValues3, $pm->sku) ? 'REQ' : 'NRL';
            $ebay3NotListed = ! ($ebay3ItemId && $ebay3ItemId !== null && $ebay3ItemId !== '');
            $ebay3MissingListing = $ebay3NotListed && $inv > 0;
            if ($ebay3MissingListing && $ebay3NrReq === 'REQ') {
                $ebay3MissingListingCount++;
            }

            $result[] = [
                '(Child) sku'    => $pm->sku,
                'INV'            => $shopify->inv ?? 0,

                'Ebay Inv'        => $ebay->ebay_stock ?? 0,
                'ebay_sku'        => $ebaySku,
                'ebay_mismatch'   => $ebayHasIssue,
                'issue'           => $ebayReason,
                'has_issue'       => $ebayHasIssue,
                'is_not_map'      => $ebayIsNotMap,
                'missing_listing' => $ebayMissingListing,
                'ebay_nr_req'     => $ebayNrReq,

                'Ebay2 Inv'             => $ebay2->ebay_stock ?? 0,
                'ebay2_sku'             => $ebay2Sku,
                'ebay2_mismatch'        => $ebay2HasIssue,
                'ebay2_issue'           => $ebay2Reason,
                'ebay2_not_map'         => $ebay2IsNotMap,
                'ebay2_missing_listing' => $ebay2MissingListing,
                'ebay2_nr_req'          => $ebay2NrReq,

                'Ebay3 Inv'             => $ebay3->ebay_stock ?? 0,
                'ebay3_sku'             => $ebay3Sku,
                'ebay3_mismatch'        => $ebay3HasIssue,
                'ebay3_issue'           => $ebay3Reason,
                'ebay3_not_map'         => $ebay3IsNotMap,
                'ebay3_missing_listing' => $ebay3MissingListing,
                'ebay3_nr_req'          => $ebay3NrReq,
            ];
        }

        return response()->json([
            'data'                  => $result,
            'not_map_count'         => $notMapCount,
            'mismatch_count'        => $mismatchCount,
            'ebay2_not_map_count'   => $ebay2NotMapCount,
            'ebay2_mismatch_count'  => $ebay2MismatchCount,
            'ebay3_not_map_count'   => $ebay3NotMapCount,
            'ebay3_mismatch_count'  => $ebay3MismatchCount,
            'missing_listing_count' => $missingListingCount,
            'ebay2_missing_listing_count' => $ebay2MissingListingCount,
            'ebay3_missing_listing_count' => $ebay3MissingListingCount,
        ]);
    }

    /**
     * Update the NR/REQ status for a SKU on a given marketplace.
     */
    public function updateNrReq(Request $request)
    {
        $sku    = (string) $request->input('sku');
        $market = (string) $request->input('marketplace');
        $status = strtoupper((string) $request->input('status'));

        $modelMap = [
            'ebay'  => EbayDataView::class,
            'ebay2' => EbayTwoDataView::class,
            'ebay3' => EbayThreeDataView::class,
        ];

        if ($sku === '' || ! isset($modelMap[$market]) || ! in_array($status, ['REQ', 'NRL'], true)) {
            return response()->json(['success' => false, 'message' => 'Invalid request'], 422);
        }

        $modelClass = $modelMap[$market];
        $row = $modelClass::firstOrNew(['sku' => $sku]);

        $value = is_array($row->value) ? $row->value : (json_decode((string) $row->value, true) ?: []);
        $value['NRL'] = $status; // stored under the NRL key: 'REQ' or 'NRL'
        $row->value = $value;
        $row->save();

        return response()->json(['success' => true, 'sku' => $sku, 'marketplace' => $market, 'status' => $status]);
    }

    /**
     * Whether a SKU is marked REQ (i.e. not flagged NRL) in a marketplace data view.
     *
     * @param  \Illuminate\Support\Collection  $nrValues  value JSON keyed by SKU
     */
    private function isReq($nrValues, string $sku): bool
    {
        if (! isset($nrValues[$sku])) {
            return true; // default REQ
        }
        $raw = $nrValues[$sku];
        if (! is_array($raw)) {
            $raw = json_decode($raw, true) ?? [];
        }

        return ! (is_array($raw) && ($raw['NRL'] ?? null) === 'NRL');
    }

    /**
     * Build a normalized-SKU => metric-row lookup, falling back to a full scan
     * for SKUs whose value differs only by formatting (NBSP / case / spaces).
     * Works for any metric model that has sku / ebay_stock / item_id columns.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  array<int, string>  $productSkus
     * @return array<string, \Illuminate\Database\Eloquent\Model>
     */
    private function buildMetricLookupByNormalizedSku(string $modelClass, array $productSkus): array
    {
        $byNorm = [];

        foreach ($modelClass::select('sku', 'ebay_stock', 'item_id')->whereIn('sku', $productSkus)->get() as $row) {
            $k = ShopifySku::normalizeSkuForShopifyLookup($row->sku);
            if ($k !== '' && ! isset($byNorm[$k])) {
                $byNorm[$k] = $row;
            }
        }

        $missing = [];
        foreach ($productSkus as $pmSku) {
            $k = ShopifySku::normalizeSkuForShopifyLookup((string) $pmSku);
            if ($k !== '' && ! isset($byNorm[$k])) {
                $missing[$k] = true;
            }
        }

        if ($missing === []) {
            return $byNorm;
        }

        $modelClass::query()
            ->select('sku', 'ebay_stock', 'item_id', 'id')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(3000, function ($rows) use (&$byNorm, &$missing) {
                foreach ($rows as $row) {
                    $k = ShopifySku::normalizeSkuForShopifyLookup($row->sku);
                    if ($k !== '' && isset($missing[$k]) && ! isset($byNorm[$k])) {
                        $byNorm[$k] = $row;
                        unset($missing[$k]);
                    }
                }

                return count($missing) > 0;
            });

        return $byNorm;
    }

    /**
     * Explain why an actual stored SKU differs from the expected Product Master SKU.
     * Returns '' when they match exactly.
     */
    private function skuDifferenceReason(string $expected, string $actual): string
    {
        if ($expected === $actual) {
            return '';
        }

        // If they don't even match after normalization, it's a genuinely different SKU.
        if (ShopifySku::normalizeSkuForShopifyLookup($expected) !== ShopifySku::normalizeSkuForShopifyLookup($actual)) {
            return 'Different SKU text';
        }

        $reasons = [];

        $hiddenSpaces = [
            "\xC2\xA0"     => 'non-breaking space',
            "\xE2\x80\xAF" => 'narrow no-break space',
            "\xE2\x80\x87" => 'figure space',
            "\xE2\x80\x8B" => 'zero-width space',
        ];
        foreach ($hiddenSpaces as $bytes => $label) {
            if (strpos($expected, $bytes) !== false || strpos($actual, $bytes) !== false) {
                $reasons[] = 'hidden ' . $label;
            }
        }

        if (trim($expected) !== $expected || trim($actual) !== $actual) {
            $reasons[] = 'leading/trailing space';
        }

        // Compare with hidden spaces converted to normal spaces, but not yet collapsed/cased.
        $cleanExpected = preg_replace('/\s+/u', ' ', str_replace(array_keys($hiddenSpaces), ' ', $expected));
        $cleanActual   = preg_replace('/\s+/u', ' ', str_replace(array_keys($hiddenSpaces), ' ', $actual));

        if ($cleanExpected !== str_replace(array_keys($hiddenSpaces), ' ', $expected)
            || $cleanActual !== str_replace(array_keys($hiddenSpaces), ' ', $actual)) {
            $reasons[] = 'extra spaces';
        }

        if (trim($cleanExpected) !== trim($cleanActual)
            && strtoupper(trim($cleanExpected)) === strtoupper(trim($cleanActual))) {
            $reasons[] = 'case mismatch';
        }

        $reasons = array_values(array_unique($reasons));
        if (empty($reasons)) {
            $reasons[] = 'formatting mismatch';
        }

        return implode(', ', $reasons);
    }
}
