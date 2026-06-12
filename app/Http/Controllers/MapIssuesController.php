<?php

namespace App\Http\Controllers;

use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\AmazonListingStatus;
use App\Models\BestbuyPriceData;
use App\Models\BestbuyUSAListingStatus;
use App\Models\BestbuyUsaProduct;
use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use App\Models\EbayThreeListingStatus;
use App\Models\EbayTwoListingStatus;
use App\Models\MacyProduct;
use App\Models\MacysListingStatus;
use App\Models\ProductMaster;
use App\Models\ProductStockMapping;
use App\Models\ReverbListingStatus;
use App\Models\ReverbProduct;
use App\Models\ShopifySku;
use App\Models\TemuListingStatus;
use App\Models\TemuPricing;
use App\Models\TiendamiaDataView;
use App\Models\TiendamiaProduct;
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
                'reverb_not_map_count' => 0,
                'reverb_mismatch_count' => 0,
                'reverb_missing_listing_count' => 0,
                'macys_not_map_count' => 0,
                'macys_mismatch_count' => 0,
                'macys_missing_listing_count' => 0,
                'bestbuy_not_map_count' => 0,
                'bestbuy_mismatch_count' => 0,
                'bestbuy_missing_listing_count' => 0,
                'tiendamia_not_map_count' => 0,
                'tiendamia_mismatch_count' => 0,
                'tiendamia_missing_listing_count' => 0,
                'temu_not_map_count' => 0,
                'temu_mismatch_count' => 0,
                'temu_missing_listing_count' => 0,
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
        // Amazon REQ/NR falls back to AmazonListingStatus.nr_req (same as the
        // amazon-tabulator-view page) when amazon_data_view has no NRL flag.
        $amazonListingStatus = AmazonListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        // Reverb: stock (remaining_inventory), price and stored SKU come from reverb_products
        // (listed = price > 0, like the reverb-pricing page), and REQ/NR comes from
        // reverb_listing_statuses' "rl_nrl" key (RL = REQ, NRL = NR).
        $reverbByNorm    = $this->buildReverbLookupByNormalizedSku($skus);
        $nrStatusReverb  = $this->buildReverbNrReqStatusLookup($skus);

        // Macy's: stock, price and stored SKU come from macy_products (listed = price > 0,
        // like the macys-pricing page), and REQ/NR comes from macys_listing_statuses' "nr_req" key.
        $macyByNorm      = $this->buildMacyLookupByNormalizedSku($skus);
        $nrStatusMacy    = $this->buildNrReqStatusLookup(MacysListingStatus::class, $skus);

        // Best Buy: stock and stored SKU come from bestbuy_usa_products; price comes from
        // bestbuy_price_data (fallback to product price) — listed = price > 0, like the
        // bestbuy-pricing page. REQ/NR comes from bestbuy_usa_listing_statuses' "nr_req" key.
        $bestbuyByNorm      = $this->buildBestbuyLookupByNormalizedSku($skus);
        $bestbuyPriceByNorm = $this->buildBestbuyPriceLookupByNormalizedSku($skus);
        $nrStatusBestbuy    = $this->buildNrReqStatusLookup(BestbuyUSAListingStatus::class, $skus);

        // Tiendamia: stock and stored SKU come from tiendamia_products (listed = the SKU exists
        // in tiendamia_products, like the tiendamia-pricing page's Missing rule). REQ/NR comes
        // from tiendamia_data_views' "NRP" key (RA = REQ, NRA = NR).
        $tiendamiaByNorm    = $this->buildTiendamiaLookupByNormalizedSku($skus);
        $nrStatusTiendamia  = $this->buildTiendamiaNrReqStatusLookup($skus);

        // Temu: stock (temu_pricing.quantity), price (base_price) and stored SKU come from
        // temu_pricing (listed = a live pricing row with base price > 0, same as the
        // temu-decrease page's Missing rule). REQ/NR comes from temu_listing_statuses' "nr_req"
        // key (default REQ when INV > 0), matching the temu-decrease page.
        $temuByNorm     = $this->buildTemuLookupByNormalizedSku($skus);
        $nrStatusTemu   = $this->buildTemuNrReqStatusLookup($skus);

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
        $reverbNotMapCount = 0;          // Reverb: INV != Reverb Stock
        $reverbMismatchCount = 0;        // Reverb: SKU stored differently
        $reverbMissingListingCount = 0;  // Reverb: not listed, REQ, INV > 0
        $macysNotMapCount = 0;           // Macy's: INV != Macy's Stock
        $macysMismatchCount = 0;         // Macy's: SKU stored differently
        $macysMissingListingCount = 0;   // Macy's: not listed, REQ, INV > 0
        $bestbuyNotMapCount = 0;         // Best Buy: INV != Best Buy Stock
        $bestbuyMismatchCount = 0;       // Best Buy: SKU stored differently
        $bestbuyMissingListingCount = 0; // Best Buy: not listed, REQ, INV > 0
        $tiendamiaNotMapCount = 0;          // Tiendamia: INV != Tiendamia Stock
        $tiendamiaMismatchCount = 0;        // Tiendamia: SKU stored differently
        $tiendamiaMissingListingCount = 0;  // Tiendamia: not listed, REQ, INV > 0
        $temuNotMapCount = 0;          // Temu: INV != Temu Stock
        $temuMismatchCount = 0;        // Temu: SKU stored differently
        $temuMissingListingCount = 0;  // Temu: not listed, REQ, INV > 0
        foreach ($productMasters as $pm) {
            $key = ShopifySku::normalizeSkuForShopifyLookup($pm->sku);

            $shopify = $key !== '' ? ($shopifyByNorm[$key] ?? null) : null;
            $ebay    = $key !== '' ? ($ebayByNorm[$key] ?? null) : null;
            $ebay2   = $key !== '' ? ($ebay2ByNorm[$key] ?? null) : null;
            $ebay3   = $key !== '' ? ($ebay3ByNorm[$key] ?? null) : null;
            // Amazon uses its own normalization (remove ALL spaces), same as the
            // amazon-tabulator-view page, so listed/stock matching is identical.
            $amazonKey = AmazonDatasheet::normalizeSkuForLookup($pm->sku);
            $amazonSheet = $amazonKey !== '' ? ($amazonSheetByNorm[$amazonKey] ?? null) : null;
            // Reverb uses its own normalization (PCS→PC + no-space fallback), same as reverb-pricing.
            $reverbKey = ReverbProduct::normalizeSkuForLookup($pm->sku);
            $reverb  = $reverbKey !== '' ? ($reverbByNorm[$reverbKey] ?? null) : null;
            $macy    = $key !== '' ? ($macyByNorm[$key] ?? null) : null;
            $bestbuy = $key !== '' ? ($bestbuyByNorm[$key] ?? null) : null;
            $tiendamia = $key !== '' ? ($tiendamiaByNorm[$key] ?? null) : null;
            // Temu uses its own normalization (PCS→PC + collapsed spaces), same as temu-decrease.
            $temuKey = $this->normalizeTemuSku($pm->sku);
            $temu    = $temuKey !== '' ? ($temuByNorm[$temuKey] ?? null) : null;

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
            $amazonStock = floatval($amazonKey !== '' ? ($amazonStockByNorm[$amazonKey] ?? 0) : 0);
            $amazonAsin = $amazonSheet->asin ?? '';
            $amazonSku = $amazonSheet->sku ?? null;
            $amazonReason = $amazonSku !== null ? $this->skuDifferenceReason($pm->sku, $amazonSku, [AmazonDatasheet::class, 'normalizeSkuForLookup']) : '';
            $amazonHasIssue = $amazonReason !== '';
            if ($amazonHasIssue) {
                $amazonMismatchCount++;
            }
            $amazonNrReq = $this->resolveAmazonNrReq($nrValuesAmz, $amazonListingStatus, $pm->sku);
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

            // ---- Reverb ----
            $reverbStock = floatval($reverb->remaining_inventory ?? 0);
            $reverbPrice = floatval($reverb->price ?? 0);
            $reverbListed = $reverbPrice > 0; // live listing, same as the reverb-pricing page
            $reverbSku = $reverb->sku ?? null;
            $reverbReason = $reverbSku !== null ? $this->skuDifferenceReason($pm->sku, $reverbSku, [ReverbProduct::class, 'normalizeSkuForLookup']) : '';
            $reverbHasIssue = $reverbReason !== '';
            if ($reverbHasIssue) {
                $reverbMismatchCount++;
            }
            $reverbNrReq = $this->isReqStatus($nrStatusReverb, $pm->sku) ? 'REQ' : 'NR';
            $reverbIsNotMap = false;
            $reverbWithin3 = false;
            if ($reverbListed && $reverbNrReq === 'REQ' && $inv > 0 && $reverbStock > 0) {
                $reverbDiffUnits = abs($inv - $reverbStock);
                if ($inv * 0.03 < 3) {
                    $reverbIsNotMap = $reverbDiffUnits > 3;
                } else {
                    $reverbIsNotMap = round(($reverbDiffUnits / $inv) * 100) > 3;
                }
                if ($reverbIsNotMap) {
                    $reverbNotMapCount++;
                }
                $reverbWithin3 = ! $reverbIsNotMap;
            }
            $reverbMissingListing = ! $reverbListed && $inv > 0;
            if ($reverbMissingListing && $reverbNrReq === 'REQ') {
                $reverbMissingListingCount++;
            }

            // ---- Macy's ----
            $macyStock = floatval($macy->stock ?? 0);
            $macyPrice = floatval($macy->price ?? 0);
            $macyListed = $macyPrice > 0; // live listing, same as the macys-pricing page
            $macySku = $macy->sku ?? null;
            $macyReason = $macySku !== null ? $this->skuDifferenceReason($pm->sku, $macySku) : '';
            $macyHasIssue = $macyReason !== '';
            if ($macyHasIssue) {
                $macysMismatchCount++;
            }
            $macyNrReq = $this->isReqStatus($nrStatusMacy, $pm->sku) ? 'REQ' : 'NR';
            $macyIsNotMap = false;
            $macyWithin3 = false;
            if ($macyListed && $macyNrReq === 'REQ' && $inv > 0 && $macyStock > 0) {
                $macyDiffUnits = abs($inv - $macyStock);
                if ($inv * 0.03 < 3) {
                    $macyIsNotMap = $macyDiffUnits > 3;
                } else {
                    $macyIsNotMap = round(($macyDiffUnits / $inv) * 100) > 3;
                }
                if ($macyIsNotMap) {
                    $macysNotMapCount++;
                }
                $macyWithin3 = ! $macyIsNotMap;
            }
            $macyMissingListing = ! $macyListed && $inv > 0;
            if ($macyMissingListing && $macyNrReq === 'REQ') {
                $macysMissingListingCount++;
            }

            // ---- Best Buy ----
            $bestbuyStock = floatval($bestbuy->stock ?? 0);
            $bestbuyDataPrice = $key !== '' ? ($bestbuyPriceByNorm[$key] ?? null) : null;
            $bestbuyPrice = $bestbuyDataPrice !== null ? floatval($bestbuyDataPrice) : floatval($bestbuy->price ?? 0);
            $bestbuyListed = $bestbuyPrice > 0; // live listing, same as the bestbuy-pricing page
            $bestbuySku = $bestbuy->sku ?? null;
            $bestbuyReason = $bestbuySku !== null ? $this->skuDifferenceReason($pm->sku, $bestbuySku) : '';
            $bestbuyHasIssue = $bestbuyReason !== '';
            if ($bestbuyHasIssue) {
                $bestbuyMismatchCount++;
            }
            $bestbuyNrReq = $this->isReqStatus($nrStatusBestbuy, $pm->sku) ? 'REQ' : 'NR';
            $bestbuyIsNotMap = false;
            $bestbuyWithin3 = false;
            if ($bestbuyListed && $bestbuyNrReq === 'REQ' && $inv > 0 && $bestbuyStock > 0) {
                $bestbuyDiffUnits = abs($inv - $bestbuyStock);
                if ($inv * 0.03 < 3) {
                    $bestbuyIsNotMap = $bestbuyDiffUnits > 3;
                } else {
                    $bestbuyIsNotMap = round(($bestbuyDiffUnits / $inv) * 100) > 3;
                }
                if ($bestbuyIsNotMap) {
                    $bestbuyNotMapCount++;
                }
                $bestbuyWithin3 = ! $bestbuyIsNotMap;
            }
            $bestbuyMissingListing = ! $bestbuyListed && $inv > 0;
            if ($bestbuyMissingListing && $bestbuyNrReq === 'REQ') {
                $bestbuyMissingListingCount++;
            }

            // ---- Tiendamia ----
            $tiendamiaStock = floatval($tiendamia->stock ?? 0);
            $tiendamiaListed = $tiendamia !== null; // SKU exists in tiendamia_products
            $tiendamiaSku = $tiendamia->sku ?? null;
            $tiendamiaReason = $tiendamiaSku !== null ? $this->skuDifferenceReason($pm->sku, $tiendamiaSku) : '';
            $tiendamiaHasIssue = $tiendamiaReason !== '';
            if ($tiendamiaHasIssue) {
                $tiendamiaMismatchCount++;
            }
            $tiendamiaNrReq = $this->isReqStatus($nrStatusTiendamia, $pm->sku) ? 'REQ' : 'NR';
            $tiendamiaIsNotMap = false;
            $tiendamiaWithin3 = false;
            if ($tiendamiaListed && $tiendamiaNrReq === 'REQ' && $inv > 0 && $tiendamiaStock > 0) {
                $tiendamiaDiffUnits = abs($inv - $tiendamiaStock);
                if ($inv * 0.03 < 3) {
                    $tiendamiaIsNotMap = $tiendamiaDiffUnits > 3;
                } else {
                    $tiendamiaIsNotMap = round(($tiendamiaDiffUnits / $inv) * 100) > 3;
                }
                if ($tiendamiaIsNotMap) {
                    $tiendamiaNotMapCount++;
                }
                $tiendamiaWithin3 = ! $tiendamiaIsNotMap;
            }
            $tiendamiaMissingListing = ! $tiendamiaListed && $inv > 0;
            if ($tiendamiaMissingListing && $tiendamiaNrReq === 'REQ') {
                $tiendamiaMissingListingCount++;
            }

            // ---- Temu ----
            $temuStock = floatval($temu->quantity ?? 0);
            $temuBasePrice = floatval($temu->base_price ?? 0);
            // Listed = a live pricing row with a base price (same as the temu-decrease page's
            // Missing rule: missing when not in pricing, or in pricing with INV > 0 and no price).
            $temuListed = $temu !== null && $temuBasePrice > 0;
            $temuSku = $temu->sku ?? null;
            $temuReason = $temuSku !== null ? $this->skuDifferenceReason($pm->sku, $temuSku, [$this, 'normalizeTemuSku']) : '';
            $temuHasIssue = $temuReason !== '';
            if ($temuHasIssue) {
                $temuMismatchCount++;
            }
            $temuNrReq = $this->resolveTemuNrReq($nrStatusTemu, $pm->sku, $inv);
            $temuIsNotMap = false;
            $temuWithin3 = false;
            if ($temuListed && $temuNrReq === 'REQ' && $inv > 0 && $temuStock > 0) {
                $temuDiffUnits = abs($inv - $temuStock);
                if ($inv * 0.03 < 3) {
                    $temuIsNotMap = $temuDiffUnits > 3;
                } else {
                    $temuIsNotMap = round(($temuDiffUnits / $inv) * 100) > 3;
                }
                if ($temuIsNotMap) {
                    $temuNotMapCount++;
                }
                $temuWithin3 = ! $temuIsNotMap;
            }
            $temuMissingListing = ! $temuListed && $inv > 0;
            if ($temuMissingListing && $temuNrReq === 'REQ') {
                $temuMissingListingCount++;
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

                'Reverb Inv'             => $reverbStock,
                'reverb_sku'             => $reverbSku,
                'reverb_mismatch'        => $reverbHasIssue,
                'reverb_issue'           => $reverbReason,
                'reverb_not_map'         => $reverbIsNotMap,
                'reverb_within3'         => $reverbWithin3,
                'reverb_missing_listing' => $reverbMissingListing,
                'reverb_nr_req'          => $reverbNrReq,

                'Macys Inv'             => $macyStock,
                'macys_sku'             => $macySku,
                'macys_mismatch'        => $macyHasIssue,
                'macys_issue'           => $macyReason,
                'macys_not_map'         => $macyIsNotMap,
                'macys_within3'         => $macyWithin3,
                'macys_missing_listing' => $macyMissingListing,
                'macys_nr_req'          => $macyNrReq,

                'Bestbuy Inv'            => $bestbuyStock,
                'bestbuy_sku'            => $bestbuySku,
                'bestbuy_mismatch'       => $bestbuyHasIssue,
                'bestbuy_issue'          => $bestbuyReason,
                'bestbuy_not_map'        => $bestbuyIsNotMap,
                'bestbuy_within3'        => $bestbuyWithin3,
                'bestbuy_missing_listing'=> $bestbuyMissingListing,
                'bestbuy_nr_req'         => $bestbuyNrReq,

                'Tiendamia Inv'            => $tiendamiaStock,
                'tiendamia_sku'            => $tiendamiaSku,
                'tiendamia_mismatch'       => $tiendamiaHasIssue,
                'tiendamia_issue'          => $tiendamiaReason,
                'tiendamia_not_map'        => $tiendamiaIsNotMap,
                'tiendamia_within3'        => $tiendamiaWithin3,
                'tiendamia_missing_listing'=> $tiendamiaMissingListing,
                'tiendamia_nr_req'         => $tiendamiaNrReq,

                'Temu Inv'              => $temuStock,
                'temu_sku'              => $temuSku,
                'temu_mismatch'         => $temuHasIssue,
                'temu_issue'            => $temuReason,
                'temu_not_map'          => $temuIsNotMap,
                'temu_within3'          => $temuWithin3,
                'temu_missing_listing'  => $temuMissingListing,
                'temu_nr_req'           => $temuNrReq,

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
            'reverb_not_map_count'  => $reverbNotMapCount,
            'reverb_mismatch_count' => $reverbMismatchCount,
            'reverb_missing_listing_count' => $reverbMissingListingCount,
            'macys_not_map_count'   => $macysNotMapCount,
            'macys_mismatch_count'  => $macysMismatchCount,
            'macys_missing_listing_count' => $macysMissingListingCount,
            'bestbuy_not_map_count'  => $bestbuyNotMapCount,
            'bestbuy_mismatch_count' => $bestbuyMismatchCount,
            'bestbuy_missing_listing_count' => $bestbuyMissingListingCount,
            'tiendamia_not_map_count'  => $tiendamiaNotMapCount,
            'tiendamia_mismatch_count' => $tiendamiaMismatchCount,
            'tiendamia_missing_listing_count' => $tiendamiaMissingListingCount,
            'temu_not_map_count'  => $temuNotMapCount,
            'temu_mismatch_count' => $temuMismatchCount,
            'temu_missing_listing_count' => $temuMissingListingCount,
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
                            $map[$k] = ['sku' => $r->sku, 'ebay' => null, 'ebay2' => null, 'ebay3' => null, 'amazon' => null, 'reverb' => null, 'macys' => null, 'bestbuy' => null, 'tiendamia' => null, 'temu' => null];
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
                        $map[$k] = ['sku' => $r->sku, 'ebay' => null, 'ebay2' => null, 'ebay3' => null, 'amazon' => null, 'reverb' => null, 'macys' => null, 'bestbuy' => null, 'tiendamia' => null, 'temu' => null];
                    }
                    $map[$k]['amazon'] = 0; // listed flag; real stock filled in below
                }

                return true;
            });

        // Reverb: a SKU is "listed" when it has a live listing (price > 0) in reverb_products.
        ReverbProduct::query()
            ->select('sku', 'price', 'remaining_inventory', 'id')
            ->where('price', '>', 0)
            ->orderBy('id')
            ->chunkById(3000, function ($rows) use (&$map, $pmKeys) {
                foreach ($rows as $r) {
                    $k = ShopifySku::normalizeSkuForShopifyLookup((string) $r->sku);
                    if ($k === '' || isset($pmKeys[$k])) {
                        continue;
                    }
                    if (! isset($map[$k])) {
                        $map[$k] = ['sku' => $r->sku, 'ebay' => null, 'ebay2' => null, 'ebay3' => null, 'amazon' => null, 'reverb' => null, 'macys' => null, 'bestbuy' => null, 'tiendamia' => null, 'temu' => null];
                    }
                    $map[$k]['reverb'] = floatval($r->remaining_inventory ?? 0);
                }

                return true;
            });

        // Macy's: a SKU is "listed" when it has a live listing (price > 0) in macy_products.
        MacyProduct::query()
            ->select('sku', 'price', 'stock', 'id')
            ->where('price', '>', 0)
            ->orderBy('id')
            ->chunkById(3000, function ($rows) use (&$map, $pmKeys) {
                foreach ($rows as $r) {
                    $k = ShopifySku::normalizeSkuForShopifyLookup((string) $r->sku);
                    if ($k === '' || isset($pmKeys[$k])) {
                        continue;
                    }
                    if (! isset($map[$k])) {
                        $map[$k] = ['sku' => $r->sku, 'ebay' => null, 'ebay2' => null, 'ebay3' => null, 'amazon' => null, 'reverb' => null, 'macys' => null, 'bestbuy' => null, 'tiendamia' => null, 'temu' => null];
                    }
                    $map[$k]['macys'] = floatval($r->stock ?? 0);
                }

                return true;
            });

        // Best Buy: a SKU is "listed" when it has a live listing (price > 0) in bestbuy_usa_products.
        BestbuyUsaProduct::query()
            ->select('sku', 'price', 'stock', 'id')
            ->where('price', '>', 0)
            ->orderBy('id')
            ->chunkById(3000, function ($rows) use (&$map, $pmKeys) {
                foreach ($rows as $r) {
                    $k = ShopifySku::normalizeSkuForShopifyLookup((string) $r->sku);
                    if ($k === '' || isset($pmKeys[$k])) {
                        continue;
                    }
                    if (! isset($map[$k])) {
                        $map[$k] = ['sku' => $r->sku, 'ebay' => null, 'ebay2' => null, 'ebay3' => null, 'amazon' => null, 'reverb' => null, 'macys' => null, 'bestbuy' => null, 'tiendamia' => null, 'temu' => null];
                    }
                    $map[$k]['bestbuy'] = floatval($r->stock ?? 0);
                }

                return true;
            });

        // Tiendamia: a SKU is "listed" when it exists in tiendamia_products.
        TiendamiaProduct::query()
            ->select('sku', 'stock', 'id')
            ->orderBy('id')
            ->chunkById(3000, function ($rows) use (&$map, $pmKeys) {
                foreach ($rows as $r) {
                    $k = ShopifySku::normalizeSkuForShopifyLookup((string) $r->sku);
                    if ($k === '' || isset($pmKeys[$k])) {
                        continue;
                    }
                    if (! isset($map[$k])) {
                        $map[$k] = ['sku' => $r->sku, 'ebay' => null, 'ebay2' => null, 'ebay3' => null, 'amazon' => null, 'reverb' => null, 'macys' => null, 'bestbuy' => null, 'tiendamia' => null, 'temu' => null];
                    }
                    $map[$k]['tiendamia'] = floatval($r->stock ?? 0);
                }

                return true;
            });

        // Temu: a SKU is "listed" when it has a live pricing row (base price > 0) in temu_pricing.
        TemuPricing::query()
            ->select('sku', 'base_price', 'quantity', 'id')
            ->where('base_price', '>', 0)
            ->orderBy('id')
            ->chunkById(3000, function ($rows) use (&$map, $pmKeys) {
                foreach ($rows as $r) {
                    $k = ShopifySku::normalizeSkuForShopifyLookup((string) $r->sku);
                    if ($k === '' || isset($pmKeys[$k])) {
                        continue;
                    }
                    if (! isset($map[$k])) {
                        $map[$k] = ['sku' => $r->sku, 'ebay' => null, 'ebay2' => null, 'ebay3' => null, 'amazon' => null, 'reverb' => null, 'macys' => null, 'bestbuy' => null, 'tiendamia' => null, 'temu' => null];
                    }
                    $map[$k]['temu'] = floatval($r->quantity ?? 0);
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
            // $stockByNorm is keyed by the amazon normalization (spaces removed);
            // $map is keyed by the shopify normalization, so resolve via the row SKU.
            $stockByNorm = $this->buildAmazonStockLookupByNormalizedSku($amazonSiteOnlySkus);
            foreach ($map as $k => $m) {
                if ($m['amazon'] !== null) {
                    $amzKey = AmazonDatasheet::normalizeSkuForLookup((string) $m['sku']);
                    $map[$k]['amazon'] = $stockByNorm[$amzKey] ?? 0;
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
            if ($m['reverb'] !== null) {
                $listedOn[] = 'Reverb';
            }
            if ($m['macys'] !== null) {
                $listedOn[] = "Macy's";
            }
            if ($m['bestbuy'] !== null) {
                $listedOn[] = 'Best Buy';
            }
            if ($m['tiendamia'] !== null) {
                $listedOn[] = 'Tiendamia';
            }
            if ($m['temu'] !== null) {
                $listedOn[] = 'Temu';
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

                'Reverb Inv'             => $m['reverb'] ?? 0,
                'reverb_sku'             => $m['reverb'] !== null ? $m['sku'] : null,
                'reverb_mismatch'        => false,
                'reverb_issue'           => '',
                'reverb_not_map'         => false,
                'reverb_within3'         => false,
                'reverb_missing_listing' => false,
                'reverb_nr_req'          => 'REQ',

                'Macys Inv'             => $m['macys'] ?? 0,
                'macys_sku'             => $m['macys'] !== null ? $m['sku'] : null,
                'macys_mismatch'        => false,
                'macys_issue'           => '',
                'macys_not_map'         => false,
                'macys_within3'         => false,
                'macys_missing_listing' => false,
                'macys_nr_req'          => 'REQ',

                'Bestbuy Inv'            => $m['bestbuy'] ?? 0,
                'bestbuy_sku'            => $m['bestbuy'] !== null ? $m['sku'] : null,
                'bestbuy_mismatch'       => false,
                'bestbuy_issue'          => '',
                'bestbuy_not_map'        => false,
                'bestbuy_within3'        => false,
                'bestbuy_missing_listing'=> false,
                'bestbuy_nr_req'         => 'REQ',

                'Tiendamia Inv'            => $m['tiendamia'] ?? 0,
                'tiendamia_sku'            => $m['tiendamia'] !== null ? $m['sku'] : null,
                'tiendamia_mismatch'       => false,
                'tiendamia_issue'          => '',
                'tiendamia_not_map'        => false,
                'tiendamia_within3'        => false,
                'tiendamia_missing_listing'=> false,
                'tiendamia_nr_req'         => 'REQ',

                'Temu Inv'              => $m['temu'] ?? 0,
                'temu_sku'              => $m['temu'] !== null ? $m['sku'] : null,
                'temu_mismatch'         => false,
                'temu_issue'            => '',
                'temu_not_map'          => false,
                'temu_within3'          => false,
                'temu_missing_listing'  => false,
                'temu_nr_req'           => 'REQ',

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
        // Reverb stores REQ/NR as RL/NRL under "rl_nrl" in reverb_listing_statuses.
        $config = [
            'ebay'   => ['model' => EbayDataView::class,           'key' => 'NRL',    'notReq' => 'NRL'],
            'ebay2'  => ['model' => EbayTwoListingStatus::class,   'key' => 'nr_req', 'notReq' => 'NR'],
            'ebay3'  => ['model' => EbayThreeListingStatus::class, 'key' => 'nr_req', 'notReq' => 'NR'],
            'amazon' => ['model' => AmazonDataView::class,         'key' => 'NRL',    'notReq' => 'NRL'],
            'reverb' => ['model' => ReverbListingStatus::class,    'key' => 'rl_nrl', 'notReq' => 'NR', 'transform' => ['REQ' => 'RL', 'NR' => 'NRL']],
            'macys'  => ['model' => MacysListingStatus::class,     'key' => 'nr_req', 'notReq' => 'NR'],
            'bestbuy'=> ['model' => BestbuyUSAListingStatus::class, 'key' => 'nr_req', 'notReq' => 'NR'],
            'tiendamia' => ['model' => TiendamiaDataView::class,   'key' => 'NRP',    'notReq' => 'NR', 'transform' => ['REQ' => 'RA', 'NR' => 'NRA']],
            'temu'   => ['model' => TemuListingStatus::class,      'key' => 'nr_req', 'notReq' => 'NR'],
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

        $stored = isset($cfg['transform']) ? ($cfg['transform'][$status] ?? $status) : $status;
        $value = is_array($row->value) ? $row->value : (json_decode((string) $row->value, true) ?: []);
        $value[$cfg['key']] = $stored;
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
     * Resolve Amazon REQ/NR exactly like the amazon-tabulator-view page:
     * use amazon_data_view's NRL flag first ('NRL' => NR, 'REQ' => REQ), and when
     * it is absent fall back to amazon_listing_statuses.nr_req; default REQ.
     *
     * @param  \Illuminate\Support\Collection  $nrValues       amazon_data_view value JSON keyed by SKU
     * @param  \Illuminate\Support\Collection  $listingStatus  AmazonListingStatus rows keyed by SKU
     */
    private function resolveAmazonNrReq($nrValues, $listingStatus, string $sku): string
    {
        // 1) amazon_data_view NRL flag (authoritative when present)
        if (isset($nrValues[$sku])) {
            $raw = $nrValues[$sku];
            if (! is_array($raw)) {
                $raw = json_decode($raw, true) ?? [];
            }
            $nrl = is_array($raw) ? ($raw['NRL'] ?? null) : null;
            if ($nrl === 'NRL') {
                return 'NRL';
            }
            if ($nrl === 'REQ') {
                return 'REQ';
            }
        }

        // 2) Fallback to AmazonListingStatus.nr_req
        $ls = $listingStatus[$sku] ?? null;
        if ($ls && $ls->value) {
            $val = is_array($ls->value) ? $ls->value : (json_decode((string) $ls->value, true) ?: []);
            $nrReq = strtoupper((string) ($val['nr_req'] ?? ''));
            if ($nrReq === 'NR' || $nrReq === 'NRL') {
                return 'NRL';
            }
            if ($nrReq === 'REQ') {
                return 'REQ';
            }
        }

        // 3) Default REQ
        return 'REQ';
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

        // Key by the amazon-tabulator-view normalization (remove ALL spaces) so the
        // same stock rows are resolved as on the Amazon page.
        foreach (ProductStockMapping::select('sku', 'inventory_amazon')->whereIn('sku', $productSkus)->get() as $row) {
            $k = AmazonDatasheet::normalizeSkuForLookup((string) $row->sku);
            if ($k !== '' && ! isset($byNorm[$k])) {
                $byNorm[$k] = floatval($row->inventory_amazon ?? 0);
            }
        }

        $missing = [];
        foreach ($productSkus as $pmSku) {
            $k = AmazonDatasheet::normalizeSkuForLookup((string) $pmSku);
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
                    $k = AmazonDatasheet::normalizeSkuForLookup((string) $row->sku);
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

        // Key by the amazon-tabulator-view normalization (remove ALL spaces) so the
        // same datasheet rows are resolved as on the Amazon page.
        foreach (AmazonDatasheet::select('sku', 'asin')->whereIn('sku', $productSkus)->get() as $row) {
            $k = AmazonDatasheet::normalizeSkuForLookup((string) $row->sku);
            if ($k !== '' && ! isset($byNorm[$k])) {
                $byNorm[$k] = $row;
            }
        }

        $missing = [];
        foreach ($productSkus as $pmSku) {
            $k = AmazonDatasheet::normalizeSkuForLookup((string) $pmSku);
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
                    $k = AmazonDatasheet::normalizeSkuForLookup((string) $row->sku);
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
     * Build a normalized-SKU => reverb_products row (sku + price + remaining_inventory)
     * lookup, with a full-scan fallback for SKUs that differ only by formatting.
     *
     * @param  array<int, string>  $productSkus
     * @return array<string, \Illuminate\Database\Eloquent\Model>
     */
    private function buildReverbLookupByNormalizedSku(array $productSkus): array
    {
        // Use the same lookup/normalization as the reverb-pricing page
        // (ReverbProduct::normalizeSkuForLookup adds PCS→PC + no-space fallback),
        // so R Stock / Reverb Inv match between the two pages.
        return ReverbProduct::buildLookupByNormalizedSku($productSkus);
    }

    /**
     * Build a normalized-SKU => macy_products row (sku + price + stock) lookup,
     * with a full-scan fallback for SKUs that differ only by formatting.
     *
     * @param  array<int, string>  $productSkus
     * @return array<string, \Illuminate\Database\Eloquent\Model>
     */
    private function buildMacyLookupByNormalizedSku(array $productSkus): array
    {
        $byNorm = [];

        foreach (MacyProduct::select('sku', 'price', 'stock')->whereIn('sku', $productSkus)->get() as $row) {
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

        MacyProduct::query()
            ->select('sku', 'price', 'stock', 'id')
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
     * Build a normalized-SKU => bestbuy_usa_products row (sku + price + stock) lookup,
     * with a full-scan fallback for SKUs that differ only by formatting.
     *
     * @param  array<int, string>  $productSkus
     * @return array<string, \Illuminate\Database\Eloquent\Model>
     */
    private function buildBestbuyLookupByNormalizedSku(array $productSkus): array
    {
        $byNorm = [];

        foreach (BestbuyUsaProduct::select('sku', 'price', 'stock')->whereIn('sku', $productSkus)->get() as $row) {
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

        BestbuyUsaProduct::query()
            ->select('sku', 'price', 'stock', 'id')
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
     * Build a normalized-SKU => Best Buy price lookup from bestbuy_price_data.
     *
     * @param  array<int, string>  $productSkus
     * @return array<string, float>
     */
    private function buildBestbuyPriceLookupByNormalizedSku(array $productSkus): array
    {
        $byNorm = [];

        foreach (BestbuyPriceData::select('sku', 'price')->whereIn('sku', $productSkus)->get() as $row) {
            $k = ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku);
            if ($k !== '' && ! isset($byNorm[$k])) {
                $byNorm[$k] = floatval($row->price ?? 0);
            }
        }

        return $byNorm;
    }

    /**
     * Build a normalized-SKU => tiendamia_products row (sku + stock) lookup, with a
     * full-scan fallback for SKUs that differ only by formatting.
     *
     * @param  array<int, string>  $productSkus
     * @return array<string, \Illuminate\Database\Eloquent\Model>
     */
    private function buildTiendamiaLookupByNormalizedSku(array $productSkus): array
    {
        $byNorm = [];
        $upper = array_map('strtoupper', $productSkus);

        foreach (TiendamiaProduct::select('sku', 'stock')->whereIn('sku', $upper)->get() as $row) {
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

        TiendamiaProduct::query()
            ->select('sku', 'stock', 'id')
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
     * Build a SKU(lowercased) => REQ/NR lookup from tiendamia_data_views' "NRP" key
     * (RA = REQ, NRA = NR; LATER and missing default to REQ).
     *
     * @param  array<int, string>  $skus
     * @return array<string, string>
     */
    private function buildTiendamiaNrReqStatusLookup(array $skus): array
    {
        $out = [];
        foreach (TiendamiaDataView::whereIn('sku', $skus)->get(['sku', 'value']) as $row) {
            $value = is_array($row->value) ? $row->value : (json_decode((string) $row->value, true) ?: []);
            $nrp = strtoupper((string) ($value['NRP'] ?? 'RA'));
            $out[strtolower(trim((string) $row->sku))] = ($nrp === 'NRA') ? 'NR' : 'REQ';
        }

        return $out;
    }

    /**
     * Normalize a SKU the same way the temu-decrease page does so listed/stock
     * matching is identical: uppercase, fold a trailing piece-count (PCS/PIECES → PC),
     * and collapse runs of whitespace to a single space.
     */
    private function normalizeTemuSku(string $sku): string
    {
        $sku = strtoupper(trim($sku));
        $sku = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $sku);
        $sku = preg_replace('/\s+/', ' ', $sku);

        return (string) $sku;
    }

    /**
     * Build a normalized-SKU => temu_pricing row (sku + base_price + quantity) lookup.
     * Loads every pricing row and keys by the temu-decrease normalization (first row wins),
     * exactly like the temu-decrease page builds its Missing/Map state.
     *
     * @param  array<int, string>  $productSkus
     * @return array<string, \Illuminate\Database\Eloquent\Model>
     */
    private function buildTemuLookupByNormalizedSku(array $productSkus): array
    {
        $byNorm = [];
        foreach (TemuPricing::select('sku', 'base_price', 'quantity')->get() as $row) {
            $k = $this->normalizeTemuSku((string) $row->sku);
            if ($k !== '' && ! isset($byNorm[$k])) {
                $byNorm[$k] = $row;
            }
        }

        return $byNorm;
    }

    /**
     * Build a SKU(lowercased) => raw nr_req lookup from temu_listing_statuses.value JSON.
     *
     * @param  array<int, string>  $skus
     * @return array<string, string>
     */
    private function buildTemuNrReqStatusLookup(array $skus): array
    {
        $out = [];
        foreach (TemuListingStatus::whereIn('sku', $skus)->get(['sku', 'value']) as $row) {
            $value = is_array($row->value) ? $row->value : (json_decode((string) $row->value, true) ?: []);
            $out[strtolower(trim((string) $row->sku))] = strtoupper((string) ($value['nr_req'] ?? ''));
        }

        return $out;
    }

    /**
     * Resolve Temu REQ/NR exactly like the temu-decrease page: use the stored nr_req
     * when present (NRL collapses to NR), otherwise default REQ when INV > 0 and NR when not.
     *
     * @param  array<string, string>  $statusMap
     */
    private function resolveTemuNrReq(array $statusMap, string $sku, float $inv): string
    {
        $nr = $statusMap[strtolower(trim($sku))] ?? '';
        if ($nr === 'REQ') {
            return 'REQ';
        }
        if ($nr === 'NR' || $nr === 'NRL') {
            return 'NR';
        }

        return $inv > 0 ? 'REQ' : 'NR';
    }

    /**
     * Build a SKU(lowercased) => REQ/NR lookup from reverb_listing_statuses.
     * Reverb stores RL/NRL under "rl_nrl" (RL = REQ, NRL = NR); older rows may
     * still use "nr_req". Defaults to REQ when not explicitly set.
     *
     * @param  array<int, string>  $skus
     * @return array<string, string>
     */
    private function buildReverbNrReqStatusLookup(array $skus): array
    {
        $out = [];
        foreach (ReverbListingStatus::whereIn('sku', $skus)->get(['sku', 'value']) as $row) {
            $value = is_array($row->value) ? $row->value : (json_decode((string) $row->value, true) ?: []);
            $rlNrl = $value['rl_nrl'] ?? null;
            if ($rlNrl === null && isset($value['nr_req'])) {
                $rlNrl = $value['nr_req'] === 'NR' ? 'NRL' : 'RL';
            }
            $out[strtolower(trim((string) $row->sku))] = ($rlNrl === 'NRL') ? 'NR' : 'REQ';
        }

        return $out;
    }

    /**
     * Explain why an actual stored SKU differs from the expected Product Master SKU.
     * Returns '' when they match exactly.
     *
     * $normalizer is the marketplace's own SKU normalization (the same one used to
     * MATCH the listing row). When the two SKUs are equal under that normalization
     * the difference is only formatting (spaces / case / PCS↔PC), not a different
     * SKU — so each marketplace reports the same way it matches.
     *
     * @param  callable|null  $normalizer  fn(string): string (defaults to ShopifySku)
     */
    private function skuDifferenceReason(string $expected, string $actual, ?callable $normalizer = null): string
    {
        if ($expected === $actual) {
            return '';
        }

        $normalizer = $normalizer ?? [ShopifySku::class, 'normalizeSkuForShopifyLookup'];

        // If they don't even match under the marketplace's own normalization, it's a genuinely different SKU.
        if (call_user_func($normalizer, $expected) !== call_user_func($normalizer, $actual)) {
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

        // They matched under the marketplace normalization; classify the remaining
        // difference. Equal once all spaces are removed => only spacing differs;
        // otherwise the marketplace normalization folded a piece-count (PCS <-> PC).
        $noSpaceExp = str_replace(' ', '', strtoupper($cleanExpected));
        $noSpaceAct = str_replace(' ', '', strtoupper($cleanActual));
        if ($noSpaceExp === $noSpaceAct) {
            if (empty($reasons)) {
                $reasons[] = 'spacing mismatch';
            }
        } else {
            $reasons[] = 'PCS/PC format';
        }

        $reasons = array_values(array_unique($reasons));
        if (empty($reasons)) {
            $reasons[] = 'formatting mismatch';
        }

        return implode(', ', $reasons);
    }
}
