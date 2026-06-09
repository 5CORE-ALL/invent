<?php

namespace App\Http\Controllers;

use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use App\Models\EbayThreeListingStatus;
use App\Models\EbayTwoListingStatus;
use App\Models\ProductMaster;
use App\Models\ProductStockMapping;
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
                'amazon_not_map_count' => 0,
                'amazon_mismatch_count' => 0,
                'amazon_missing_listing_count' => 0,
            ]);
        }

        // 3. Normalized lookups (NBSP / case / whitespace safe) keyed by normalized SKU
        $shopifyByNorm = ShopifySku::buildShopifySkuLookupByNormalizedSku($skus);
        $ebayByNorm    = $this->buildMetricLookupByNormalizedSku(EbayMetric::class, $skus);
        $ebay2ByNorm   = $this->buildMetricLookupByNormalizedSku(Ebay2Metric::class, $skus);
        $ebay3ByNorm   = $this->buildMetricLookupByNormalizedSku(Ebay3Metric::class, $skus);

        // NR/REQ values per marketplace — used for the Missing Listing condition.
        // eBay 1 uses EbayDataView's "NRL" key (REQ/NRL).
        // eBay 2 & 3 use their listing-status "nr_req" key (REQ/NR) — same source as their pages.
        $nrValues   = EbayDataView::whereIn('sku', $skus)->pluck('value', 'sku');
        $nrStatus2  = $this->buildNrReqStatusLookup(EbayTwoListingStatus::class, $skus);
        $nrStatus3  = $this->buildNrReqStatusLookup(EbayThreeListingStatus::class, $skus);

        // Amazon: stock comes from product_stock_mappings.inventory_amazon, the listing
        // (ASIN) / stored SKU come from amazon_datsheets, and REQ/NRL comes from
        // amazon_data_view's "NRL" key — same source the amazon-tabulator-view page uses.
        $amazonStockByNorm = $this->buildAmazonStockLookupByNormalizedSku($skus);
        $amazonSheetByNorm = $this->buildAmazonSheetLookupByNormalizedSku($skus);
        $nrValuesAmz       = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

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
        $amazonNotMapCount = 0;          // Amazon: INV != Amazon Stock
        $amazonMismatchCount = 0;        // Amazon: SKU stored differently
        $amazonMissingListingCount = 0;  // Amazon: not listed, REQ, INV > 0
        foreach ($productMasters as $pm) {
            $key = ShopifySku::normalizeSkuForShopifyLookup($pm->sku);

            $shopify = $key !== '' ? ($shopifyByNorm[$key] ?? null) : null;
            $ebay    = $key !== '' ? ($ebayByNorm[$key] ?? null) : null;
            $ebay2   = $key !== '' ? ($ebay2ByNorm[$key] ?? null) : null;
            $ebay3   = $key !== '' ? ($ebay3ByNorm[$key] ?? null) : null;
            $amazonSheet = $key !== '' ? ($amazonSheetByNorm[$key] ?? null) : null;

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
            $ebayNrReq = $this->isReq($nrValues, $pm->sku) ? 'REQ' : 'NRL';
            // N Map within a 3% tolerance, both sides with stock, REQ only (NL / Not Req not counted).
            $ebayIsNotMap = false;
            $ebayWithin3 = false;
            if ($ebayItemId && $ebayItemId !== null && $ebayItemId !== '' && $ebayNrReq === 'REQ' && $inv > 0 && $ebayStock > 0) {
                $ebayDiffUnits = abs($inv - $ebayStock);
                // When 3% of INV is below 3 units, require an absolute gap > 3 units;
                // otherwise apply the (rounded) 3% rule.
                if ($inv * 0.03 < 3) {
                    $ebayIsNotMap = $ebayDiffUnits > 3;
                } else {
                    $ebayIsNotMap = round(($ebayDiffUnits / $inv) * 100) > 3;
                }
                if ($ebayIsNotMap) {
                    $notMapCount++;
                }
                $ebayWithin3 = ! $ebayIsNotMap;
            }

            // Missing Listing: not listed on the marketplace, INV > 0 (NRL rows are kept, just labelled).
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
            $ebay2NrReq = $this->isReqStatus($nrStatus2, $pm->sku) ? 'REQ' : 'NR';
            // eBay 2 only: allow a 3% tolerance — within 3% counts as mapped (e.g. 100 vs 97).
            // NL / 0 stock and Not Req rows are not counted as N Map.
            $ebay2IsNotMap = false;
            $ebay2Within3 = false;
            if ($ebay2ItemId && $ebay2ItemId !== null && $ebay2ItemId !== '' && $ebay2NrReq === 'REQ' && $inv > 0 && $ebay2Stock > 0) {
                $ebay2DiffUnits = abs($inv - $ebay2Stock);
                if ($inv * 0.03 < 3) {
                    $ebay2IsNotMap = $ebay2DiffUnits > 3;
                } else {
                    $ebay2IsNotMap = round(($ebay2DiffUnits / $inv) * 100) > 3;
                }
                if ($ebay2IsNotMap) {
                    $ebay2NotMapCount++;
                }
                $ebay2Within3 = ! $ebay2IsNotMap;
            }
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
            $ebay3NrReq = $this->isReqStatus($nrStatus3, $pm->sku) ? 'REQ' : 'NR';
            // eBay 3 caps listing stock at 100, so the expected stock is min(INV, 100).
            // Allow a 3% tolerance against that expected value. NL / 0 stock and Not Req rows are not counted.
            $ebay3IsNotMap = false;
            $ebay3Within3 = false;
            if ($ebay3ItemId && $ebay3ItemId !== null && $ebay3ItemId !== '' && $ebay3NrReq === 'REQ' && $inv > 0 && $ebay3Stock > 0) {
                $ebay3Expected = min($inv, 100.0);
                $ebay3DiffUnits = abs($ebay3Stock - $ebay3Expected);
                if ($ebay3Expected * 0.03 < 3) {
                    $ebay3IsNotMap = $ebay3DiffUnits > 3;
                } else {
                    $ebay3IsNotMap = round(($ebay3DiffUnits / $ebay3Expected) * 100) > 3;
                }
                if ($ebay3IsNotMap) {
                    $ebay3NotMapCount++;
                }
                $ebay3Within3 = ! $ebay3IsNotMap;
            }
            $ebay3NotListed = ! ($ebay3ItemId && $ebay3ItemId !== null && $ebay3ItemId !== '');
            $ebay3MissingListing = $ebay3NotListed && $inv > 0;
            if ($ebay3MissingListing && $ebay3NrReq === 'REQ') {
                $ebay3MissingListingCount++;
            }

            // ---- Amazon ----
            $amazonStock = floatval($key !== '' ? ($amazonStockByNorm[$key] ?? 0) : 0);
            $amazonAsin = $amazonSheet->asin ?? '';
            $amazonSku = $amazonSheet->sku ?? null;
            $amazonReason = $amazonSku !== null ? $this->skuDifferenceReason($pm->sku, $amazonSku) : '';
            $amazonHasIssue = $amazonReason !== '';
            if ($amazonHasIssue) {
                $amazonMismatchCount++;
            }
            $amazonNrReq = $this->isReq($nrValuesAmz, $pm->sku) ? 'REQ' : 'NRL';
            // N Map within a 3% tolerance, both sides with stock, REQ only (NL / Not Req not counted).
            $amazonIsNotMap = false;
            $amazonWithin3 = false;
            if ($amazonAsin && $amazonAsin !== null && $amazonAsin !== '' && $amazonNrReq === 'REQ' && $inv > 0 && $amazonStock > 0) {
                $amazonDiffUnits = abs($inv - $amazonStock);
                if ($inv * 0.03 < 3) {
                    $amazonIsNotMap = $amazonDiffUnits > 3;
                } else {
                    $amazonIsNotMap = round(($amazonDiffUnits / $inv) * 100) > 3;
                }
                if ($amazonIsNotMap) {
                    $amazonNotMapCount++;
                }
                $amazonWithin3 = ! $amazonIsNotMap;
            }
            $amazonNotListed = ! ($amazonAsin && $amazonAsin !== null && $amazonAsin !== '');
            $amazonMissingListing = $amazonNotListed && $inv > 0;
            if ($amazonMissingListing && $amazonNrReq === 'REQ') {
                $amazonMissingListingCount++;
            }

            $result[] = [
                '(Child) sku'    => $pm->sku,
                'INV'            => $shopify->inv ?? 0,
                'listed_on'      => '',

                'Ebay Inv'        => $ebay->ebay_stock ?? 0,
                'ebay_sku'        => $ebaySku,
                'ebay_mismatch'   => $ebayHasIssue,
                'issue'           => $ebayReason,
                'has_issue'       => $ebayHasIssue,
                'is_not_map'      => $ebayIsNotMap,
                'ebay_within3'    => $ebayWithin3,
                'missing_listing' => $ebayMissingListing,
                'ebay_nr_req'     => $ebayNrReq,

                'Ebay2 Inv'             => $ebay2->ebay_stock ?? 0,
                'ebay2_sku'             => $ebay2Sku,
                'ebay2_mismatch'        => $ebay2HasIssue,
                'ebay2_issue'           => $ebay2Reason,
                'ebay2_not_map'         => $ebay2IsNotMap,
                'ebay2_within3'         => $ebay2Within3,
                'ebay2_missing_listing' => $ebay2MissingListing,
                'ebay2_nr_req'          => $ebay2NrReq,

                'Ebay3 Inv'             => $ebay3->ebay_stock ?? 0,
                'ebay3_sku'             => $ebay3Sku,
                'ebay3_mismatch'        => $ebay3HasIssue,
                'ebay3_issue'           => $ebay3Reason,
                'ebay3_not_map'         => $ebay3IsNotMap,
                'ebay3_within3'         => $ebay3Within3,
                'ebay3_missing_listing' => $ebay3MissingListing,
                'ebay3_nr_req'          => $ebay3NrReq,

                'Amazon Inv'             => $amazonStock,
                'amazon_sku'             => $amazonSku,
                'amazon_mismatch'        => $amazonHasIssue,
                'amazon_issue'           => $amazonReason,
                'amazon_not_map'         => $amazonIsNotMap,
                'amazon_within3'         => $amazonWithin3,
                'amazon_missing_listing' => $amazonMissingListing,
                'amazon_nr_req'          => $amazonNrReq,

                'pm_missing'            => false,
            ];
        }

        // Optionally append SKUs that exist on a marketplace but NOT in Product Master.
        $pmMissingCount = 0;
        if ($request->boolean('site_only')) {
            $siteOnlyRows = $this->buildSiteOnlyRows($skus);
            $pmMissingCount = count($siteOnlyRows);
            $result = array_merge($result, $siteOnlyRows);
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
            'amazon_not_map_count'  => $amazonNotMapCount,
            'amazon_mismatch_count' => $amazonMismatchCount,
            'amazon_missing_listing_count' => $amazonMissingListingCount,
            'pm_missing_count'      => $pmMissingCount,
        ]);
    }

    /**
     * Build rows for SKUs that exist on a marketplace (listed) but are missing
     * from Product Master.
     *
     * @param  array<int, string>  $productSkus
     * @return array<int, array<string, mixed>>
     */
    private function buildSiteOnlyRows(array $productSkus): array
    {
        $pmKeys = [];
        foreach ($productSkus as $s) {
            $k = ShopifySku::normalizeSkuForShopifyLookup((string) $s);
            if ($k !== '') {
                $pmKeys[$k] = true;
            }
        }

        $map = [];
        $scan = function (string $modelClass, string $field) use (&$map, $pmKeys) {
            $modelClass::query()
                ->select('sku', 'ebay_stock', 'item_id', 'id')
                ->whereNotNull('item_id')
                ->where('item_id', '!=', '')
                ->orderBy('id')
                ->chunkById(3000, function ($rows) use (&$map, $pmKeys, $field) {
                    foreach ($rows as $r) {
                        $k = ShopifySku::normalizeSkuForShopifyLookup($r->sku);
                        if ($k === '' || isset($pmKeys[$k])) {
                            continue;
                        }
                        if (! isset($map[$k])) {
                            $map[$k] = ['sku' => $r->sku, 'ebay' => null, 'ebay2' => null, 'ebay3' => null, 'amazon' => null];
                        }
                        $map[$k][$field] = $r->ebay_stock ?? 0;
                    }

                    return true;
                });
        };

        $scan(EbayMetric::class, 'ebay');
        $scan(Ebay2Metric::class, 'ebay2');
        $scan(Ebay3Metric::class, 'ebay3');

        // Amazon: a SKU is "listed" when it has an ASIN in amazon_datsheets.
        AmazonDatasheet::query()
            ->select('sku', 'asin', 'id')
            ->whereNotNull('asin')
            ->where('asin', '!=', '')
            ->orderBy('id')
            ->chunkById(3000, function ($rows) use (&$map, $pmKeys) {
                foreach ($rows as $r) {
                    $k = ShopifySku::normalizeSkuForShopifyLookup((string) $r->sku);
                    if ($k === '' || isset($pmKeys[$k])) {
                        continue;
                    }
                    if (! isset($map[$k])) {
                        $map[$k] = ['sku' => $r->sku, 'ebay' => null, 'ebay2' => null, 'ebay3' => null, 'amazon' => null];
                    }
                    $map[$k]['amazon'] = 0; // listed flag; real stock filled in below
                }

                return true;
            });

        // Fill Amazon stock for the collected site-only SKUs.
        $amazonSiteOnlySkus = [];
        foreach ($map as $m) {
            if ($m['amazon'] !== null) {
                $amazonSiteOnlySkus[] = $m['sku'];
            }
        }
        if ($amazonSiteOnlySkus !== []) {
            $stockByNorm = $this->buildAmazonStockLookupByNormalizedSku($amazonSiteOnlySkus);
            foreach ($map as $k => $m) {
                if ($m['amazon'] !== null) {
                    $map[$k]['amazon'] = $stockByNorm[$k] ?? 0;
                }
            }
        }

        $rows = [];
        foreach ($map as $m) {
            $listedOn = [];
            if ($m['ebay'] !== null) {
                $listedOn[] = 'eBay';
            }
            if ($m['ebay2'] !== null) {
                $listedOn[] = 'eBay 2';
            }
            if ($m['ebay3'] !== null) {
                $listedOn[] = 'eBay 3';
            }
            if ($m['amazon'] !== null) {
                $listedOn[] = 'Amazon';
            }

            $rows[] = [
                '(Child) sku'    => $m['sku'],
                'INV'            => 0,
                'listed_on'      => implode(', ', $listedOn),

                'Ebay Inv'        => $m['ebay'] ?? 0,
                'ebay_sku'        => $m['ebay'] !== null ? $m['sku'] : null,
                'ebay_mismatch'   => false,
                'issue'           => '',
                'has_issue'       => false,
                'is_not_map'      => false,
                'ebay_within3'    => false,
                'missing_listing' => false,
                'ebay_nr_req'     => 'REQ',

                'Ebay2 Inv'             => $m['ebay2'] ?? 0,
                'ebay2_sku'             => $m['ebay2'] !== null ? $m['sku'] : null,
                'ebay2_mismatch'        => false,
                'ebay2_issue'           => '',
                'ebay2_not_map'         => false,
                'ebay2_within3'         => false,
                'ebay2_missing_listing' => false,
                'ebay2_nr_req'          => 'REQ',

                'Ebay3 Inv'             => $m['ebay3'] ?? 0,
                'ebay3_sku'             => $m['ebay3'] !== null ? $m['sku'] : null,
                'ebay3_mismatch'        => false,
                'ebay3_issue'           => '',
                'ebay3_not_map'         => false,
                'ebay3_within3'         => false,
                'ebay3_missing_listing' => false,
                'ebay3_nr_req'          => 'REQ',

                'Amazon Inv'             => $m['amazon'] ?? 0,
                'amazon_sku'             => $m['amazon'] !== null ? $m['sku'] : null,
                'amazon_mismatch'        => false,
                'amazon_issue'           => '',
                'amazon_not_map'         => false,
                'amazon_within3'         => false,
                'amazon_missing_listing' => false,
                'amazon_nr_req'          => 'REQ',

                'pm_missing'            => true,
            ];
        }

        return $rows;
    }

    /**
     * Update the NR/REQ status for a SKU on a given marketplace.
     */
    public function updateNrReq(Request $request)
    {
        $sku    = (string) $request->input('sku');
        $market = (string) $request->input('marketplace');
        $status = strtoupper((string) $request->input('status'));

        // Per-marketplace config: model, JSON key, and the "not required" status value.
        // eBay 1 stores REQ/NRL under "NRL" in EbayDataView.
        // eBay 2 & 3 store REQ/NR under "nr_req" in their listing-status tables.
        $config = [
            'ebay'   => ['model' => EbayDataView::class,           'key' => 'NRL',    'notReq' => 'NRL'],
            'ebay2'  => ['model' => EbayTwoListingStatus::class,   'key' => 'nr_req', 'notReq' => 'NR'],
            'ebay3'  => ['model' => EbayThreeListingStatus::class, 'key' => 'nr_req', 'notReq' => 'NR'],
            'amazon' => ['model' => AmazonDataView::class,         'key' => 'NRL',    'notReq' => 'NRL'],
        ];

        if ($sku === '' || ! isset($config[$market])) {
            return response()->json(['success' => false, 'message' => 'Invalid request'], 422);
        }

        $cfg = $config[$market];
        if (! in_array($status, ['REQ', $cfg['notReq']], true)) {
            return response()->json(['success' => false, 'message' => 'Invalid status'], 422);
        }

        $modelClass = $cfg['model'];
        $row = $modelClass::whereRaw('LOWER(sku) = ?', [strtolower($sku)])->first();
        if (! $row) {
            $row = new $modelClass();
            $row->sku = $sku;
        }

        $value = is_array($row->value) ? $row->value : (json_decode((string) $row->value, true) ?: []);
        $value[$cfg['key']] = $status;
        $row->value = $value;
        $row->save();

        return response()->json(['success' => true, 'sku' => $sku, 'marketplace' => $market, 'status' => $status]);
    }

    /**
     * Build a SKU(lowercased) => nr_req status lookup from a listing-status model.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  array<int, string>  $skus
     * @return array<string, string>
     */
    private function buildNrReqStatusLookup(string $modelClass, array $skus): array
    {
        $out = [];
        foreach ($modelClass::whereIn('sku', $skus)->get(['sku', 'value']) as $row) {
            $value = is_array($row->value) ? $row->value : (json_decode((string) $row->value, true) ?: []);
            $out[strtolower(trim((string) $row->sku))] = strtoupper((string) ($value['nr_req'] ?? 'REQ'));
        }

        return $out;
    }

    /**
     * Whether a SKU is REQ in a listing-status nr_req lookup (default REQ).
     *
     * @param  array<string, string>  $statusMap
     */
    private function isReqStatus(array $statusMap, string $sku): bool
    {
        $status = $statusMap[strtolower(trim($sku))] ?? 'REQ';

        return $status === 'REQ';
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
     * Build a normalized-SKU => Amazon stock (inventory_amazon) lookup from
     * product_stock_mappings, with a full-scan fallback for formatting variants.
     *
     * @param  array<int, string>  $productSkus
     * @return array<string, float>
     */
    private function buildAmazonStockLookupByNormalizedSku(array $productSkus): array
    {
        $byNorm = [];

        foreach (ProductStockMapping::select('sku', 'inventory_amazon')->whereIn('sku', $productSkus)->get() as $row) {
            $k = ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku);
            if ($k !== '' && ! isset($byNorm[$k])) {
                $byNorm[$k] = floatval($row->inventory_amazon ?? 0);
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

        ProductStockMapping::query()
            ->select('sku', 'inventory_amazon', 'id')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(3000, function ($rows) use (&$byNorm, &$missing) {
                foreach ($rows as $row) {
                    $k = ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku);
                    if ($k !== '' && isset($missing[$k]) && ! isset($byNorm[$k])) {
                        $byNorm[$k] = floatval($row->inventory_amazon ?? 0);
                        unset($missing[$k]);
                    }
                }

                return count($missing) > 0;
            });

        return $byNorm;
    }

    /**
     * Build a normalized-SKU => Amazon datasheet row (sku + asin) lookup, with a
     * full-scan fallback for SKUs that differ only by formatting.
     *
     * @param  array<int, string>  $productSkus
     * @return array<string, \Illuminate\Database\Eloquent\Model>
     */
    private function buildAmazonSheetLookupByNormalizedSku(array $productSkus): array
    {
        $byNorm = [];

        foreach (AmazonDatasheet::select('sku', 'asin')->whereIn('sku', $productSkus)->get() as $row) {
            $k = ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku);
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

        AmazonDatasheet::query()
            ->select('sku', 'asin', 'id')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(3000, function ($rows) use (&$byNorm, &$missing) {
                foreach ($rows as $row) {
                    $k = ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku);
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
