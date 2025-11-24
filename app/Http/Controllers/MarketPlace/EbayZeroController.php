<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\AmazonDataView;
use App\Models\EbayDataView;
use App\Models\EbayListingStatus;
use App\Models\EbayMetric;
use App\Models\EbayGeneralReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\MarketplacePercentage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\EbayPriorityReport;
use Illuminate\Support\Facades\Log;

class EbayZeroController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function adcvrEbay(){
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay')->first();

        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;
        
        return view('market-places.adcvrEbay', [
            'ebayPercentage' => $percentage,
            'ebayAdUpdates' => $adUpdates
        ]);
    }

    public function adcvrEbayData() {
        ini_set('max_execution_time', 600);

        $normalize = fn($s) => strtoupper(trim((string)$s));

        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->map($normalize)->unique()->values()->all();

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay')->first();
        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 1;

        // external ebay metrics (apicentral) - fetch and key by normalized SKU
        $rawEbayDatasheets = DB::connection('apicentral')
            ->table('ebay_one_metrics')
            ->whereIn('sku', $skus)
            ->get();

        $ebayDatasheetsBySku = collect($rawEbayDatasheets)->mapWithKeys(function ($item) use ($normalize) {
            $skuKey = $normalize($item->sku ?? $item->SKU ?? '');
            return [$skuKey => (object) ((array) $item)];
        });

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy(fn($i) => $normalize($i->sku));
        $nrValuesRaw = EbayDataView::whereIn('sku', $skus)->pluck('value', 'sku');
        $nrValues = [];
        foreach ($nrValuesRaw as $k => $v) {
            $nrValues[$normalize($k)] = $v;
        }

        // Priority reports (keyword/SP campaigns)
        $allPriority = EbayPriorityReport::whereIn('report_range', ['L90', 'L30', 'L7'])->get();
        $campaignsByRange = [
            'L90' => $allPriority->where('report_range', 'L90')->keyBy(fn($r) => $normalize(trim(rtrim($r->campaign_name ?? $r->campaignName ?? '', '.')))),
            'L30' => $allPriority->where('report_range', 'L30')->keyBy(fn($r) => $normalize(trim(rtrim($r->campaign_name ?? $r->campaignName ?? '', '.')))),
            'L7'  => $allPriority->where('report_range', 'L7')->keyBy(fn($r) => $normalize(trim(rtrim($r->campaign_name ?? $r->campaignName ?? '', '.')))),
        ];

        // HL / Sponsored Brands like reports
        // $ebayHl = EbayGeneralReport::whereIn('report_range', ['L90', 'L30', 'L7'])
        //     ->get()
        //     ->groupBy('report_range');

        $result = [];

        $lmpData = DB::connection('repricer')
            ->table('lmp_data')
            ->whereIn('sku', $skus)
            ->where('price', '>', 0)
            ->orderBy('price', 'asc')
            ->get()
            ->groupBy('sku');

        foreach ($productMasters as $pm) {
            $sku = $normalize($pm->sku);
            $parent = $pm->parent;

            $ebaySheet = $ebayDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$sku] ?? null;
            // fallback: local EbayMetric if apicentral row missing
            $localEbayMetric = null;
            if (!$ebaySheet) {
                $localEbayMetric = EbayMetric::where('sku', $pm->sku)->first();
            }

            $matchedKwL90 = $campaignsByRange['L90'][$sku] ?? null;
            $matchedKwL30 = $campaignsByRange['L30'][$sku] ?? null;
            $matchedKwL7  = $campaignsByRange['L7'][$sku] ?? null;

            // $hlL90 = $ebayHl['L90'] ?? collect();
            // $hlL30 = $ebayHl['L30'] ?? collect();
            // $hlL7  = $ebayHl['L7']  ?? collect();

            // $matchedHlL90 = $hlL90->first(function ($item) use ($sku) {
            //     $cleanName = strtoupper(trim(preg_replace('/\.+$/', '', $item->campaignName ?? $item->campaign_name ?? '')));
            //     return (str_contains($cleanName, $sku) || $cleanName === $sku || $cleanName === $sku . ' HEAD') && strtoupper($item->campaignStatus ?? '') === 'ENABLED';
            // });
            // $matchedHlL30 = $hlL30->first(function ($item) use ($sku) {
            //     $cleanName = strtoupper(trim(preg_replace('/\.+$/', '', $item->campaignName ?? $item->campaign_name ?? '')));
            //     return (str_contains($cleanName, $sku) || $cleanName === $sku || $cleanName === $sku . ' HEAD') && strtoupper($item->campaignStatus ?? '') === 'ENABLED';
            // });
            // $matchedHlL7 = $hlL7->first(function ($item) use ($sku) {
            //     $cleanName = strtoupper(trim(preg_replace('/\.+$/', '', $item->campaignName ?? $item->campaign_name ?? '')));
            //     return (str_contains($cleanName, $sku) || $cleanName === $sku || $cleanName === $sku . ' HEAD') && strtoupper($item->campaignStatus ?? '') === 'ENABLED';
            // });

            $row = [];
            $row['parent'] = $parent;
            $row['sku'] = $pm->sku;
            $row['INV'] = $shopify->inv ?? 0;
            $row['L30'] = $shopify->quantity ?? 0;
            $row['fba'] = $pm->fba ?? null;

            // KW / SP fields (L90/L30/L7)
            // $row['kw_impr_L90'] = (int) ($matchedKwL90->impressions ?? $matchedKwL90->cpc_impressions ?? 0);
            // $row['kw_impr_L30'] = (int) ($matchedKwL30->impressions ?? $matchedKwL30->cpc_impressions ?? 0);
            // $row['kw_impr_L7']  = (int) ($matchedKwL7->impressions ?? $matchedKwL7->cpc_impressions ?? 0);

            // $row['kw_clicks_L90'] = (int) ($matchedKwL90->clicks ?? $matchedKwL90->cpc_clicks ?? 0);
            // $row['kw_clicks_L30'] = (int) ($matchedKwL30->clicks ?? $matchedKwL30->cpc_clicks ?? 0);
            // $row['kw_clicks_L7']  = (int) ($matchedKwL7->clicks ?? $matchedKwL7->cpc_clicks ?? 0);

            // $row['kw_spend_L90']  = (float) str_replace('USD ', '', ($matchedKwL90->spend ?? $matchedKwL90->cpc_ad_fees_payout_currency ?? 0));
            // $row['kw_spend_L30']  = (float) str_replace('USD ', '', ($matchedKwL30->spend ?? $matchedKwL30->cpc_ad_fees_payout_currency ?? 0));
            // $row['kw_spend_L7']   = (float) str_replace('USD ', '', ($matchedKwL7->spend ?? $matchedKwL7->cpc_ad_fees_payout_currency ?? 0));

            // $row['kw_sales_L90']  = (float) str_replace('USD ', '', ($matchedKwL90->sales ?? $matchedKwL90->cpc_sale_amount_payout_currency ?? 0));
            // $row['kw_sales_L30']  = (float) str_replace('USD ', '', ($matchedKwL30->sales ?? $matchedKwL30->cpc_sale_amount_payout_currency ?? 0));
            // $row['kw_sales_L7']   = (float) str_replace('USD ', '', ($matchedKwL7->sales ?? $matchedKwL7->cpc_sale_amount_payout_currency ?? 0));

            // $row['kw_sold_L90']  = (int) ($matchedKwL90->unitsSoldSameSku30d ?? $matchedKwL90->cpc_attributed_sales ?? 0);
            // $row['kw_sold_L30']  = (int) ($matchedKwL30->unitsSoldSameSku30d ?? $matchedKwL30->cpc_attributed_sales ?? 0);
            // $row['kw_sold_L7']   = (int) ($matchedKwL7->unitsSoldSameSku7d ?? $matchedKwL7->cpc_attributed_sales ?? 0);

            // // HL fields
            // $row['hl_impr_L90'] = (int) ($matchedHlL90->impressions ?? 0);
            // $row['hl_impr_L30'] = (int) ($matchedHlL30->impressions ?? 0);
            // $row['hl_impr_L7']  = (int) ($matchedHlL7->impressions ?? 0);

            // $row['hl_clicks_L90'] = (int) ($matchedHlL90->clicks ?? 0);
            // $row['hl_clicks_L30'] = (int) ($matchedHlL30->clicks ?? 0);
            // $row['hl_clicks_L7']  = (int) ($matchedHlL7->clicks ?? 0);

            // $row['hl_sales_L90']  = (float) ($matchedHlL90->sale_amount ?? $matchedHlL90->sales ?? 0);
            // $row['hl_sales_L30']  = (float) ($matchedHlL30->sale_amount ?? $matchedHlL30->sales ?? 0);
            // $row['hl_sales_L7']   = (float) ($matchedHlL7->sale_amount ?? $matchedHlL7->sales ?? 0);

            // // HL spend (ad fees) if present in general reports
            // $row['hl_spend_L90'] = (float) str_replace('USD ', '', ($matchedHlL90->ad_fees ?? $matchedHlL90->adFees ?? 0));
            // $row['hl_spend_L30'] = (float) str_replace('USD ', '', ($matchedHlL30->ad_fees ?? $matchedHlL30->adFees ?? 0));
            // $row['hl_spend_L7']  = (float) str_replace('USD ', '', ($matchedHlL7->ad_fees ?? $matchedHlL7->adFees ?? 0));

            // $row['hl_sold_L90']  = (int) ($matchedHlL90->unitsSold ?? 0);
            // $row['hl_sold_L30']  = (int) ($matchedHlL30->unitsSold ?? 0);
            // $row['hl_sold_L7']   = (int) ($matchedHlL7->unitsSold ?? 0);

            // totals & derived - prefer direct metrics from apicentral/local metric when available
            // Prefer explicit eBay metric columns if available (avoid using KW/HL sums)
            $row['A_L30'] = (int) (
                $ebaySheet?->ebay_l30
                ?? ($localEbayMetric ? ($localEbayMetric->ebay_l30 ?? 0) : 0)
                ?? 0
            );

            $row['total_review_count'] = $ebaySheet->total_review_count ?? 0;
            $row['average_star_rating'] = $ebaySheet->average_star_rating ?? 0;

            // price/values
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
            $lp = 0;
            if (is_array($values)) {
                foreach ($values as $k => $v) {
                    if (strtolower($k) === 'lp') { $lp = floatval($v); break; }
                }
            }
            if ($lp === 0 && isset($pm->lp)) $lp = floatval($pm->lp);
            $ship = isset($values['ship']) ? floatval($values['ship']) : (isset($pm->ship) ? floatval($pm->ship) : 0);

            $row['SHIP'] = $ship;
            $row['LP'] = $lp;

            // determine price: prefer apicentral ebay_one_metrics, fallback to local EbayMetric
            $price = 0;
            if ($ebaySheet) {
                $price = $ebaySheet->ebay_price ?? $ebaySheet->price ?? 0;
            } elseif ($localEbayMetric) {
                $price = $localEbayMetric->ebay_price ?? ($localEbayMetric->price ?? 0);
            }
            $row['price'] = $price;
            $row['PFT_percentage'] = round($price > 0 ? ((($price * $percentage) - $lp - $ship) / $price) * 100 : 0, 2);
            // Campaign meta
            $row['campaign_id'] = $matchedKwL90->campaign_id ?? $matchedKwL30->campaign_id ?? $matchedKwL7->campaign_id ?? '';
            $row['campaignName'] = $matchedKwL90->campaign_name ?? $matchedKwL30->campaign_name ?? $matchedKwL7->campaign_name ?? ($matchedHlL30->campaign_name ?? '');
            $row['campaignStatus'] = $matchedKwL90->campaignStatus ?? $matchedKwL30->campaignStatus ?? $matchedKwL7->campaignStatus ?? ($matchedHlL30->campaignStatus ?? '');
            $row['campaignBudgetAmount'] = $matchedKwL90->campaignBudgetAmount ?? $matchedKwL30->campaignBudgetAmount ?? 0;
            $row['l7_cpc'] = $matchedKwL7->costPerClick ?? 0;

            $row['spend_l90'] = (float) str_replace('USD ', '', ($matchedKwL90->spend ?? $matchedKwL90->cpc_ad_fees_payout_currency ?? 0));
            $row['spend_l30'] = (float) str_replace('USD ', '', ($matchedKwL30->spend ?? $matchedKwL30->cpc_ad_fees_payout_currency ?? 0));
            $row['spend_l7']  = (float) str_replace('USD ', '', ($matchedKwL7->spend ?? $matchedKwL7->cpc_ad_fees_payout_currency ?? 0));

            $row['ad_sales_l90'] = (float) str_replace('USD ', '', ($matchedKwL90->sales ?? $matchedKwL90->cpc_sale_amount_payout_currency ?? 0));
            $row['ad_sales_l30'] = (float) str_replace('USD ', '', ($matchedKwL30->sales ?? $matchedKwL30->cpc_sale_amount_payout_currency ?? 0));
            $row['ad_sales_l7']  = (float) str_replace('USD ', '', ($matchedKwL7->sales ?? $matchedKwL7->cpc_sale_amount_payout_currency ?? 0));

            $row['clicks_L90'] = (int) ($matchedKwL90->cpc_clicks ?? 0);
            $row['clicks_L30'] = (int) ($matchedKwL30->cpc_clicks ?? 0);
            $row['clicks_L7']  = (int) ($matchedKwL7->cpc_clicks ?? 0);

            // A_L90/A_L7 - prefer direct metrics from apicentral/local metric when available
            $row['A_L90'] = (int) (
                $ebaySheet?->ebay_l90
                ?? ($localEbayMetric ? ($localEbayMetric->ebay_l90 ?? 0) : 0)
                ?? 0
            );
            $row['A_L7'] = (int) (
                $ebaySheet?->ebay_l7
                ?? ($localEbayMetric ? ($localEbayMetric->ebay_l7 ?? 0) : 0)
                ?? 0
            );

            // ACOS calculations
            $sales90 = $row['ad_sales_l90'] ?? 0; $spend90 = $row['spend_l90'] ?? 0;
            $sales30 = $row['ad_sales_l30'] ?? 0; $spend30 = $row['spend_l30'] ?? 0;
            $sales7  = $row['ad_sales_l7'] ?? 0;  $spend7  = $row['spend_l7'] ?? 0;

            $row['acos_L90'] = $sales90 > 0 ? round(($spend90 / $sales90) * 100, 2) : (($spend90 > 0 && $sales90 == 0) ? 100 : 0);
            $row['acos_L30'] = $sales30 > 0 ? round(($spend30 / $sales30) * 100, 2) : (($spend30 > 0 && $sales30 == 0) ? 100 : 0);
            $row['acos_L7']  = $sales7  > 0 ? round(($spend7  / $sales7)  * 100, 2) : (($spend7  > 0 && $sales7  == 0) ? 100 : 0);

            // CVR
            $row['cvr_l90'] = ($row['clicks_L90'] == 0) ? NULL : number_format((($row['A_L90'] ?? 0) / $row['clicks_L90']) * 100, 2);
            $row['cvr_l30'] = ($row['clicks_L30'] == 0) ? NULL : number_format((($row['A_L30'] ?? 0) / $row['clicks_L30']) * 100, 2);
            $row['cvr_l7']  = ($row['clicks_L7']  == 0) ? NULL : number_format((($row['A_L7'] ?? 0) / $row['clicks_L7']) * 100, 2);

            // NRL/NRA/FBA/TPFT from EbayDataView
            $row['NRL']  = '';
            $row['NRA'] = '';
            $row['FBA'] = '';
            $row['TPFT'] = null;
                if (isset($nrValues[$sku])) {
                    $raw = $nrValues[$sku];
                if (!is_array($raw)) $raw = json_decode($raw, true) ?: [];
                if (is_array($raw)) {
                    $row['NRL']  = $raw['NRL'] ?? $raw['NR'] ?? '';
                    $row['NRA']  = $raw['NRA'] ?? '';
                    $row['FBA']  = $raw['FBA'] ?? '';
                    $row['TPFT'] = $raw['TPFT'] ?? null;
                }
            }

            $row['ebay_price'] = $price;
            $row['ebay_pft'] = $price > 0 ? ((($price * $percentage) - $lp - $ship) / $price) : 0;
            $row['ebay_roi'] = ($lp > 0 && $price > 0) ? ((($price * 0.70) - $lp - $ship) / $lp) : 0;

            // DIL fields (decimal form)
            $row['DIL %'] = ($row['INV'] > 0) ? round(($row['L30'] ?? 0) / $row['INV'], 4) : 0;
            $row['A DIL %'] = ($row['INV'] > 0) ? round(($row['A_L30'] ?? 0) / $row['INV'], 4) : 0;

            $prices = isset($lmpData[$sku])
                ? $lmpData[$sku]->pluck('price')->toArray()
                : [];

            for ($i = 0; $i <= 11; $i++) {
                if ($i == 0) {
                    $row['lmp'] = $prices[$i] ?? 0;
                } else {
                    $row['lmp_' . $i] = $prices[$i] ?? 0;
                }
            }

            $result[] = (object) $row;
        }

        return response()->json([
            'message' => 'Ebay ADCVR data fetched',
            'data' => $result,
            'status' => 200,
        ]);
    }

    public function updateEbayPrice(Request $request) {
        try {
            $validated = $request->validate([
            'sku' => 'required|exists:apicentral.ebay_one_metrics,sku',
            'price' => 'required|numeric',
        ]);

        $ebayData = DB::connection('apicentral')
            ->table('ebay_one_metrics')
            ->where('sku', $validated['sku'])
            ->first();

        if (!$ebayData) {
            return response()->json([
                'status' => 'error',
                'message' => 'SKU not found in ebay_one_metrics.',
            ], 404);
        }

        DB::connection('apicentral')
            ->table('ebay_one_metrics')
            ->where('sku', $validated['sku'])
            ->update([
                'ebay_price' => $validated['price'],
            ]);

        $updatedData = DB::connection('apicentral')
            ->table('ebay_one_metrics')
            ->where('sku', $validated['sku'])
            ->first();

        return response()->json([
            'status' => 'success',
            'message' => 'Ebay price and metrics updated successfully.',
            'data' => $updatedData,
        ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function ebayZero(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('ebay_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'eBay')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        return view('market-places.ebayZeroView', [
            'mode' => $mode,
            'demo' => $demo,
            'ebayPercentage' => $percentage
        ]);
    }

    public function getVieweBayZeroData(Request $request)
    {
        // 1. Fetch all ProductMaster rows (base)
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        // Fetch data from the Google Sheet using the ApiController method
        // Prepare SKU list for related models
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        // Fetch related data
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $ebayMetrics = EbayMetric::whereIn('sku', $skus)->get()->keyBy('sku');
        // Fetch all EbayDataView rows for these SKUs
        $ebayDataViews = EbayDataView::whereIn('sku', $skus)->get()->keyBy('sku');

        // Build the result using ProductMaster as the base
        $processedData = [];
        foreach ($productMasters as $pm) {
            $sku = $pm->sku;
            $parentSku = $pm->parent;
            $imagePath = null;

            // Try to get image from Shopify first
            $shopify = $shopifyData[$sku] ?? null;
            if ($shopify && !empty($shopify->image_src)) {
                $imagePath = $shopify->image_src;
            } else {
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                if (isset($values['image_path'])) {
                    $imagePath = $values['image_path'];
                } elseif (isset($pm->image_path)) {
                    $imagePath = $pm->image_path;
                }
            }
            $buyerLink = $item->{'B Link'} ?? null;
            $sellerLink = $item->{'S Link'} ?? null;
            // Create base item object
            $item = (object) [
                'Parent' => $parentSku,
                '(Child) sku' => $sku,
                'B Link' => null,
                'S Link' => null,
                'INV' => $shopify ? $shopify->inv : 0,
                'L30' => $shopify ? $shopify->quantity : 0,
                'eBay L30' => 0,
                'eBay L60' => 0,
                'eBay Price' => 0,
                'OV CLICKS L30' => 0,
                'views' => $ebayMetric->views ?? 0,
                'image' => $imagePath,
                'A_Z_Reason' => '',
                'A_Z_ActionRequired' => '',
                'A_Z_ActionTaken' => '',
                'NR' => 'REQ',
                'B Link' => (filter_var($buyerLink, FILTER_VALIDATE_URL)) ? $buyerLink : null,
                'S Link' => (filter_var($sellerLink, FILTER_VALIDATE_URL)) ? $sellerLink : null,
            ];

            // eBay metrics
            if ($ebayMetrics->has($sku)) {
                $ebayMetric = $ebayMetrics[$sku];
                $item->{'eBay L30'} = $ebayMetric->ebay_l30;
                $item->{'eBay L60'} = $ebayMetric->ebay_l60;
                $item->{'eBay Price'} = $ebayMetric->ebay_price;
                $item->{'views'} = $ebayMetric->views ?? 0;
                $inv = $shopify->inv ?? 0;
                $eBayL30 = $item->{'eBay L30'} ?? 0;
                $item->{'E Dil%'} = ($inv > 0) ? round($eBayL30 / $inv, 2) : 0;
            }

            // EbayDataView
            $dataView = $ebayDataViews->get($sku);
            $value = $dataView ? $dataView->value : [];
            $item->{'A_Z_Reason'} = $value['A_Z_Reason'] ?? '';
            $item->{'A_Z_ActionRequired'} = $value['A_Z_ActionRequired'] ?? '';
            $item->{'A_Z_ActionTaken'} = $value['A_Z_ActionTaken'] ?? '';
            $item->{'NR'} = $value['NR'] ?? 'REQ';

            $processedData[] = $item;
        }

        // Filter: Only show rows with 0 views
        $filteredResults = array_filter($processedData, function ($item) {
            $childSku = $item->{'(Child) sku'} ?? '';
            $inv = $item->INV ?? 0;
            $views = $item->views ?? 1;
            return stripos($childSku, 'PARENT') === false && $inv > 0 && intval($views) === 0;
        });

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => array_values($filteredResults),
            'status' => 200
        ]);

    }

    public function updateReasonAction(Request $request)
    {
        $sku = $request->input('sku');
        $reason = $request->input('reason');
        $actionRequired = $request->input('action_required');
        $actionTaken = $request->input('action_taken');

        if (!$sku) {
            return response()->json([
                'status' => 400,
                'message' => 'SKU is required.'
            ], 400);
        }

        $row = EbayDataView::firstOrCreate(['sku' => $sku]);
        $value = $row->value ?? [];
        $value['A_Z_Reason'] = $reason;
        $value['A_Z_ActionRequired'] = $actionRequired;
        $value['A_Z_ActionTaken'] = $actionTaken;
        $row->value = $value;
        $row->save();

        return response()->json([
            'status' => 200,
            'message' => 'Reason and actions updated successfully.'
        ]);
    }

    public function getZeroViewCount()
    {
        // Use the same logic as getVieweBayZeroData: INV > 0, views == 0, not parent SKU
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->unique()->toArray();
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $ebayMetrics = EbayMetric::whereIn('sku', $skus)->get()->keyBy('sku');

        $zeroCount = 0;
        foreach ($productMasters as $pm) {
            $sku = $pm->sku;
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = $shopifyData[$sku]->inv ?? 0;
            $views = $ebayMetrics[$sku]->views ?? 0;
            if (!$isParent && $inv > 0 && intval($views) === 0) {
                $zeroCount++;
            }
        }
        return $zeroCount;
    }

    public function getNrReqCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = EbayDataView::whereIn('sku', $skus)->get()->keyBy('sku');

        $reqCount = 0;
        $nrCount = 0;
        $listedCount = 0;
        $pendingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $inv = $shopifyData[$sku]->inv ?? 0;
            $isParent = stripos($sku, 'PARENT') !== false;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // NR/REQ logic
            $nrReq = $status['NR'] ?? (floatval($inv) > 0 ? 'REQ' : 'NR');
            if ($nrReq === 'REQ') {
                $reqCount++;
            } elseif ($nrReq === 'NR') {
                $nrCount++;
            }

            // Listed/Pending logic
            $listed = $status['listed'] ?? (floatval($inv) > 0 ? 'Pending' : 'Listed');
            if ($listed === 'Listed') {
                $listedCount++;
            } elseif ($listed === 'Pending') {
                $pendingCount++;
            }
        }

        return [
            'NR'  => $nrCount,
            'REQ' => $reqCount,
            'Listed' => $listedCount,
            'Pending' => $pendingCount,
        ];
    }

    // public function getLivePendingAndZeroViewCounts()
    // {
    //     $productMasters = ProductMaster::whereNull('deleted_at')->get();
    //     $skus = $productMasters->pluck('sku')->unique()->toArray();

    //     $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
    //     $ebayDataViews = EbayListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');
    //     $ebayMetrics = EbayMetric::whereIn('sku', $skus)->get()->keyBy('sku'); 

    //     $listedCount = 0;
    //     $zeroInvOfListed = 0;
    //     $liveCount = 0;
    //     $zeroViewCount = 0;

    //     foreach ($productMasters as $item) {
    //         $sku = trim($item->sku);
    //         $inv = $shopifyData[$sku]->inv ?? 0;
    //         $isParent = stripos($sku, 'PARENT') !== false;
    //         if ($isParent) continue;

    //         $status = $ebayDataViews[$sku]->value ?? null;
    //         if (is_string($status)) {
    //             $status = json_decode($status, true);
    //         }
    //         $listed = $status['listed'] ?? (floatval($inv) > 0 ? 'Pending' : 'Listed');
    //         $live = $status['live'] ?? null;

    //         // Listed count 
    //         if ($listed === 'Listed') {
    //             $listedCount++;
    //             if (floatval($inv) <= 0) {
    //                 $zeroInvOfListed++;
    //             }
    //         }

    //         // Live count
    //         if ($live === 'Live') {
    //             $liveCount++;
    //         }

    //         // Zero view: INV > 0, views == 0 (from ebay_metric table), not parent SKU (NR ignored)
    //         $views = $ebayMetrics[$sku]->views ?? null;
    //         // if (floatval($inv) > 0 && $views !== null && intval($views) === 0) {
    //         //     $zeroViewCount++;
    //         // }
    //         if ($inv > 0) {
    //             if ($views === null) {
    //             } elseif (intval($views) === 0) {
    //                 $zeroViewCount++;
    //             }
    //         }
    //     }

    //     // live pending = listed - 0-inv of listed - live
    //     $livePending = $listedCount - $zeroInvOfListed - $liveCount;

    //     return [
    //         'live_pending' => $livePending,
    //         'zero_view' => $zeroViewCount,
    //     ];
    // }


    public function getLivePendingAndZeroViewCounts()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();

        // Normalize SKUs (avoid case/space mismatch)
        $skus = $productMasters->pluck('sku')->map(fn($s) => strtoupper(trim($s)))->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $ebayListingStatus = EbayListingStatus::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $ebayDataViews = EbayDataView::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $ebayMetrics = EbayMetric::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $listedCount = 0;
        $zeroInvOfListed = 0;
        $liveCount = 0;
        $zeroViewCount = 0;

        foreach ($productMasters as $item) {
            $sku = strtoupper(trim($item->sku));
            $inv = $shopifyData[$sku]->inv ?? 0;

            // Skip parent SKUs
            if (stripos($sku, 'PARENT') !== false) continue;

            // --- eBay Listing Status ---
            $status = $ebayListingStatus[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // $listed = $status['listed'] ?? (floatval($inv) > 0 ? 'Pending' : 'Listed');
            $listed = $status['listed'] ?? null;

            // --- Amazon Live Status ---
            $dataView = $ebayDataViews[$sku]->value ?? null;
            if (is_string($dataView)) {
                $dataView = json_decode($dataView, true);
            }
            // $live = ($dataView['Live'] ?? false) === true ? 'Live' : null;
            $live = (!empty($dataView['Live']) && $dataView['Live'] === true) ? 'Live' : null;


            // --- Listed count ---
            if ($listed === 'Listed') {
                $listedCount++;
                if (floatval($inv) <= 0) {
                    $zeroInvOfListed++;
                }
            }

            // --- Live count ---
            if ($live === 'Live') {
                $liveCount++;
            }

            // --- Views / Zero-View logic ---
            $metricRecord = $ebayMetrics[$sku] ?? null;
            $views = null;

            if ($metricRecord) {
                // Direct field
                if (!empty($metricRecord->views) || $metricRecord->views === "0" || $metricRecord->views === 0) {
                    $views = (int)$metricRecord->views;
                }
                // Or inside JSON column `value`
                elseif (!empty($metricRecord->value)) {
                    $metricData = json_decode($metricRecord->value, true);
                    if (isset($metricData['views'])) {
                        $views = (int)$metricData['views'];
                    }
                }
            }

            // Normalize $inv to numeric
            $inv = floatval($inv);

            $hasNR = !empty($dataView['NR']) && strtoupper($dataView['NR']) === 'NR';

            // Count as zero-view if views are exactly 0 and inv > 0
            if ($inv > 0 && $views === 0  && !$hasNR) {
                $zeroViewCount++;
            }

        }

        $livePending = $listedCount - $liveCount;

        return [
            'live_pending' => $livePending,
            'zero_view' => $zeroViewCount,
        ];
    }
}