<?php

namespace App\Http\Controllers\MarketPlace;

use App\Models\ShopifySku;
use Illuminate\Http\Request;
use App\Models\ProductMaster;
use App\Models\AmazonDataView;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Services\AmazonSpApiService;
use App\Models\MarketplacePercentage;
use Illuminate\Support\Facades\Cache;
use App\Models\JungleScoutProductData;
use App\Http\Controllers\ApiController;
use App\Jobs\UpdateAmazonSPriceJob;
use App\Models\AmazonDatasheet;
use App\Models\ADVMastersData;
use App\Models\AmazonSbCampaignReport;
use App\Models\AmazonSpCampaignReport;
use App\Models\ChannelMaster;
use Illuminate\Support\Facades\DB;
use App\Services\AmazonDataService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Models\AmazonFbmManual;
use App\Models\AmazonListingStatus;

class OverallAmazonController extends Controller
{
    protected $apiController;
    protected $amazonDataService;

    public function __construct(ApiController $apiController, AmazonDataService $amazonDataService)
    {
        $this->apiController = $apiController;
        $this->amazonDataService = $amazonDataService;
    }

    public function updatePrice(Request $request)
    {
        $sku = $request["sku"];
        $price = $request["price"];

        $price = app(AmazonSpApiService::class)->updateAmazonPriceUS($sku, $price);

        return response()->json(['status' => 200, 'data' => $price]);
    }

    public function adcvrAmazon(){
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();

        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;
        
        return view('market-places.adcvrAmazon', [
            'amazonPercentage' => $percentage,
            'amazonAdUpdates' => $adUpdates
        ]);
    }

    public function adcvrAmazonData() {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();

        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 1;

        $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $amazonSpCampaignReportsL90 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L90')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'NOT LIKE', '%' . $sku . '% PT')
                    ->orWhere('campaignName', 'NOT LIKE', '%' . $sku . '% pt');
                }
            })
            ->get();

        $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'NOT LIKE', '%' . $sku . '% PT')
                    ->orWhere('campaignName', 'NOT LIKE', '%' . $sku . '% pt');
                }
            })
            ->get();

        $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'NOT LIKE', '%' . $sku . '% PT')
                    ->orWhere('campaignName', 'NOT LIKE', '%' . $sku . '% pt');
                }
            })
            ->get();

        $amazonHlL90 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L90')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
            })
            ->get();

        $amazonHlL30 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
            })
            ->get();

        $amazonHlL7 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
            })
            ->get();
            
        $result = [];
        $parentHlSpendData = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaignL90 = $amazonSpCampaignReportsL90->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            // $matchedCampaignPtL90 = $amazonSpCampaignReportsL90->first(function ($item) use ($sku) {
            //     $cleanName = strtoupper(trim($item->campaignName));
            //     return (str_ends_with($cleanName, $sku . ' PT') || str_ends_with($cleanName, $sku . ' PT.'))
            //         && strtoupper($item->campaignStatus) === 'ENABLED';
            // });

            // $matchedCampaignPtL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
            //     $cleanName = strtoupper(trim($item->campaignName));
            //     return (str_ends_with($cleanName, $sku . ' PT') || str_ends_with($cleanName, $sku . ' PT.'))
            //         && strtoupper($item->campaignStatus) === 'ENABLED';
            // });

            // $matchedCampaignPtL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
            //     $cleanName = strtoupper(trim($item->campaignName));
            //     return (str_ends_with($cleanName, $sku . ' PT') || str_ends_with($cleanName, $sku . ' PT.'))
            //         && strtoupper($item->campaignStatus) === 'ENABLED';
            // });

            // $matchedCampaignHlL90 = $amazonHlL90->first(function ($item) use ($sku) {
            //     $cleanName = strtoupper(trim($item->campaignName));
            //     return in_array($cleanName, [$sku, $sku . ' HEAD']) && strtoupper($item->campaignStatus) === 'ENABLED';
            // });

            // $matchedCampaignHlL30 = $amazonHlL30->first(function ($item) use ($sku) {
            //     $cleanName = strtoupper(trim($item->campaignName));
            //     return in_array($cleanName, [$sku, $sku . ' HEAD']) && strtoupper($item->campaignStatus) === 'ENABLED';
            // });
            // $matchedCampaignHlL7 = $amazonHlL7->first(function ($item) use ($sku) {
            //     $cleanName = strtoupper(trim($item->campaignName));
            //     return in_array($cleanName, [$sku, $sku . ' HEAD']) && strtoupper($item->campaignStatus) === 'ENABLED';
            // });

            // if (str_starts_with($sku, 'PARENT')) {
            //     $childCount = $parentSkuCounts[$parent] ?? 0;
            //     $parentHlSpendData[$parent] = [
            //         'total_L90' => $matchedCampaignHlL90->cost ?? 0,
            //         'total_L30' => $matchedCampaignHlL30->cost ?? 0,
            //         'total_L7'  => $matchedCampaignHlL7->cost ?? 0,
            //         'total_L90_sales' => $matchedCampaignHlL90->sales ?? 0,
            //         'total_L30_sales' => $matchedCampaignHlL30->sales ?? 0,
            //         'total_L7_sales'  => $matchedCampaignHlL7->sales ?? 0,
            //         'total_L90_sold'  => $matchedCampaignHlL90->unitsSold ?? 0,
            //         'total_L30_sold'  => $matchedCampaignHlL30->unitsSold ?? 0,
            //         'total_L7_sold'   => $matchedCampaignHlL7->unitsSold ?? 0,
            //         'total_L90_impr'  => $matchedCampaignHlL90->impressions ?? 0,
            //         'total_L30_impr'  => $matchedCampaignHlL30->impressions ?? 0,
            //         'total_L7_impr'   => $matchedCampaignHlL7->impressions ?? 0,
            //         'total_L90_clicks'=> $matchedCampaignHlL90->clicks ?? 0,
            //         'total_L30_clicks'=> $matchedCampaignHlL30->clicks ?? 0,
            //         'total_L7_clicks' => $matchedCampaignHlL7->clicks ?? 0,
            //         'childCount'=> $childCount,
            //     ];
            // }

            // $row = [];

            // // --- KW ---
            // $row['kw_impr_L90'] = $matchedCampaignL90->impressions ?? 0;
            // $row['kw_impr_L30'] = $matchedCampaignL30->impressions ?? 0;
            // $row['kw_impr_L7']  = $matchedCampaignKwL7->impressions ?? 0;
            // $row['kw_clicks_L90'] = $matchedCampaignL90->clicks ?? 0;
            // $row['kw_clicks_L30'] = $matchedCampaignL30->clicks ?? 0;
            // $row['kw_clicks_L7']  = $matchedCampaignKwL7->clicks ?? 0;
            // $row['kw_spend_L90']  = $matchedCampaignL90->spend ?? 0;
            // $row['kw_spend_L30']  = $matchedCampaignL30->spend ?? 0;
            // $row['kw_spend_L7']   = $matchedCampaignKwL7->spend ?? 0;
            // $row['kw_sales_L90']  = $matchedCampaignL90->sales30d ?? 0;
            // $row['kw_sales_L30']  = $matchedCampaignL30->sales30d ?? 0;
            // $row['kw_sales_L7']   = $matchedCampaignKwL7->sales7d ?? 0;
            // $row['kw_sold_L90']  = $matchedCampaignL90->unitsSoldSameSku30d ?? 0;
            // $row['kw_sold_L30']  = $matchedCampaignL30->unitsSoldSameSku30d ?? 0;
            // $row['kw_sold_L7']   = $matchedCampaignKwL7->unitsSoldSameSku7d ?? 0;

            // // --- PT ---
            // $row['pt_impr_L90'] = $matchedCampaignPtL90->impressions ?? 0;
            // $row['pt_impr_L30'] = $matchedCampaignPtL30->impressions ?? 0;
            // $row['pt_impr_L7']  = $matchedCampaignPtL7->impressions ?? 0;
            // $row['pt_clicks_L90'] = $matchedCampaignPtL90->clicks ?? 0;
            // $row['pt_clicks_L30'] = $matchedCampaignPtL30->clicks ?? 0;
            // $row['pt_clicks_L7']  = $matchedCampaignPtL7->clicks ?? 0;
            // $row['pt_spend_L90']  = $matchedCampaignPtL90->spend ?? 0;
            // $row['pt_spend_L30']  = $matchedCampaignPtL30->spend ?? 0;
            // $row['pt_spend_L7']   = $matchedCampaignPtL7->spend ?? 0;
            // $row['pt_sales_L90']  = $matchedCampaignPtL90->sales30d ?? 0;
            // $row['pt_sales_L30']  = $matchedCampaignPtL30->sales30d ?? 0;
            // $row['pt_sales_L7']   = $matchedCampaignPtL7->sales7d ?? 0;
            // $row['pt_sold_L90']  = $matchedCampaignPtL90->unitsSoldSameSku30d ?? 0;
            // $row['pt_sold_L30']  = $matchedCampaignPtL30->unitsSoldSameSku30d ?? 0;
            // $row['pt_sold_L7']   = $matchedCampaignPtL7->unitsSoldSameSku7d ?? 0;

            // // --- HL  ---
            // $row['hl_impr_L90'] = $matchedCampaignHlL90->impressions ?? 0;
            // $row['hl_impr_L30'] = $matchedCampaignHlL30->impressions ?? 0;
            // $row['hl_impr_L7']  = $matchedCampaignHlL7->impressions ?? 0;
            // $row['hl_clicks_L90'] = $matchedCampaignHlL90->clicks ?? 0;
            // $row['hl_clicks_L30'] = $matchedCampaignHlL30->clicks ?? 0;
            // $row['hl_clicks_L7']  = $matchedCampaignHlL7->clicks ?? 0;
            // $row['hl_campaign_L90'] = $matchedCampaignHlL90->campaignName ?? null;
            // $row['hl_campaign_L30'] = $matchedCampaignHlL30->campaignName ?? null;
            // $row['hl_campaign_L7']  = $matchedCampaignHlL7->campaignName ?? null;
            // $row['hl_sales_L90']  = $matchedCampaignHlL90->sales ?? 0;
            // $row['hl_sales_L30']  = $matchedCampaignHlL30->sales ?? 0;
            // $row['hl_sales_L7']   = $matchedCampaignHlL7->sales ?? 0;
            // $row['hl_sold_L90']  = $matchedCampaignHlL90->unitsSold ?? 0;
            // $row['hl_sold_L30']  = $matchedCampaignHlL30->unitsSold ?? 0;
            // $row['hl_sold_L7']   = $matchedCampaignHlL7->unitsSold ?? 0;

            // if (str_starts_with($sku, 'PARENT')) {
            //     $row['hl_spend_L90'] = $matchedCampaignHlL90->cost ?? 0;
            //     $row['hl_spend_L30'] = $matchedCampaignHlL30->cost ?? 0;
            //     $row['hl_spend_L7']  = $matchedCampaignHlL7->cost ?? 0;
            //     $row['hl_sales_L90']  = $matchedCampaignHlL90->sales ?? 0;
            //     $row['hl_sales_L30']  = $matchedCampaignHlL30->sales ?? 0;
            //     $row['hl_sales_L7']   = $matchedCampaignHlL7->sales ?? 0;
            //     $row['hl_sold_L90']  = $matchedCampaignHlL90->unitsSold ?? 0;
            //     $row['hl_sold_L30']  = $matchedCampaignHlL30->unitsSold ?? 0;
            //     $row['hl_sold_L7']   = $matchedCampaignHlL7->unitsSold ?? 0;
            //     $row['hl_impr_L90'] = $matchedCampaignHlL90->impressions ?? 0;
            //     $row['hl_impr_L30'] = $matchedCampaignHlL30->impressions ?? 0;
            //     $row['hl_impr_L7']  = $matchedCampaignHlL7->impressions ?? 0;
            //     $row['hl_clicks_L90'] = $matchedCampaignHlL90->clicks ?? 0;
            //     $row['hl_clicks_L30'] = $matchedCampaignHlL30->clicks ?? 0;
            //     $row['hl_clicks_L7']  = $matchedCampaignHlL7->clicks ?? 0;
            // } 
            // elseif (isset($parentHlSpendData[$parent]) && $parentHlSpendData[$parent]['childCount'] > 0) {
            //     $row['hl_spend_L90'] = $parentHlSpendData[$parent]['total_L90'] / $parentHlSpendData[$parent]['childCount'];
            //     $row['hl_spend_L30'] = $parentHlSpendData[$parent]['total_L30'] / $parentHlSpendData[$parent]['childCount'];
            //     $row['hl_spend_L7']  = $parentHlSpendData[$parent]['total_L7'] / $parentHlSpendData[$parent]['childCount'];
            //     $row['hl_sales_L90']  = $parentHlSpendData[$parent]['total_L90_sales'] / $parentHlSpendData[$parent]['childCount'];
            //     $row['hl_sales_L30']  = $parentHlSpendData[$parent]['total_L30_sales'] / $parentHlSpendData[$parent]['childCount'];
            //     $row['hl_sales_L7']   = $parentHlSpendData[$parent]['total_L7_sales'] / $parentHlSpendData[$parent]['childCount'];
            //     $row['hl_sold_L90']  = $parentHlSpendData[$parent]['total_L90_sold'] / $parentHlSpendData[$parent]['childCount'];
            //     $row['hl_sold_L30']  = $parentHlSpendData[$parent]['total_L30_sold'] / $parentHlSpendData[$parent]['childCount'];
            //     $row['hl_sold_L7']   = $parentHlSpendData[$parent]['total_L7_sold'] / $parentHlSpendData[$parent]['childCount'];
            //     $row['hl_impr_L90'] = $parentHlSpendData[$parent]['total_L90_impr'] / $parentHlSpendData[$parent]['childCount'];
            //     $row['hl_impr_L30'] = $parentHlSpendData[$parent]['total_L30_impr'] / $parentHlSpendData[$parent]['childCount'];
            //     $row['hl_impr_L7']  = $parentHlSpendData[$parent]['total_L7_impr'] / $parentHlSpendData[$parent]['childCount'];
            //     $row['hl_clicks_L90'] = $parentHlSpendData[$parent]['total_L90_clicks'] / $parentHlSpendData[$parent]['childCount'];
            //     $row['hl_clicks_L30'] = $parentHlSpendData[$parent]['total_L30_clicks'] / $parentHlSpendData[$parent]['childCount'];
            //     $row['hl_clicks_L7']  = $parentHlSpendData[$parent]['total_L7_clicks'] / $parentHlSpendData[$parent]['childCount'];
            // } else {
            //     $row['hl_spend_L90'] = 0;
            //     $row['hl_spend_L30'] = 0;
            //     $row['hl_spend_L7']  = 0;
            //     $row['hl_sales_L90'] = 0;
            //     $row['hl_sales_L30'] = 0;
            //     $row['hl_sales_L7']  = 0;
            //     $row['hl_sold_L90']  = 0;
            //     $row['hl_sold_L30']  = 0;
            //     $row['hl_sold_L7']   = 0;
            //     $row['hl_impr_L90'] = 0;
            //     $row['hl_impr_L30'] = 0;
            //     $row['hl_impr_L7']  = 0;
            //     $row['hl_clicks_L90'] = 0;
            //     $row['hl_clicks_L30'] = 0;
            //     $row['hl_clicks_L7']  = 0;
            // }

            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['fba']    = $pm->fba ?? null;
            $row['A_L30']  = $amazonSheet->units_ordered_l30 ?? 0;
            $row['A_L7']  = $amazonSheet->units_ordered_l7 ?? 0;
            $row['A_L90']  = $amazonSheet->units_ordered_l90 ?? 0;
            // $row['A_L90'] = $row['kw_sold_L90'] + $row['pt_sold_L90'] + $row['hl_sold_L90'];
            // $row['A_L30'] = $row['kw_sold_L30'] + $row['pt_sold_L30'] + $row['hl_sold_L30'];
            // $row['A_L7'] = $row['kw_sold_L7'] + $row['pt_sold_L7'] + $row['hl_sold_L7'];
            $row['total_review_count']  = $amazonSheet->total_review_count ?? 0;
            $row['average_star_rating']  = $amazonSheet->average_star_rating ?? 0;
            $row['campaign_id'] = $matchedCampaignL90->campaign_id ??  '';
            $row['campaignName'] = $matchedCampaignL90->campaignName ?? '';
            $row['campaignStatus'] = $matchedCampaignL90->campaignStatus ?? '';
            $row['campaignBudgetAmount'] = $matchedCampaignL90->campaignBudgetAmount ?? 0;
            $row['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
            $row['spend_l90'] = $matchedCampaignL90->spend ?? 0;
            $row['ad_sales_l90'] = $matchedCampaignL90->sales30d ?? 0;
            $row['spend_l30'] = $matchedCampaignL30->spend ?? 0;
            $row['ad_sales_l30'] = $matchedCampaignL30->sales30d ?? 0;
            $row['spend_l7'] = $matchedCampaignL7->spend ?? 0;
            $row['ad_sales_l7'] = $matchedCampaignL7->sales30d ?? 0;
            // $row['spend_l90'] = $row['kw_spend_L90'] + $row['pt_spend_L90'] + $row['hl_spend_L90'];
            // $row['ad_sales_l90'] = $row['kw_sales_L90'] + $row['pt_sales_L90'] + $row['hl_sales_L90'];
            // $row['spend_l30'] = $row['kw_spend_L30'] + $row['pt_spend_L30'] + $row['hl_spend_L30'];
            // $row['ad_sales_l30'] = $row['kw_sales_L30'] + $row['pt_sales_L30'] + $row['hl_sales_L30'];
            // $row['spend_l7'] = $row['kw_spend_L7'] + $row['pt_spend_L7'] + $row['hl_spend_L7'];
            // $row['ad_sales_l7'] = $row['kw_sales_L7'] + $row['pt_sales_L7'] + $row['hl_sales_L7'];

            if ($amazonSheet) {
                $row['A_L30'] = $amazonSheet->units_ordered_l30;
                // $row['A_L90']  = $amazonSheet->units_ordered_l90;
                $row['Sess30'] = $amazonSheet->sessions_l30;
                $row['price'] = $amazonSheet->price;
                $row['price_lmpa'] = $amazonSheet->price_lmpa;
                $row['sessions_l60'] = $amazonSheet->sessions_l60;
                $row['units_ordered_l60'] = $amazonSheet->units_ordered_l60;
            }

            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = 0;
            foreach ($values as $k => $v) {
                if (strtolower($k) === 'lp') {
                    $lp = floatval($v);
                    break;
                }
            }
            if ($lp === 0 && isset($pm->lp)) {
                $lp = floatval($pm->lp);
            }
            $ship = isset($values['ship']) ? floatval($values['ship']) : (isset($pm->ship) ? floatval($pm->ship) : 0);

            $row['SHIP'] = $ship;
            $row['LP'] = $lp;
            
            $price = isset($row['price']) ? floatval($row['price']) : 0;
            
            $row['PFT_percentage'] = round($price > 0 ? ((($price * $percentage) - $lp - $ship) / $price) : 0, 2) * 100;

            $sales90 = $matchedCampaignL90->sales30d ?? 0;
            $spend90 = $matchedCampaignL90->spend ?? 0;

            $sales30 = $matchedCampaignL30->sales30d ?? 0;
            $spend30 = $matchedCampaignL30->spend ?? 0;

            $sales7 = $matchedCampaignL7->sales30d ?? 0;
            $spend7 = $matchedCampaignL7->spend ?? 0;

            if ($sales90 > 0) {
                $row['acos_L90'] = round(($spend90 / $sales90) * 100, 2);
            } elseif ($spend90 > 0 && $sales90 == 0) {
                $row['acos_L90'] = 100;
            } else {
                $row['acos_L90'] = 0;
            }

            if ($sales30 > 0) {
                $row['acos_L30'] = round(($spend30 / $sales30) * 100, 2);
            } elseif ($spend30 > 0 && $sales30 == 0) {
                $row['acos_L30'] = 100;
            } else {
                $row['acos_L30'] = 0;
            }

            if ($sales7 > 0) {
                $row['acos_L7'] = round(($spend7 / $sales7) * 100, 2);
            } elseif ($spend7 > 0 && $sales7 == 0) {
                $row['acos_L7'] = 100;
            } else {
                $row['acos_L7'] = 0;
            }

            $row['clicks_L90'] = $matchedCampaignL90->clicks ?? 0;
            $row['clicks_L30'] = $matchedCampaignL30->clicks ?? 0;
            $row['clicks_L7'] = $matchedCampaignL7->clicks ?? 0;
            // $row['clicks_L90'] = $row['kw_clicks_L90'] + $row['pt_clicks_L90'] + $row['hl_clicks_L90'];
            // $row['clicks_L30'] = $row['kw_clicks_L30'] + $row['pt_clicks_L30'] + $row['hl_clicks_L30'];
            // $row['clicks_L7'] = $row['kw_clicks_L7'] + $row['pt_clicks_L7'] + $row['hl_clicks_L7'];

            $row['cvr_l90'] = $row['clicks_L90'] == 0 ? NULL : number_format(($row['A_L90'] / $row['clicks_L90']) * 100, 2);
            $row['cvr_l30'] = $row['clicks_L30'] == 0 ? NULL : number_format(($row['A_L30'] / $row['clicks_L30']) * 100, 2);
            $row['cvr_l7'] = $row['clicks_L7'] == 0 ? NULL : number_format(($row['A_L7'] / $row['clicks_L7']) * 100, 2);

            $row['NRL']  = '';
            $row['NRA'] = '';
            $row['FBA'] = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NRL']  = $raw['NRL'] ?? null;
                    $row['NRA'] = $raw['NRA'] ?? null;
                    $row['FBA'] = $raw['FBA'] ?? null;
                    $row['TPFT'] = $raw['TPFT'] ?? null;
                }
            }

            $row['amz_price'] = $amazonSheet ? ($amazonSheet->price ?? 0) : 0;
            $row['amz_pft'] = $amazonSheet && ($amazonSheet->price ?? 0) > 0 ? ((($amazonSheet->price * $percentage) - $lp - $ship) / $amazonSheet->price) : 0;
            $row['amz_roi'] = $amazonSheet && $lp > 0 && ($amazonSheet->price ?? 0) > 0 ? (($amazonSheet->price * 0.70 - $lp - $ship) / $lp) : 0;

            $prices = DB::connection('repricer')
                ->table('lmp_data')
                ->where('sku', $sku)
                ->where('price', '>', 0)
                ->orderBy('price', 'asc')
                ->pluck('price')
                ->toArray();

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
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    public function adcvrPtAmazon(){
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();

        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;
        
        return view('market-places.adcvrPtAmazon', [
            'amazonPercentage' => $percentage,
            'amazonAdUpdates' => $adUpdates
        ]);
    }

    public function adcvrPtAmazonData() {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();

        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 1;

        $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $amazonSpCampaignReportsL90 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L90')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '% PT')
                    ->orWhere('campaignName', 'LIKE', '%' . $sku . '% pt');
                }
            })
            ->get();

        $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '% PT')
                    ->orWhere('campaignName', 'LIKE', '%' . $sku . '% pt');
                }
            })
            ->get();

        $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '% PT')
                    ->orWhere('campaignName', 'LIKE', '%' . $sku . '% pt');
                }
            })
            ->get();

        $amazonHlL90 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L90')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $upperSku = strtoupper($sku);
                    $q->orWhere('campaignName', 'NOT LIKE', '%' . $upperSku . '% PT')
                    ->orWhere('campaignName', ' NOT LIKE', '%' . $upperSku . '% pt');
                }
            })
            ->get();

        $amazonHlL30 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $upperSku = strtoupper($sku);
                    $q->orWhere('campaignName', 'NOT LIKE', '%' . $upperSku . '% PT')
                    ->orWhere('campaignName', 'NOT LIKE', '%' . $upperSku . '% pt');
                }
            })
            ->get();

        $amazonHlL7 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $upperSku = strtoupper($sku);
                    $q->orWhere('campaignName', 'NOT LIKE', '%' . $upperSku . '% PT')
                    ->orWhere('campaignName', 'NOT LIKE', '%' . $upperSku . '% pt');
                }
            })
            ->get();
            
        $result = [];
        $parentHlSpendData = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaignL90 = $amazonSpCampaignReportsL90->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            $matchedCampaignPtL90 = $amazonSpCampaignReportsL90->first(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));
                return (str_ends_with($cleanName, $sku . ' PT') || str_ends_with($cleanName, $sku . ' PT.'))
                    && strtoupper($item->campaignStatus) === 'ENABLED';
            });

            $matchedCampaignPtL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));
                return (str_ends_with($cleanName, $sku . ' PT') || str_ends_with($cleanName, $sku . ' PT.'))
                    && strtoupper($item->campaignStatus) === 'ENABLED';
            });

            $matchedCampaignPtL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));
                return (str_ends_with($cleanName, $sku . ' PT') || str_ends_with($cleanName, $sku . ' PT.'))
                    && strtoupper($item->campaignStatus) === 'ENABLED';
            });

            $matchedCampaignHlL90 = $amazonHlL90->first(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));
                return in_array($cleanName, [$sku, $sku . ' HEAD']) && strtoupper($item->campaignStatus) === 'ENABLED';
            });

            $matchedCampaignHlL30 = $amazonHlL30->first(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));
                return in_array($cleanName, [$sku, $sku . ' HEAD']) && strtoupper($item->campaignStatus) === 'ENABLED';
            });
            $matchedCampaignHlL7 = $amazonHlL7->first(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));
                return in_array($cleanName, [$sku, $sku . ' HEAD']) && strtoupper($item->campaignStatus) === 'ENABLED';
            });

            if (str_starts_with($sku, 'PARENT')) {
                $childCount = $parentSkuCounts[$parent] ?? 0;
                $parentHlSpendData[$parent] = [
                    'total_L90' => $matchedCampaignHlL90->cost ?? 0,
                    'total_L30' => $matchedCampaignHlL30->cost ?? 0,
                    'total_L7'  => $matchedCampaignHlL7->cost ?? 0,
                    'total_L90_sales' => $matchedCampaignHlL90->sales ?? 0,
                    'total_L30_sales' => $matchedCampaignHlL30->sales ?? 0,
                    'total_L7_sales'  => $matchedCampaignHlL7->sales ?? 0,
                    'total_L90_sold'  => $matchedCampaignHlL90->unitsSold ?? 0,
                    'total_L30_sold'  => $matchedCampaignHlL30->unitsSold ?? 0,
                    'total_L7_sold'   => $matchedCampaignHlL7->unitsSold ?? 0,
                    'total_L90_impr'  => $matchedCampaignHlL90->impressions ?? 0,
                    'total_L30_impr'  => $matchedCampaignHlL30->impressions ?? 0,
                    'total_L7_impr'   => $matchedCampaignHlL7->impressions ?? 0,
                    'total_L90_clicks'=> $matchedCampaignHlL90->clicks ?? 0,
                    'total_L30_clicks'=> $matchedCampaignHlL30->clicks ?? 0,
                    'total_L7_clicks' => $matchedCampaignHlL7->clicks ?? 0,
                    'childCount'=> $childCount,
                ];
            }

            $row = [];

            // --- KW ---
            $row['kw_impr_L90'] = $matchedCampaignL90->impressions ?? 0;
            $row['kw_impr_L30'] = $matchedCampaignL30->impressions ?? 0;
            $row['kw_impr_L7']  = $matchedCampaignKwL7->impressions ?? 0;
            $row['kw_clicks_L90'] = $matchedCampaignL90->clicks ?? 0;
            $row['kw_clicks_L30'] = $matchedCampaignL30->clicks ?? 0;
            $row['kw_clicks_L7']  = $matchedCampaignKwL7->clicks ?? 0;
            $row['kw_spend_L90']  = $matchedCampaignL90->spend ?? 0;
            $row['kw_spend_L30']  = $matchedCampaignL30->spend ?? 0;
            $row['kw_spend_L7']   = $matchedCampaignKwL7->spend ?? 0;
            $row['kw_sales_L90']  = $matchedCampaignL90->sales30d ?? 0;
            $row['kw_sales_L30']  = $matchedCampaignL30->sales30d ?? 0;
            $row['kw_sales_L7']   = $matchedCampaignKwL7->sales7d ?? 0;
            $row['kw_sold_L90']  = $matchedCampaignL90->unitsSoldSameSku30d ?? 0;
            $row['kw_sold_L30']  = $matchedCampaignL30->unitsSoldSameSku30d ?? 0;
            $row['kw_sold_L7']   = $matchedCampaignKwL7->unitsSoldSameSku7d ?? 0;

            // --- PT ---
            $row['pt_impr_L90'] = $matchedCampaignPtL90->impressions ?? 0;
            $row['pt_impr_L30'] = $matchedCampaignPtL30->impressions ?? 0;
            $row['pt_impr_L7']  = $matchedCampaignPtL7->impressions ?? 0;
            $row['pt_clicks_L90'] = $matchedCampaignPtL90->clicks ?? 0;
            $row['pt_clicks_L30'] = $matchedCampaignPtL30->clicks ?? 0;
            $row['pt_clicks_L7']  = $matchedCampaignPtL7->clicks ?? 0;
            $row['pt_spend_L90']  = $matchedCampaignPtL90->spend ?? 0;
            $row['pt_spend_L30']  = $matchedCampaignPtL30->spend ?? 0;
            $row['pt_spend_L7']   = $matchedCampaignPtL7->spend ?? 0;
            $row['pt_sales_L90']  = $matchedCampaignPtL90->sales30d ?? 0;
            $row['pt_sales_L30']  = $matchedCampaignPtL30->sales30d ?? 0;
            $row['pt_sales_L7']   = $matchedCampaignPtL7->sales7d ?? 0;
            $row['pt_sold_L90']  = $matchedCampaignPtL90->unitsSoldSameSku30d ?? 0;
            $row['pt_sold_L30']  = $matchedCampaignPtL30->unitsSoldSameSku30d ?? 0;
            $row['pt_sold_L7']   = $matchedCampaignPtL7->unitsSoldSameSku7d ?? 0;

            // --- HL  ---
            $row['hl_impr_L90'] = $matchedCampaignHlL90->impressions ?? 0;
            $row['hl_impr_L30'] = $matchedCampaignHlL30->impressions ?? 0;
            $row['hl_impr_L7']  = $matchedCampaignHlL7->impressions ?? 0;
            $row['hl_clicks_L90'] = $matchedCampaignHlL90->clicks ?? 0;
            $row['hl_clicks_L30'] = $matchedCampaignHlL30->clicks ?? 0;
            $row['hl_clicks_L7']  = $matchedCampaignHlL7->clicks ?? 0;
            $row['hl_campaign_L90'] = $matchedCampaignHlL90->campaignName ?? null;
            $row['hl_campaign_L30'] = $matchedCampaignHlL30->campaignName ?? null;
            $row['hl_campaign_L7']  = $matchedCampaignHlL7->campaignName ?? null;
            $row['hl_sales_L90']  = $matchedCampaignHlL90->sales ?? 0;
            $row['hl_sales_L30']  = $matchedCampaignHlL30->sales ?? 0;
            $row['hl_sales_L7']   = $matchedCampaignHlL7->sales ?? 0;
            $row['hl_sold_L90']  = $matchedCampaignHlL90->unitsSold ?? 0;
            $row['hl_sold_L30']  = $matchedCampaignHlL30->unitsSold ?? 0;
            $row['hl_sold_L7']   = $matchedCampaignHlL7->unitsSold ?? 0;

            if (str_starts_with($sku, 'PARENT')) {
                $row['hl_spend_L90'] = $matchedCampaignHlL90->cost ?? 0;
                $row['hl_spend_L30'] = $matchedCampaignHlL30->cost ?? 0;
                $row['hl_spend_L7']  = $matchedCampaignHlL7->cost ?? 0;
                $row['hl_sales_L90']  = $matchedCampaignHlL90->sales ?? 0;
                $row['hl_sales_L30']  = $matchedCampaignHlL30->sales ?? 0;
                $row['hl_sales_L7']   = $matchedCampaignHlL7->sales ?? 0;
                $row['hl_sold_L90']  = $matchedCampaignHlL90->unitsSold ?? 0;
                $row['hl_sold_L30']  = $matchedCampaignHlL30->unitsSold ?? 0;
                $row['hl_sold_L7']   = $matchedCampaignHlL7->unitsSold ?? 0;
                $row['hl_impr_L90'] = $matchedCampaignHlL90->impressions ?? 0;
                $row['hl_impr_L30'] = $matchedCampaignHlL30->impressions ?? 0;
                $row['hl_impr_L7']  = $matchedCampaignHlL7->impressions ?? 0;
                $row['hl_clicks_L90'] = $matchedCampaignHlL90->clicks ?? 0;
                $row['hl_clicks_L30'] = $matchedCampaignHlL30->clicks ?? 0;
                $row['hl_clicks_L7']  = $matchedCampaignHlL7->clicks ?? 0;
            } 
            elseif (isset($parentHlSpendData[$parent]) && $parentHlSpendData[$parent]['childCount'] > 0) {
                $row['hl_spend_L90'] = $parentHlSpendData[$parent]['total_L90'] / $parentHlSpendData[$parent]['childCount'];
                $row['hl_spend_L30'] = $parentHlSpendData[$parent]['total_L30'] / $parentHlSpendData[$parent]['childCount'];
                $row['hl_spend_L7']  = $parentHlSpendData[$parent]['total_L7'] / $parentHlSpendData[$parent]['childCount'];
                $row['hl_sales_L90']  = $parentHlSpendData[$parent]['total_L90_sales'] / $parentHlSpendData[$parent]['childCount'];
                $row['hl_sales_L30']  = $parentHlSpendData[$parent]['total_L30_sales'] / $parentHlSpendData[$parent]['childCount'];
                $row['hl_sales_L7']   = $parentHlSpendData[$parent]['total_L7_sales'] / $parentHlSpendData[$parent]['childCount'];
                $row['hl_sold_L90']  = $parentHlSpendData[$parent]['total_L90_sold'] / $parentHlSpendData[$parent]['childCount'];
                $row['hl_sold_L30']  = $parentHlSpendData[$parent]['total_L30_sold'] / $parentHlSpendData[$parent]['childCount'];
                $row['hl_sold_L7']   = $parentHlSpendData[$parent]['total_L7_sold'] / $parentHlSpendData[$parent]['childCount'];
                $row['hl_impr_L90'] = $parentHlSpendData[$parent]['total_L90_impr'] / $parentHlSpendData[$parent]['childCount'];
                $row['hl_impr_L30'] = $parentHlSpendData[$parent]['total_L30_impr'] / $parentHlSpendData[$parent]['childCount'];
                $row['hl_impr_L7']  = $parentHlSpendData[$parent]['total_L7_impr'] / $parentHlSpendData[$parent]['childCount'];
                $row['hl_clicks_L90'] = $parentHlSpendData[$parent]['total_L90_clicks'] / $parentHlSpendData[$parent]['childCount'];
                $row['hl_clicks_L30'] = $parentHlSpendData[$parent]['total_L30_clicks'] / $parentHlSpendData[$parent]['childCount'];
                $row['hl_clicks_L7']  = $parentHlSpendData[$parent]['total_L7_clicks'] / $parentHlSpendData[$parent]['childCount'];
            } else {
                $row['hl_spend_L90'] = 0;
                $row['hl_spend_L30'] = 0;
                $row['hl_spend_L7']  = 0;
                $row['hl_sales_L90'] = 0;
                $row['hl_sales_L30'] = 0;
                $row['hl_sales_L7']  = 0;
                $row['hl_sold_L90']  = 0;
                $row['hl_sold_L30']  = 0;
                $row['hl_sold_L7']   = 0;
                $row['hl_impr_L90'] = 0;
                $row['hl_impr_L30'] = 0;
                $row['hl_impr_L7']  = 0;
                $row['hl_clicks_L90'] = 0;
                $row['hl_clicks_L30'] = 0;
                $row['hl_clicks_L7']  = 0;
            }

            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['fba']    = $pm->fba ?? null;
            // $row['A_L30']  = $amazonSheet->units_ordered_l30 ?? 0;
            // $row['A_L90']  = $amazonSheet->units_ordered_l90 ?? 0;
            $row['A_L90'] = $row['kw_sold_L90'] + $row['pt_sold_L90'] + $row['hl_sold_L90'];
            $row['A_L30'] = $row['kw_sold_L30'] + $row['pt_sold_L30'] + $row['hl_sold_L30'];
            $row['A_L7'] = $row['kw_sold_L7'] + $row['pt_sold_L7'] + $row['hl_sold_L7'];
            $row['total_review_count']  = $amazonSheet->total_review_count ?? 0;
            $row['average_star_rating']  = $amazonSheet->average_star_rating ?? 0;
            $row['campaign_id'] = $matchedCampaignL90->campaign_id ??  '';
            $row['campaignName'] = $matchedCampaignL90->campaignName ?? '';
            $row['campaignStatus'] = $matchedCampaignL90->campaignStatus ?? '';
            $row['campaignBudgetAmount'] = $matchedCampaignL90->campaignBudgetAmount ?? 0;
            $row['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
            // $row['spend_l90'] = $matchedCampaignL90->spend ?? 0;
            // $row['ad_sales_l90'] = $matchedCampaignL90->sales30d ?? 0;
            $row['spend_l90'] = $row['kw_spend_L90'] + $row['pt_spend_L90'] + $row['hl_spend_L90'];
            $row['ad_sales_l90'] = $row['kw_sales_L90'] + $row['pt_sales_L90'] + $row['hl_sales_L90'];
            $row['spend_l30'] = $row['kw_spend_L30'] + $row['pt_spend_L30'] + $row['hl_spend_L30'];
            $row['ad_sales_l30'] = $row['kw_sales_L30'] + $row['pt_sales_L30'] + $row['hl_sales_L30'];
            $row['spend_l7'] = $row['kw_spend_L7'] + $row['pt_spend_L7'] + $row['hl_spend_L7'];
            $row['ad_sales_l7'] = $row['kw_sales_L7'] + $row['pt_sales_L7'] + $row['hl_sales_L7'];

            if ($amazonSheet) {
                $row['A_L30'] = $amazonSheet->units_ordered_l30;
                // $row['A_L90']  = $amazonSheet->units_ordered_l90;
                $row['Sess30'] = $amazonSheet->sessions_l30;
                $row['price'] = $amazonSheet->price;
                $row['price_lmpa'] = $amazonSheet->price_lmpa;
                $row['sessions_l60'] = $amazonSheet->sessions_l60;
                $row['units_ordered_l60'] = $amazonSheet->units_ordered_l60;
            }

            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = 0;
            foreach ($values as $k => $v) {
                if (strtolower($k) === 'lp') {
                    $lp = floatval($v);
                    break;
                }
            }
            if ($lp === 0 && isset($pm->lp)) {
                $lp = floatval($pm->lp);
            }
            $ship = isset($values['ship']) ? floatval($values['ship']) : (isset($pm->ship) ? floatval($pm->ship) : 0);

            $row['SHIP'] = $ship;
            $row['LP'] = $lp;
            
            $price = isset($row['price']) ? floatval($row['price']) : 0;
            
            $row['PFT_percentage'] = round($price > 0 ? ((($price * $percentage) - $lp - $ship) / $price) : 0, 2);

            $sales90 = $matchedCampaignL90->sales30d ?? 0;
            $spend90 = $matchedCampaignL90->spend ?? 0;

            $sales30 = $matchedCampaignL30->sales30d ?? 0;
            $spend30 = $matchedCampaignL30->spend ?? 0;

            $sales7 = $matchedCampaignL7->sales30d ?? 0;
            $spend7 = $matchedCampaignL7->spend ?? 0;

            if ($sales90 > 0) {
                $row['acos_L90'] = round(($spend90 / $sales90) * 100, 2);
            } elseif ($spend90 > 0) {
                $row['acos_L90'] = 100;
            } else {
                $row['acos_L90'] = 0;
            }

            if ($sales30 > 0) {
                $row['acos_L30'] = round(($spend30 / $sales30) * 100, 2);
            } elseif ($spend30 > 0) {
                $row['acos_L30'] = 100;
            } else {
                $row['acos_L30'] = 0;
            }

            if ($sales7 > 0) {
                $row['acos_L7'] = round(($spend7 / $sales7) * 100, 2);
            } elseif ($spend7 > 0) {
                $row['acos_L7'] = 100;
            } else {
                $row['acos_L7'] = 0;
            }

            // $row['clicks_L90'] = $matchedCampaignL90->clicks ?? 0;
            $row['clicks_L90'] = $row['kw_clicks_L90'] + $row['pt_clicks_L90'] + $row['hl_clicks_L90'];
            $row['clicks_L30'] = $row['kw_clicks_L30'] + $row['pt_clicks_L30'] + $row['hl_clicks_L30'];
            $row['clicks_L7'] = $row['kw_clicks_L7'] + $row['pt_clicks_L7'] + $row['hl_clicks_L7'];

            $row['cvr_l90'] = $row['clicks_L90'] == 0 ? NULL : number_format(($row['A_L90'] / $row['clicks_L90']) * 100, 2);
            $row['cvr_l30'] = $row['clicks_L30'] == 0 ? NULL : number_format(($row['A_L30'] / $row['clicks_L30']) * 100, 2);
            $row['cvr_l7'] = $row['clicks_L7'] == 0 ? NULL : number_format(($row['A_L7'] / $row['clicks_L7']) * 100, 2);

            $row['NRL']  = '';
            $row['NRA'] = '';
            $row['FBA'] = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NRL']  = $raw['NRL'] ?? null;
                    $row['NRA'] = $raw['NRA'] ?? null;
                    $row['FBA'] = $raw['FBA'] ?? null;
                    $row['TPFT'] = $raw['TPFT'] ?? null;
                }
            }

            $row['amz_price'] = $amazonSheet ? ($amazonSheet->price ?? 0) : 0;
            $row['amz_pft'] = $amazonSheet && ($amazonSheet->price ?? 0) > 0 ? ((($amazonSheet->price * $percentage) - $lp - $ship) / $amazonSheet->price) : 0;
            $row['amz_roi'] = $amazonSheet && $lp > 0 && ($amazonSheet->price ?? 0) > 0 ? (($amazonSheet->price * 0.70 - $lp - $ship) / $lp) : 0;

            $prices = DB::connection('repricer')
                ->table('lmpa_data')
                ->where('sku', $sku)
                ->where('price', '>', 0)
                ->orderBy('price', 'asc')
                ->pluck('price')
                ->toArray();

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
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    public function updateAmzPrice(Request $request) {
        try {
            $validated = $request->validate([
                'sku' => 'required|exists:amazon_datsheets,sku',
                'price' => 'required|numeric',
            ]);

            
            $amazonData = AmazonDatasheet::find($validated['sku']);

            $amazonData->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Amazon price and metrics updated successfully.',
                'data' => $amazonData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function reviewRatingsAmazon(){
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();

        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;
        
        return view('market-places.reviewRatingsAmazon', [
            'amazonPercentage' => $percentage,
            'amazonAdUpdates' => $adUpdates
        ]);
    }

    public function reviewRatingsAmazonData() {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();

        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 1;

        $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $parents = $productMasters->pluck('parent')->filter()->unique()->map('strtoupper')->values()->all();

        $jungleScoutData = JungleScoutProductData::whereIn('parent', $parents)
        ->get()
        ->groupBy(function ($item) {
            return strtoupper(trim($item->parent));
        })
        ->map(function ($group) {
            $validPrices = $group->filter(function ($item) {
                $data = is_array($item->data) ? $item->data : [];
                $price = $data['price'] ?? null;
                return is_numeric($price) && $price > 0;
            })->pluck('data.price');

            return [
                'scout_parent' => $group->first()->parent,
                'min_price' => $validPrices->isNotEmpty() ? $validPrices->min() : null,
                'product_count' => $group->count(),
                'all_data' => $group->map(function ($item) {
                    $data = is_array($item->data) ? $item->data : [];
                    if (isset($data['price'])) {
                        $data['price'] = is_numeric($data['price']) ? (float) $data['price'] : null;
                    }
                    return $data;
                })->toArray()
            ];
        });

        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
            })
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
            ->get();

        $amazonSpCampaignReportsL90 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L90')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
            })
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
            ->get();

        $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
            })
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            $matchedCampaignL90 = $amazonSpCampaignReportsL90->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['fba']    = $pm->fba ?? null;
            $row['A_L30']  = $amazonSheet->units_ordered_l30 ?? 0;
            $row['A_L90']  = $amazonSheet->units_ordered_l90 ?? 0;
            $row['campaign_id'] = $matchedCampaignL90->campaign_id ??  '';
            $row['campaignName'] = $matchedCampaignL90->campaignName ?? '';
            $row['campaignStatus'] = $matchedCampaignL90->campaignStatus ?? '';
            $row['campaignBudgetAmount'] = $matchedCampaignL90->campaignBudgetAmount ?? 0;
            $row['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
            $row['spend_l90'] = $matchedCampaignL90->spend ?? 0;
            $row['ad_sales_l90'] = $matchedCampaignL90->sales30d ?? 0;

            if ($amazonSheet) {
                $row['A_L30'] = $amazonSheet->units_ordered_l30;
                $row['A_L90']  = $amazonSheet->units_ordered_l90;
                $row['Sess30'] = $amazonSheet->sessions_l30;
                $row['price'] = $amazonSheet->price;
                $row['price_lmpa'] = $amazonSheet->price_lmpa;
                $row['sessions_l60'] = $amazonSheet->sessions_l60;
                $row['units_ordered_l60'] = $amazonSheet->units_ordered_l60;
            }

            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = 0;
            foreach ($values as $k => $v) {
                if (strtolower($k) === 'lp') {
                    $lp = floatval($v);
                    break;
                }
            }
            if ($lp === 0 && isset($pm->lp)) {
                $lp = floatval($pm->lp);
            }
            $ship = isset($values['ship']) ? floatval($values['ship']) : (isset($pm->ship) ? floatval($pm->ship) : 0);

            $row['SHIP'] = $ship;
            $row['LP'] = $lp;
            
            $price = isset($row['price']) ? floatval($row['price']) : 0;
            
            $row['PFT_percentage'] = round($price > 0 ? ((($price * $percentage) - $lp - $ship) / $price) * 100 : 0, 2);

            $sales = $matchedCampaignL90->sales30d ?? 0;
            $spend = $matchedCampaignL90->spend ?? 0;

            if ($sales > 0) {
                $row['acos_L90'] = round(($spend / $sales) * 100, 2);
            } elseif ($spend > 0) {
                $row['acos_L90'] = 100;
            } else {
                $row['acos_L90'] = 0;
            }

            $row['clicks_L90'] = $matchedCampaignL90->clicks ?? 0;

            $row['cvr_l90'] = $row['clicks_L90'] == 0 ? NULL : number_format(($row['A_L90'] / $row['clicks_L90']) * 100, 2);

            $row['NRL']  = '';
            $row['NRA'] = '';
            $row['FBA'] = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NRL']  = $raw['NRL'] ?? null;
                    $row['NRA'] = $raw['NRA'] ?? null;
                    $row['FBA'] = $raw['FBA'] ?? null;
                    $row['TPFT'] = $raw['TPFT'] ?? null;
                }
            }

            $row['amz_price'] = $amazonSheet ? ($amazonSheet->price ?? 0) : 0;
            $row['amz_pft'] = $amazonSheet && ($amazonSheet->price ?? 0) > 0 ? (($amazonSheet->price * 0.70 - $lp - $ship) / $amazonSheet->price) : 0;
            $row['amz_roi'] = $amazonSheet && $lp > 0 && ($amazonSheet->price ?? 0) > 0 ? (($amazonSheet->price * 0.70 - $lp - $ship) / $lp) : 0;

            $prices = DB::connection('repricer')
                ->table('lmpa_data')
                ->where('sku', $sku)
                ->where('price', '>', 0)
                ->orderBy('price', 'asc')
                ->pluck('price')
                ->toArray();

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
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    public function targetingAmazon(){
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();

        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;
        
        return view('market-places.targetingAmazon', [
            'amazonPercentage' => $percentage,
            'amazonAdUpdates' => $adUpdates
        ]);
    }

    public function targetingAmazonData() {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();

        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 1;

        $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
            })
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
            ->get();

        $amazonSpCampaignReportsL90 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L90')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
            })
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
            ->get();

        $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
            })
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            $matchedCampaignL90 = $amazonSpCampaignReportsL90->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['fba']    = $pm->fba ?? null;
            $row['A_L30']  = $amazonSheet->units_ordered_l30 ?? 0;
            $row['A_L90']  = $amazonSheet->units_ordered_l90 ?? 0;
            $row['total_review_count']  = $amazonSheet->total_review_count ?? 0;
            $row['average_star_rating']  = $amazonSheet->average_star_rating ?? 0;
            $row['campaign_id'] = $matchedCampaignL90->campaign_id ??  '';
            $row['campaignName'] = $matchedCampaignL90->campaignName ?? '';
            $row['campaignStatus'] = $matchedCampaignL90->campaignStatus ?? '';
            $row['campaignBudgetAmount'] = $matchedCampaignL90->campaignBudgetAmount ?? 0;
            $row['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
            $row['spend_l90'] = $matchedCampaignL90->spend ?? 0;
            $row['ad_sales_l90'] = $matchedCampaignL90->sales30d ?? 0;

            if ($amazonSheet) {
                $row['A_L30'] = $amazonSheet->units_ordered_l30;
                $row['A_L90']  = $amazonSheet->units_ordered_l90;
                $row['Sess30'] = $amazonSheet->sessions_l30;
                $row['price'] = $amazonSheet->price;
                $row['price_lmpa'] = $amazonSheet->price_lmpa;
                $row['sessions_l60'] = $amazonSheet->sessions_l60;
                $row['units_ordered_l60'] = $amazonSheet->units_ordered_l60;
            }

            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = 0;
            foreach ($values as $k => $v) {
                if (strtolower($k) === 'lp') {
                    $lp = floatval($v);
                    break;
                }
            }
            if ($lp === 0 && isset($pm->lp)) {
                $lp = floatval($pm->lp);
            }
            $ship = isset($values['ship']) ? floatval($values['ship']) : (isset($pm->ship) ? floatval($pm->ship) : 0);

            $row['SHIP'] = $ship;
            $row['LP'] = $lp;
            
            $price = isset($row['price']) ? floatval($row['price']) : 0;
            
            $row['PFT_percentage'] = round($price > 0 ? ((($price * $percentage) - $lp - $ship) / $price) * 100 : 0, 2);

            $sales = $matchedCampaignL90->sales30d ?? 0;
            $spend = $matchedCampaignL90->spend ?? 0;

            if ($sales > 0) {
                $row['acos_L90'] = round(($spend / $sales) * 100, 2);
            } elseif ($spend > 0) {
                $row['acos_L90'] = 100;
            } else {
                $row['acos_L90'] = 0;
            }

            $row['clicks_L90'] = $matchedCampaignL90->clicks ?? 0;

            $row['cvr_l90'] = $row['clicks_L90'] == 0 ? NULL : number_format(($row['A_L90'] / $row['clicks_L90']) * 100, 2);

            $row['NRL']  = '';
            $row['NRA'] = '';
            $row['FBA'] = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NRL']  = $raw['NRL'] ?? null;
                    $row['NRA'] = $raw['NRA'] ?? null;
                    $row['FBA'] = $raw['FBA'] ?? null;
                    $row['TPFT'] = $raw['TPFT'] ?? null;
                }
            }

            $row['amz_price'] = $amazonSheet ? ($amazonSheet->price ?? 0) : 0;
            $row['amz_pft'] = $amazonSheet && ($amazonSheet->price ?? 0) > 0 ? (($amazonSheet->price * 0.70 - $lp - $ship) / $amazonSheet->price) : 0;
            $row['amz_roi'] = $amazonSheet && $lp > 0 && ($amazonSheet->price ?? 0) > 0 ? (($amazonSheet->price * 0.70 - $lp - $ship) / $lp) : 0;

            $prices = DB::connection('repricer')
                ->table('lmpa_data')
                ->where('sku', $sku)
                ->where('price', '>', 0)
                ->orderBy('price', 'asc')
                ->pluck('price')
                ->toArray();

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
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    public function overallAmazon(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();

        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;

        return view('market-places.overallAmazon', [
            'mode' => $mode,
            'demo' => $demo,
            'amazonPercentage' => $percentage,
            'amazonAdUpdates' => $adUpdates
        ]);
    }

    public function getAmazonTotalSalesSaveData(Request $request)
    {
        return ADVMastersData::getAmazonTotalSalesSaveDataProceed($request);
    }

    public function amazonPricingCVR(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get fresh data from database instead of cache for immediate updates
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();

        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;


        return view('market-places.amazon_pricing_cvr', [
            'mode' => $mode,
            'demo' => $demo,
            'amazonPercentage' => $percentage,
            'amazonAdUpdates' => $adUpdates
        ]);
    }


    public function updateFbaStatus(Request $request)
    {
        $sku = $request->input('shopify_id');
        $fbaStatus = $request->input('fba');

        if (!$sku || !is_numeric($fbaStatus)) {
            return response()->json(['error' => 'SKU and FBA status are required.'], 400);
        }
        $amazonData = DB::table('amazon_data_view')
            ->where('sku', $sku)
            ->first();

        if (!$amazonData) {
            return response()->json(['error' => 'SKU not found.'], 404);
        }
        DB::table('amazon_data_view')
            ->where('sku', $sku)
            ->update(['fba' => $fbaStatus]);
        $updatedData = DB::table('amazon_data_view')
            ->where('sku', $sku)
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'FBA status updated successfully.',
            'data' => $updatedData
        ]);
    }


    public function getViewAmazonData(Request $request)
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $ratings = AmazonFbmManual::whereIn('sku', $skus)->pluck('data', 'sku')->toArray();

        // Get all JungleScout data - group by SKU first, then also by ASIN for fallback
        $allJungleScoutData = JungleScoutProductData::all();
        
        // Group by SKU (case-insensitive) - for products with SKU in Jungle Scout
        $jungleScoutBySku = $allJungleScoutData
            ->filter(function ($item) {
                return !empty($item->sku);
            })
            ->groupBy(function ($item) {
                return strtoupper(trim($item->sku));
            })
            ->map(function ($group) {
                $validPrices = $group->filter(function ($item) {
                    $data = is_array($item->data) ? $item->data : json_decode($item->data, true);
                    $price = $data['price'] ?? null;
                    return is_numeric($price) && $price > 0;
                })->map(function ($item) {
                    $data = is_array($item->data) ? $item->data : json_decode($item->data, true);
                    return $data['price'];
                });

                return [
                    'scout_parent' => $group->first()->parent,
                    'min_price' => $validPrices->isNotEmpty() ? $validPrices->min() : null,
                    'product_count' => $group->count(),
                    'all_data' => $group->map(function ($item) {
                        $data = is_array($item->data) ? $item->data : json_decode($item->data, true);
                        if (isset($data['price'])) {
                            $data['price'] = is_numeric($data['price']) ? (float) $data['price'] : null;
                        }
                        return $data;
                    })->toArray()
                ];
            });
        
        // Group by ASIN (from data->id field) - for fallback when SKU doesn't match
        $jungleScoutByAsin = $allJungleScoutData
            ->filter(function ($item) {
                $data = is_array($item->data) ? $item->data : json_decode($item->data, true);
                return isset($data['id']) && !empty($data['id']);
            })
            ->groupBy(function ($item) {
                $data = is_array($item->data) ? $item->data : json_decode($item->data, true);
                $id = $data['id'] ?? '';
                $asin = str_replace('us/', '', $id);
                return strtoupper(trim($asin));
            })
            ->map(function ($group) {
                $validPrices = $group->filter(function ($item) {
                    $data = is_array($item->data) ? $item->data : json_decode($item->data, true);
                    $price = $data['price'] ?? null;
                    return is_numeric($price) && $price > 0;
                })->map(function ($item) {
                    $data = is_array($item->data) ? $item->data : json_decode($item->data, true);
                    return $data['price'];
                });

                return [
                    'scout_parent' => $group->first()->parent,
                    'min_price' => $validPrices->isNotEmpty() ? $validPrices->min() : null,
                    'product_count' => $group->count(),
                    'all_data' => $group->map(function ($item) {
                        $data = is_array($item->data) ? $item->data : json_decode($item->data, true);
                        if (isset($data['price'])) {
                            $data['price'] = is_numeric($data['price']) ? (float) $data['price'] : null;
                        }
                        return $data;
                    })->toArray()
                ];
            });

        // Load NR values from AmazonListingStatus model instead of AmazonDataView
        $nrListingStatuses = AmazonListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');
        
        // Keep loading other data from AmazonDataView for backward compatibility
        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku', 'fba');

        // Fetch LMP data from repricer database
        $lmpLowestLookup = collect();
        $lmpDetailsLookup = collect();
        try {
            $lmpRecords = DB::connection('repricer')
                ->table('lmpa_data')
                ->select('sku', 'price', 'link', 'image')
                ->whereIn('sku', $skus)
                ->orderBy('price', 'asc')
                ->get()
                ->groupBy('sku');

            $lmpDetailsLookup = $lmpRecords;
            $lmpLowestLookup = $lmpRecords->map(function ($items) {
                return $items->first();
            });
        } catch (\Exception $e) {
            Log::warning('Could not fetch LMP data from repricer database: ' . $e->getMessage());
        }

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();

        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 1; 
        $adUpdates  = $marketplaceData ? $marketplaceData->ad_updates : 0;   

        // Fetch Amazon SP Campaign Reports for L30 (KW campaigns - NOT PT)
        $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'NOT LIKE', '%' . $sku . '% PT')
                      ->orWhere('campaignName', 'NOT LIKE', '%' . $sku . '% pt');
                }
            })
            ->get();

        // Fetch Amazon SP Campaign Reports for L30 (PT campaigns)
        $amazonSpCampaignReportsPtL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->get();

        // Fetch Amazon SB Campaign Reports for L30 (HL campaigns)
        $amazonHlL30 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
            })
            ->get();

        $parentSkuCounts = $productMasters
            ->filter(fn($pm) => $pm->parent && !str_starts_with(strtoupper($pm->sku), 'PARENT'))
            ->groupBy('parent')
            ->map->count();

        $result = [];
        $parentHlSpendData = [];

        // First loop: collect parent HL spend data
        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = trim($pm->parent);

            $matchedCampaignHlL30 = $amazonHlL30->first(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));
                return in_array($cleanName, [$sku, $sku . ' HEAD']) && strtoupper($item->campaignStatus) === 'ENABLED';
            });

            if (str_starts_with($sku, 'PARENT')) {
                $childCount = $parentSkuCounts[$parent] ?? 0;
                $parentHlSpendData[$parent] = [
                    'total_L30' => $matchedCampaignHlL30->cost ?? 0,
                    'total_L30_sales' => $matchedCampaignHlL30->sales ?? 0,
                    'childCount' => $childCount,
                ];
            }
        }

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);

            if (str_starts_with($sku, 'PARENT ')) {
                continue;
            }

            $parent = $pm->parent;
            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $row = [];
            // Get rating and reviews from Jungle Scout - Try SKU first, fallback to ASIN
            $jsData = $jungleScoutBySku->get($sku);
            
            // If no match by SKU, try ASIN fallback
            if (!$jsData && $amazonSheet && $amazonSheet->asin) {
                $asinKey = strtoupper(trim($amazonSheet->asin));
                $jsData = $jungleScoutByAsin->get($asinKey);
            }
            
            $jsAllData = $jsData['all_data'] ?? [];
            
            // Extract rating and reviews from jungle scout data (first entry with valid data)
            $rating = null;
            $reviews = null;
            foreach ($jsAllData as $jsEntry) {
                if (isset($jsEntry['rating']) && $jsEntry['rating'] > 0) {
                    $rating = $jsEntry['rating'];
                    $reviews = $jsEntry['reviews'] ?? null;
                    break;
                }
            }
            
            $row['rating'] = $rating;
            $row['reviews'] = $reviews;
            $row['Parent'] = $parent;
            $row['(Child) sku'] = $pm->sku;

            if ($amazonSheet) {
                $row['A_L30'] = $amazonSheet->units_ordered_l30 ?? 0;
                $row['A_L15'] = $amazonSheet->units_ordered_l15 ?? 0;
                $row['A_L7'] = $amazonSheet->units_ordered_l7 ?? 0;
                $row['Sess30'] = $amazonSheet->sessions_l30 ?? 0;
                $row['Sess7'] = $amazonSheet->sessions_l7 ?? 0;
                $row['price'] = $amazonSheet->price;
                $row['price_lmpa'] = $amazonSheet->price_lmpa;
                $row['sessions_l60'] = $amazonSheet->sessions_l60;
                $row['units_ordered_l60'] = $amazonSheet->units_ordered_l60;
            }

            $row['INV'] = $shopify->inv ?? 0;
            $row['L30'] = $shopify->quantity ?? 0;
            $row['fba'] = $pm->fba;


            // LP & ship cost
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
            $lp = 0;
            foreach ($values as $k => $v) {
                if (strtolower($k) === 'lp') {
                    $lp = floatval($v);
                    break;
                }
            }
            if ($lp === 0 && isset($pm->lp)) {
                $lp = floatval($pm->lp);
            }
            $ship = isset($values['ship']) ? floatval($values['ship']) : (isset($pm->ship) ? floatval($pm->ship) : 0);

            $price = isset($row['price']) ? floatval($row['price']) : 0;
            $units_ordered_l30 = isset($row['A_L30']) ? floatval($row['A_L30']) : 0;

            $row['Total_pft'] = round((($price * $percentage) - $lp - $ship) * $units_ordered_l30, 2);
            $row['T_Sale_l30'] = round($price * $units_ordered_l30, 2);
            $row['PFT_percentage'] = round($price > 0 ? ((($price * $percentage) - $lp - $ship) / $price) * 100 : 0, 2);
            $row['ROI_percentage'] = round($lp > 0 ? ((($price * $percentage) - $lp - $ship) / $lp) * 100 : 0, 2);
            $row['T_COGS'] = round($lp * $units_ordered_l30, 2);
            $row['ad_updates'] = $adUpdates;

            $parentKey = strtoupper($parent);
            if (!empty($parentKey) && $jungleScoutBySku->has($parentKey)) {
                $row['scout_data'] = $jungleScoutBySku[$parentKey];
            } elseif (!empty($parentKey) && $jungleScoutByAsin->has($parentKey)) {
                $row['scout_data'] = $jungleScoutByAsin[$parentKey];
            }

            $row['percentage'] = $percentage;
            $row['LP_productmaster'] = $lp;
            $row['Ship_productmaster'] = $ship;

            // Default values
            $row['NRL'] = '';
            $row['NRA'] = '';
            $row['FBA'] = null;
            $row['SPRICE'] = null;
            $row['Spft'] = null;
            $row['SROI'] = null;
            $row['ad_spend'] = null;
            $row['Listed'] = null;
            $row['Live'] = null;
            $row['APlus'] = null;
            $row['js_comp_manual_api_link'] = null;
            $row['js_comp_manual_link'] = null;

            // LMP data - lowest entry plus top entries
            $lmpEntries = $lmpDetailsLookup->get($pm->sku);
            if (!$lmpEntries instanceof \Illuminate\Support\Collection) {
                $lmpEntries = collect();
            }

            $lowestLmp = $lmpLowestLookup->get($pm->sku);
            $row['lmp_price'] = ($lowestLmp && isset($lowestLmp->price))
                ? (is_numeric($lowestLmp->price) ? floatval($lowestLmp->price) : null)
                : null;
            $row['lmp_link'] = $lowestLmp->link ?? null;
            $row['lmp_entries'] = $lmpEntries
                ->take(10)
                ->map(function ($entry) {
                    return [
                        'price' => is_numeric($entry->price) ? floatval($entry->price) : null,
                        'link' => $entry->link ?? null,
                        'image' => $entry->image ?? null,
                    ];
                })
                ->toArray();
            $row['lmp_entries_total'] = $lmpEntries->count();

            // Amazon SP Campaign Reports - KW and PMT spend L30
            // Get ALL matching campaigns (not just first) to handle variations like 'A-54' and 'A-54 2PCS'
            $matchedCampaignsKwL30 = $amazonSpCampaignReportsL30->filter(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                // Match exact SKU or campaigns starting with SKU (like 'A-54' and 'A-54 2PCS')
                return $campaignName === $cleanSku || str_starts_with($campaignName, $cleanSku . ' ');
            });

            $matchedCampaignsPtL30 = $amazonSpCampaignReportsPtL30->filter(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));
                // Match PT campaigns that end with SKU PT or start with SKU and end with PT
                return (str_ends_with($cleanName, $sku . ' PT') || str_ends_with($cleanName, $sku . ' PT.'))
                    || (str_starts_with($cleanName, $sku . ' ') && (str_ends_with($cleanName, ' PT') || str_ends_with($cleanName, ' PT.')));
            });

            $matchedCampaignHlL30 = $amazonHlL30->first(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));
                return in_array($cleanName, [$sku, $sku . ' HEAD']) && strtoupper($item->campaignStatus) === 'ENABLED';
            });

            // Sum spend from all matching KW campaigns
            $row['kw_spend_L30'] = $matchedCampaignsKwL30->sum('spend');
            // Sum spend from all matching PT campaigns
            $row['pmt_spend_L30'] = $matchedCampaignsPtL30->sum('spend');
            
            // Campaign status for ad pause toggle
            // Check EXACT SKU match first (like acos-kw-control shows individual campaign status)
            // If exact match not found, then check variations
            $cleanSku = strtoupper(trim(rtrim($sku, '.')));
            
            // Find exact SKU match campaigns (not variations)
            $exactKwCampaigns = $matchedCampaignsKwL30->filter(function ($campaign) use ($cleanSku) {
                $campaignName = strtoupper(trim(rtrim($campaign->campaignName, '.')));
                return $campaignName === $cleanSku;
            });
            
            $exactPtCampaigns = $matchedCampaignsPtL30->filter(function ($campaign) use ($cleanSku, $sku) {
                $cleanName = strtoupper(trim($campaign->campaignName));
                // Exact PT match: ends with exactly "SKU PT" or "SKU PT."
                return str_ends_with($cleanName, $sku . ' PT') || str_ends_with($cleanName, $sku . ' PT.');
            });
            
            // Use exact match if available, otherwise use all matches (variations)
            $kwCampaignsForStatus = $exactKwCampaigns->isNotEmpty() ? $exactKwCampaigns : $matchedCampaignsKwL30;
            $ptCampaignsForStatus = $exactPtCampaigns->isNotEmpty() ? $exactPtCampaigns : $matchedCampaignsPtL30;
            
            // Check if ANY campaign is ENABLED
            $kwHasEnabled = $kwCampaignsForStatus->contains(function ($campaign) {
                return strtoupper($campaign->campaignStatus ?? 'PAUSED') === 'ENABLED';
            });
            $ptHasEnabled = $ptCampaignsForStatus->contains(function ($campaign) {
                return strtoupper($campaign->campaignStatus ?? 'PAUSED') === 'ENABLED';
            });

            // If either has any ENABLED campaign, ads are enabled
            $row['ad_pause'] = !($kwHasEnabled || $ptHasEnabled);
            
            // Store status based on exact match (or all if no exact match)
            // This matches the behavior in acos-kw-control where individual campaign status is shown
            $row['kw_campaign_status'] = $kwHasEnabled ? 'ENABLED' : 'PAUSED';
            $row['pt_campaign_status'] = $ptHasEnabled ? 'ENABLED' : 'PAUSED';
            
            // Check if any campaigns exist for this SKU
            $row['has_campaigns'] = $matchedCampaignsKwL30->isNotEmpty() || $matchedCampaignsPtL30->isNotEmpty();
            
            // Sales data for L30 (like AmazonAdRunningController) - sum from all matching campaigns
            $row['kw_sales_L30'] = $matchedCampaignsKwL30->sum('sales30d');
            $row['pmt_sales_L30'] = $matchedCampaignsPtL30->sum('sales30d');

            // HL (Headline/Sponsored Brands) data (like AmazonAdRunningController)
            if (isset($parentHlSpendData[$parent]) && $parentHlSpendData[$parent]['childCount'] > 0) {
                $row['hl_spend_L30'] = $parentHlSpendData[$parent]['total_L30'] / $parentHlSpendData[$parent]['childCount'];
                $row['hl_sales_L30'] = $parentHlSpendData[$parent]['total_L30_sales'] / $parentHlSpendData[$parent]['childCount'];
            } else {
                $row['hl_spend_L30'] = 0;
                $row['hl_sales_L30'] = 0;
            }

            // SPEND_L30 and SALES_L30 include HL (like AmazonAdRunningController)
            $row['SPEND_L30'] = ($row['kw_spend_L30'] ?? 0) + ($row['pmt_spend_L30'] ?? 0) + ($row['hl_spend_L30'] ?? 0);
            $row['AD_Spend_L30'] = ($row['kw_spend_L30'] ?? 0) + ($row['pmt_spend_L30'] ?? 0) + ($row['hl_spend_L30'] ?? 0);
            $row['SALES_L30'] = ($row['kw_sales_L30'] ?? 0) + ($row['pmt_sales_L30'] ?? 0) + ($row['hl_sales_L30'] ?? 0);

            // AD% Formula = (AD_Spend_L30 / (price * A_L30)) * 100
            $totalRevenue = $price * $units_ordered_l30;
            $row['AD%'] = $totalRevenue > 0
                ? round(($row['AD_Spend_L30'] / $totalRevenue) * 100, 4)
                : 0;

            // GPFT% Formula = ((price × 0.80 - ship - lp) / price) × 100
            $row['GPFT%'] = $price > 0
                ? round((($price * 0.80 - $ship - $lp) / $price) * 100, 2)
                : 0;

            // PFT% = GPFT% - AD%
            $row['PFT%'] = round($row['GPFT%'] - $row['AD%'], 2);

            // ROI% = ((price * (0.80 - AD%/100) - ship - lp) / lp) * 100
            $adDecimal = $row['AD%'] / 100;
            $row['ROI_percentage'] = round(
                $lp > 0 ? (($price * (0.80 - $adDecimal) - $ship - $lp) / $lp) * 100 : 0,
                2
            );

            // Load NR field from AmazonDataView (where ListingAmazonController saves it)
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];

                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }

                if (is_array($raw)) {
                    // Read NRL field from amazon_data_view - "REQ" means RL, "NRL" means NRL
                    $nrlValue = $raw['NRL'] ?? null;
                    if ($nrlValue === 'NRL') {
                        $row['NR'] = 'NR';
                    } else if ($nrlValue === 'REQ') {
                        $row['NR'] = 'REQ';
                    } else {
                        $row['NR'] = null;
                    }
                    
                    // Load buyer and seller links
                    $row['buyer_link'] = $raw['buyer_link'] ?? null;
                    $row['seller_link'] = $raw['seller_link'] ?? null;
                    
                    $row['NRA'] = $raw['NRA'] ?? null;
                    $row['FBA'] = $raw['FBA'] ?? null;
                    $row['shopify_id'] = $shopify->id ?? null;
                    $row['SPRICE'] = $raw['SPRICE'] ?? null;
                    $row['Spft%'] = $raw['SPFT'] ?? null;
                    $row['SROI'] = $raw['SROI'] ?? null;
                    $row['SGPFT'] = $raw['SGPFT'] ?? null;
                    $row['ad_spend'] = $raw['Spend_L30'] ?? null;
                    $row['SPRICE_STATUS'] = $raw['SPRICE_STATUS'] ?? null; // Status: 'pushed', 'applied', 'error'
                    $row['Listed'] = isset($raw['Listed']) ? filter_var($raw['Listed'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['Live'] = isset($raw['Live']) ? filter_var($raw['Live'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['APlus'] = isset($raw['APlus']) ? filter_var($raw['APlus'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['js_comp_manual_api_link'] = $raw['js_comp_manual_api_link'] ?? '';
                    $row['js_comp_manual_link'] = $raw['js_comp_manual_link'] ?? '';
                }
            }
            
            // Fallback to AmazonListingStatus if NR not set from AmazonDataView
            if (!isset($row['NR']) || $row['NR'] === null) {
                $listingStatus = $nrListingStatuses->get($pm->sku);
                if ($listingStatus && $listingStatus->value) {
                    $listingValue = is_array($listingStatus->value) ? $listingStatus->value : [];
                    $row['NR'] = $listingValue['nr_req'] ?? null;
                    // Only set links from listing status if not already set from AmazonDataView
                    if (!isset($row['buyer_link'])) {
                        $row['buyer_link'] = $listingValue['buyer_link'] ?? null;
                    }
                    if (!isset($row['seller_link'])) {
                        $row['seller_link'] = $listingValue['seller_link'] ?? null;
                    }
                }
            }
            

            // If SPRICE is null or empty, use price as default and calculate SGPFT/SPFT/SROI
            if (empty($row['SPRICE']) && $price > 0) {
                $row['SPRICE'] = $price;
                $row['has_custom_sprice'] = false; // Flag to indicate using default price
                
                // Calculate SGPFT based on default price (using 0.80 for Amazon)
                $sgpft = round(
                    $price > 0 ? (($price * 0.80 - $ship - $lp) / $price) * 100 : 0,
                    2
                );
                $row['SGPFT'] = $sgpft;
                
                // Calculate SPFT = SGPFT - AD%
                $row['Spft%'] = round($sgpft - $row['AD%'], 2);
                
                // Calculate SROI = ((SPRICE * (0.80 - AD%/100) - ship - lp) / lp) * 100
                $adDecimal = $row['AD%'] / 100;
                $row['SROI'] = round(
                    $lp > 0 ? (($price * (0.80 - $adDecimal) - $ship - $lp) / $lp) * 100 : 0,
                    2
                );
            } else {
                $row['has_custom_sprice'] = true; // Flag to indicate custom SPRICE
                
                // Calculate SGPFT using custom SPRICE if not already set (using 0.80 for Amazon)
                if (empty($row['SGPFT'])) {
                    $sprice = floatval($row['SPRICE']);
                    $sgpft = round(
                        $sprice > 0 ? (($sprice * 0.80 - $ship - $lp) / $sprice) * 100 : 0,
                        2
                    );
                    $row['SGPFT'] = $sgpft;
                }
                
                // Calculate SPFT = SGPFT - AD%
                if (!empty($row['SGPFT'])) {
                    $row['Spft%'] = round($row['SGPFT'] - $row['AD%'], 2);
                }
                
                // Calculate SROI = ((SPRICE * (0.80 - AD%/100) - ship - lp) / lp) * 100
                if (!empty($row['SPRICE'])) {
                    $sprice = floatval($row['SPRICE']);
                    $adDecimal = $row['AD%'] / 100;
                    $row['SROI'] = round(
                        $lp > 0 ? (($sprice * (0.80 - $adDecimal) - $ship - $lp) / $lp) * 100 : 0,
                        2
                    );
                }
            }

            $row['image_path'] = $shopify->image_src ?? ($values['image_path'] ?? null);

            $result[] = (object) $row;
        }

        // Parent-wise grouping
        $groupedByParent = collect($result)->groupBy('Parent');
        $finalResult = [];

        foreach ($groupedByParent as $parent => $rows) {
            foreach ($rows as $row) {
                $finalResult[] = $row;
            }

            if (empty($parent)) {
                continue;
            }

            $sumRow = [
                '(Child) sku' => 'PARENT ' . $parent,
                'Parent' => $parent,
                'INV' => $rows->sum('INV'),
                'L30' => $rows->sum('L30'),
                'price' => '',
                'price_lmpa' => '',
                'A_L30' => $rows->sum('A_L30'),
                'Sess30' => $rows->sum('Sess30'),
                'CVR_L30' => '',
                'NRL' => '',
                'NRA' => '',
                'FBA' => null,
                'shopify_id' => null,
                'SPRICE' => '',
                'Spft%' => '',
                'SROI' => '',
                'SGPFT' => '',
                'ad_spend' => '',
                'Listed' => null,
                'Live' => null,
                'APlus' => null,
                'js_comp_manual_api_link' => '',
                'js_comp_manual_link' => '',
                'image_path' => '',
                'Total_pft' => round($rows->sum('Total_pft'), 2),
                'T_Sale_l30' => round($rows->sum('T_Sale_l30'), 2),
                'PFT_percentage' => '',
                'ROI_percentage' => '',
                'T_COGS' => round($rows->sum('T_COGS'), 2),
                'scout_data' => null,
                'percentage' => $percentage,
                'LP_productmaster' => '',
                'Ship_productmaster' => '',
                'kw_spend_L30' => round($rows->sum('kw_spend_L30'), 2),
                'pmt_spend_L30' => round($rows->sum('pmt_spend_L30'), 2),
                'AD_Spend_L30' => round($rows->sum('AD_Spend_L30'), 2),
                'AD%' => 0, // Parent summary AD% not calculated
                'GPFT%' => $rows->count() > 0 ? round($rows->avg('GPFT%'), 2) : 0,
                'PFT%' => $rows->count() > 0 ? round($rows->avg('PFT%'), 2) : 0,
                'is_parent_summary' => true,
                'ad_updates' => $adUpdates
            ];

            $finalResult[] = (object) $sumRow;
        }

        // Save PFT% and ROI_percentage to AmazonDataView value column after processing all rows
        foreach ($result as $row) {
            try {
                if (!isset($row->{'(Child) sku'}) || empty($row->{'(Child) sku'})) {
                    continue;
                }
                
                $sku = $row->{'(Child) sku'};
                
                // Skip parent rows
                if (strpos($sku, 'PARENT ') === 0) {
                    continue;
                }
                
                $amazonDataView = AmazonDataView::firstOrNew(['sku' => $sku]);
                $existing = is_array($amazonDataView->value)
                    ? $amazonDataView->value
                    : (json_decode($amazonDataView->value ?? '{}', true) ?? []);
                
                $merged = array_merge($existing, [
                    'PFT' => $row->{'PFT%'} ?? 0,
                    'ROI' => $row->ROI_percentage ?? 0,
                    'GPFT' => $row->{'GPFT%'} ?? 0,
                    'AD_percent' => $row->{'AD%'} ?? 0,
                ]);
                
                $amazonDataView->value = $merged;
                $amazonDataView->save();
            } catch (\Exception $e) {
                // Continue processing other SKUs if one fails
                Log::error('Error saving PFT/ROI for SKU: ' . ($sku ?? 'unknown') . ' - ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $finalResult,
            'status' => 200,
        ]);
    }



    public function updateAllAmazonSkus(Request $request)
    {
        try {
            $type = $request->input('type');
            $value = $request->input('value');

            // Current record fetch
            $marketplace = MarketplacePercentage::where('marketplace', 'Amazon')->first();

            $percent = $marketplace->percentage ?? 0;
            $adUpdates = $marketplace->ad_updates ?? 0;

            // Handle percentage update
            if ($type === 'percentage') {
                if (!is_numeric($value) || $value < 0 || $value > 100) {
                    return response()->json(['status' => 400, 'message' => 'Invalid percentage value'], 400);
                }
                $percent = $value;
            }

            // Handle ad_updates update
            if ($type === 'ad_updates') {
                if (!is_numeric($value) || $value < 0) {
                    return response()->json(['status' => 400, 'message' => 'Invalid ad_updates value'], 400);
                }
                $adUpdates = $value;
            }

            // Save both fields
            $marketplace = MarketplacePercentage::updateOrCreate(
                ['marketplace' => 'Amazon'],
                [
                    'percentage' => $percent,
                    'ad_updates' => $adUpdates,
                ]
            );

            // Clear the cache
            Cache::forget('amazon_marketplace_percentage');
            Cache::forget('amazon_marketplace_ad_updates');

            return response()->json([
                'status' => 200,
                'message' => ucfirst($type) . ' updated successfully!',
                'data' => [
                    'percentage' => $marketplace->percentage,
                    'ad_updates' => $marketplace->ad_updates
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error updating Amazon marketplace values',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function saveNrToDatabase(Request $request) {
        $sku = $request->input('sku');
        $nr = $request->input('nr');     
        $fbaInput = $request->input('fba');   
        $spend = $request->input('spend');    
        $tpft = $request->input('tpft');      
        $spend_l30 = $request->input('spend_l30');

        if (!$sku) {
            return response()->json(['error' => 'SKU is required.'], 400);
        }

        // Fetch or create the record
        $amazonDataView = \App\Models\AmazonDataView::firstOrNew(['sku' => $sku]);

        // Decode existing value JSON
        $existing = is_array($amazonDataView->value)
            ? $amazonDataView->value
            : (json_decode($amazonDataView->value ?? '{}', true));

        // Handle NR - accept NRL, REQ, or JSON format with NR/REQ (for backward compatibility)
        if ($nr !== null) {
            $nrValue = $nr;
            
            // Handle JSON format from frontend: {"NR":"NR"} or {"NR":"REQ"}
            if (is_string($nr) && (strpos($nr, '{') === 0 || strpos($nr, '[') === 0)) {
                $decoded = json_decode($nr, true);
                if (is_array($decoded) && isset($decoded['NR'])) {
                    $nrValue = $decoded['NR']; // Extract 'NR' or 'REQ' from JSON
                }
            }
            
            // Map values: 'NR' -> 'NRL', 'REQ' -> 'REQ', 'NRL' -> 'NRL'
            if ($nrValue === 'NR' || $nrValue === 'NRL') {
                $existing['NRL'] = 'NRL';
                $existing['NRA'] = ''; // Clear NRA
            } elseif ($nrValue === 'REQ') {
                $existing['NRL'] = 'REQ';
                $existing['NRA'] = ''; // Clear NRA
            } elseif ($nrValue === '') {
                // Clear both when empty
                $existing['NRL'] = '';
                $existing['NRA'] = '';
            }
            // Ignore any other values (numbers, etc.)
        }

        // Handle FBA
        if ($fbaInput) {
            $fba = is_array($fbaInput) ? $fbaInput : json_decode($fbaInput, true);
            if (!is_array($fba) || !isset($fba['FBA'])) {
                return response()->json(['error' => 'Invalid FBA format.'], 400);
            }
            $existing['FBA'] = $fba['FBA'];
        }

        // Handle Spend
        if (!is_null($spend)) {
            $existing['Spend'] = $spend;
        }

        // Handle tpft (total profit percentage)
        if (!is_null($tpft)) {
            $existing['TPFT'] = $tpft;
        }

        // Handle spend_l30
        if (!is_null($spend_l30)) {
            $existing['Spend_L30'] = $spend_l30;
        }

        $newValueJson = json_encode($existing);
        if ($amazonDataView->value !== $newValueJson) {
            $amazonDataView->value = $existing;
            $amazonDataView->save();
        }

        // Create a user-friendly message based on what was updated
        $message = "Data updated successfully";
        if ($nr !== null) {
            $message = $nr === 'NRL' ? "NRL updated" : ($nr === 'REQ' ? "REQ updated" : "NR cleared");
        }

        return response()->json(['success' => true, 'data' => $amazonDataView, 'message' => $message]);
    }

    public function saveSpriceToDatabase(Request $request)
    {
        Log::info('Saving Amazon pricing data', $request->all());
        $sku = strtoupper($request->input('sku'));
        $sprice = $request->input('sprice');

        if (!$sku || !$sprice) {
            return response()->json(['error' => 'SKU and sprice are required.'], 400);
        }

        // Get current marketplace percentage
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 1;
        Log::info('Using percentage', ['percentage' => $percentage]);

        // Get ProductMaster for lp and ship
        $pm = ProductMaster::where('sku', $sku)->first();
        if (!$pm) {
            return response()->json(['error' => 'SKU not found in product master.'], 404);
        }

        // Extract lp and ship
        $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
        $lp = 0;
        foreach ($values as $k => $v) {
            if (strtolower($k) === 'lp') {
                $lp = floatval($v);
                break;
            }
        }
        if ($lp === 0 && isset($pm->lp)) {
            $lp = floatval($pm->lp);
        }

        $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($pm->ship) ? floatval($pm->ship) : 0);
        Log::info('LP and Ship', ['lp' => $lp, 'ship' => $ship]);

        // Calculate SGPFT first (using 0.80 for Amazon)
        $spriceFloat = floatval($sprice);
        $sgpft = $spriceFloat > 0 ? round((($spriceFloat * 0.80 - $ship - $lp) / $spriceFloat) * 100, 2) : 0;
        
        // Get AD% from the product
        $adPercent = 0;
        $amazonDatasheet = AmazonDatasheet::where('sku', $sku)->first();
        
        if ($amazonDatasheet) {
            $price = floatval($amazonDatasheet->price ?? 0);
            $unitsL30 = floatval($amazonDatasheet->units_ordered_l30 ?? 0);
            
            $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', 'L30')
                ->get()
                ->first(function ($item) use ($sku) {
                    $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                    $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                    return $campaignName === $cleanSku;
                });
            
            $amazonSpCampaignReportsPtL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', 'L30')
                ->get()
                ->first(function ($item) use ($sku) {
                    $cleanName = strtoupper(trim($item->campaignName));
                    return (str_ends_with($cleanName, $sku . ' PT') || str_ends_with($cleanName, $sku . ' PT.'));
                });
            
            $kw_spend_l30 = $amazonSpCampaignReportsL30->cost ?? 0;
            $pmt_spend_l30 = $amazonSpCampaignReportsPtL30->cost ?? 0;
            $totalSpend = $kw_spend_l30 + $pmt_spend_l30;
            
            $totalRevenue = $price * $unitsL30;
            $adPercent = $totalRevenue > 0 ? ($totalSpend / $totalRevenue) * 100 : 0;
        }
        
        // SPFT = SGPFT - AD%
        $spft = round($sgpft - $adPercent, 2);
        
        // SROI = ((SPRICE * (0.80 - AD%/100) - ship - lp) / lp) * 100
        $adDecimal = $adPercent / 100;
        $sroi = round(
            $lp > 0 ? (($spriceFloat * (0.80 - $adDecimal) - $ship - $lp) / $lp) * 100 : 0,
            2
        );
        
        Log::info('Calculated values', ['sprice' => $spriceFloat, 'sgpft' => $sgpft, 'ad_percent' => $adPercent, 'spft' => $spft, 'sroi' => $sroi]);

        $amazonDataView = AmazonDataView::firstOrNew(['sku' => $sku]);

        // Decode value column safely
        $existing = is_array($amazonDataView->value)
            ? $amazonDataView->value
            : (json_decode($amazonDataView->value ?? '{}', true) ?? []);

        // Merge new sprice data
        $merged = array_merge($existing, [
            'SPRICE' => $spriceFloat,
            'SPFT' => $spft,
            'SROI' => $sroi,
            'SGPFT' => $sgpft,
        ]);

        $amazonDataView->value = $merged;
        $amazonDataView->save();
        Log::info('Data saved successfully', ['sku' => $sku]);

        return response()->json([
            'message' => 'Data saved successfully.',
            'data' => $spriceFloat,
            'spft_percent' => $spft,
            'sroi_percent' => $sroi,
            'sgpft_percent' => $sgpft
        ]);
    }

    /**
     * Clear SPRICE for selected SKUs
     */
    public function clearAmazonSprice(Request $request)
    {
        try {
            $updates = $request->input('updates', []);
            
            if (empty($updates)) {
                return response()->json(['error' => 'No SKUs provided'], 400);
            }

            $clearedCount = 0;
            
            foreach ($updates as $update) {
                $sku = strtoupper($update['sku'] ?? '');
                
                if (empty($sku)) {
                    continue;
                }
                
                // Find the amazon_data_view record
                $amazonDataView = AmazonDataView::where('sku', $sku)->first();
                
                if ($amazonDataView) {
                    // Decode value column safely
                    $existing = is_array($amazonDataView->value)
                        ? $amazonDataView->value
                        : (json_decode($amazonDataView->value ?? '{}', true) ?? []);
                    
                    // Clear SPRICE related fields
                    $existing['SPRICE'] = null;
                    $existing['SPFT'] = null;
                    $existing['SROI'] = null;
                    $existing['SGPFT'] = null;
                    $existing['SPRICE_STATUS'] = null;
                    
                    $amazonDataView->value = $existing;
                    $amazonDataView->save();
                    
                    $clearedCount++;
                }
            }
            
            Log::info('SPRICE cleared successfully', ['count' => $clearedCount]);
            
            return response()->json([
                'success' => true,
                'message' => "SPRICE cleared for {$clearedCount} SKU(s)",
                'cleared_count' => $clearedCount
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error clearing SPRICE', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to clear SPRICE: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Apply Amazon price with automatic verification
     * 
     * IMPORTANT FIX: The AmazonSpApiService now includes automatic verification
     * to ensure prices are actually updated on Amazon. It will:
     * 1. Update the price on Amazon
     * 2. Wait 1 second and verify the price was applied
     * 3. If verification fails, retry with fresh token (up to 2 attempts)
     * 4. Return error only if price truly wasn't applied after verification
     * 
     * This fixes the issue where Amazon API returns success but doesn't actually
     * apply the price on the first attempt (often due to token refresh timing).
     */
    public function applyAmazonPrice(Request $request)
    {
        // Validate and sanitize inputs
        $sku = trim($request->input('sku', ''));
        $price = $request->input('price');

        // Validate SKU
        if (empty($sku)) {
            return response()->json([
                'errors' => [[
                    'code' => 'InvalidInput',
                    'message' => 'SKU is required and cannot be empty.'
                ]]
            ], 400);
        }

        $sku = strtoupper($sku);

        // Validate price
        if ($price === null || $price === '') {
            $this->saveSpriceStatus($sku, 'error');
            return response()->json([
                'errors' => [[
                    'code' => 'InvalidInput',
                    'message' => 'Price is required.'
                ]]
            ], 400);
        }

        // Convert to float and validate
        $priceFloat = is_numeric($price) ? (float) $price : null;
        
        if ($priceFloat === null || $priceFloat <= 0) {
            $this->saveSpriceStatus($sku, 'error');
            return response()->json([
                'errors' => [[
                    'code' => 'InvalidInput',
                    'message' => 'Price must be a valid number greater than 0.'
                ]]
            ], 400);
        }

        // Validate price range (reasonable bounds: 0.01 to 999999.99)
        if ($priceFloat < 0.01 || $priceFloat > 999999.99) {
            $this->saveSpriceStatus($sku, 'error');
            return response()->json([
                'errors' => [[
                    'code' => 'InvalidInput',
                    'message' => 'Price must be between $0.01 and $999,999.99.'
                ]]
            ], 400);
        }

        // Round price to 2 decimal places
        $priceFloat = round($priceFloat, 2);

        try {
            $service = new AmazonSpApiService();
            $result = $service->updateAmazonPriceUS($sku, $priceFloat);

            // Check if the response indicates errors
            if (isset($result['errors']) && !empty($result['errors'])) {
                // Save error status
                $this->saveSpriceStatus($sku, 'error');
                
                // Log the error for debugging
                Log::error('Amazon price update failed', [
                    'sku' => $sku,
                    'price' => $priceFloat,
                    'errors' => $result['errors']
                ]);
                
                return response()->json($result);
            }

            // Check if response indicates success (pushed)
            // If no errors, consider it pushed initially
            $this->saveSpriceStatus($sku, 'pushed');
            
            Log::info('Amazon price update successful', [
                'sku' => $sku,
                'price' => $priceFloat
            ]);
            
            return response()->json($result);
        } catch (\Exception $e) {
            // Save error status
            $this->saveSpriceStatus($sku, 'error');
            Log::error('Exception in applyAmazonPrice', [
                'sku' => $sku,
                'price' => $priceFloat,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'errors' => [[
                    'code' => 'Exception',
                    'message' => 'An error occurred: ' . $e->getMessage()
                ]]
            ], 500);
        }
    }

    /**
     * Save SPRICE status to database
     * Status: 'pushed', 'applied', 'error'
     */
    private function saveSpriceStatus($sku, $status)
    {
        try {
            $amazonDataView = AmazonDataView::firstOrNew(['sku' => $sku]);
            
            // Decode value column safely
            $existing = is_array($amazonDataView->value)
                ? $amazonDataView->value
                : (json_decode($amazonDataView->value ?? '{}', true) ?? []);
            
            // Save status
            $existing['SPRICE_STATUS'] = $status;
            $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();
            
            $amazonDataView->value = $existing;
            $amazonDataView->save();
            
            Log::info('SPRICE status saved', ['sku' => $sku, 'status' => $status]);
        } catch (\Exception $e) {
            Log::error('Failed to save SPRICE status', [
                'sku' => $sku,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update SPRICE status manually
     */
    public function updateSpriceStatus(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'status' => 'required|in:pushed,applied,error'
        ]);

        $sku = strtoupper(trim($request->input('sku')));
        $status = $request->input('status');

        $this->saveSpriceStatus($sku, $status);

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully',
            'status' => $status
        ]);
    }

    public function amazonPriceIncreaseDecrease(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        $adUpdates = Cache::remember('amazon_marketplace_ad_updates', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
            return $marketplaceData ? $marketplaceData->ad_updates : 0; // Default to 0 if not set
        });

        return view('market-places.amazon_pricing_increase_decrease', [
            'mode' => $mode,
            'demo' => $demo,
            'amazonPercentage' => $percentage,
            'amazonAdUpdates' => $adUpdates
        ]);
    }

    public function amazonPriceIncrease(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        $adUpdates = Cache::remember('amazon_marketplace_ad_updates', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
            return $marketplaceData ? $marketplaceData->ad_updates : 0; // Default to 0 if not set
        });

        return view('market-places.amazon_pricing_increase', [
            'mode' => $mode,
            'demo' => $demo,
            'amazonPercentage' => $percentage,
            'amazonAdUpdates' => $adUpdates
        ]);
    }


    public function saveManualLink(Request $request)
    {
        $sku = $request->input('sku');
        $type = $request->input('type');
        $value = $request->input('value');

        if (!$sku || !$type) {
            return response()->json(['error' => 'SKU and type are required.'], 400);
        }

        $amazonDataView = AmazonDataView::firstOrNew(['sku' => $sku]);

        // Decode existing value array
        $existing = is_array($amazonDataView->value)
            ? $amazonDataView->value
            : (json_decode($amazonDataView->value, true) ?: []);

        $existing[$type] = $value;

        $amazonDataView->value = $existing;
        $amazonDataView->save();

        return response()->json(['message' => 'Manual link saved successfully.']);
    }

    public function saveLowProfit(Request $request)
    {
        $count = $request->input('count');

        $channel = ChannelMaster::where('channel', 'Amazon')->first();

        if (!$channel) {
            return response()->json(['success' => false, 'message' => 'Channel not found'], 404);
        }

        $channel->red_margin = $count;
        $channel->save();

        return response()->json(['success' => true]);
    }

    public function updateListedLive(Request $request)
    {
        $request->validate([
            'sku'   => 'required|string',
            'field' => 'required|in:Listed,Live,APlus',
            'value' => 'required|boolean' // validate as boolean
        ]);

        // Find or create the product without overwriting existing value
        $product = AmazonDataView::firstOrCreate(
            ['sku' => $request->sku],
            ['value' => []]
        );

        // Decode current value (ensure it's an array)
        $currentValue = is_array($product->value)
            ? $product->value
            : (json_decode($product->value, true) ?? []);

        // Store as actual boolean
        $currentValue[$request->field] = filter_var($request->value, FILTER_VALIDATE_BOOLEAN);

        // Save back to DB
        $product->value = $currentValue;
        $product->save();

        return response()->json(['success' => true]);
    }

    public function importAmazonAnalytics(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        try {
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathName());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Clean headers
            $headers = array_map(function ($header) {
                return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $header)));
            }, $rows[0]);

            unset($rows[0]);

            $allSkus = [];
            foreach ($rows as $row) {
                if (!empty($row[0])) {
                    $allSkus[] = $row[0];
                }
            }

            $existingSkus = ProductMaster::whereIn('sku', $allSkus)
                ->pluck('sku')
                ->toArray();

            $existingSkus = array_flip($existingSkus);

            $importCount = 0;
            foreach ($rows as $index => $row) {
                if (empty($row[0])) { // Check if SKU is empty
                    continue;
                }

                // Ensure row has same number of elements as headers
                $rowData = array_pad(array_slice($row, 0, count($headers)), count($headers), null);
                $data = array_combine($headers, $rowData);

                if (!isset($data['sku']) || empty($data['sku'])) {
                    continue;
                }

                // Only import SKUs that exist in product_masters (in-memory check)
                if (!isset($existingSkus[$data['sku']])) {
                    continue;
                }

                // Prepare values array
                $values = [];

                // Handle boolean fields
                if (isset($data['listed'])) {
                    $values['Listed'] = filter_var($data['listed'], FILTER_VALIDATE_BOOLEAN);
                }

                if (isset($data['live'])) {
                    $values['Live'] = filter_var($data['live'], FILTER_VALIDATE_BOOLEAN);
                }

                // Update or create record
                AmazonDataView::updateOrCreate(
                    ['sku' => $data['sku']],
                    ['value' => $values]
                );

                $importCount++;
            }

            return back()->with('success', "Successfully imported $importCount records!");
        } catch (\Exception $e) {
            return back()->with('error', 'Error importing file: ' . $e->getMessage());
        }
    }

    public function exportAmazonAnalytics()
    {
        $amazonData = AmazonDataView::all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Data Rows
        $rowIndex = 2;
        foreach ($amazonData as $data) {
            $values = is_array($data->value)
                ? $data->value
                : (json_decode($data->value, true) ?? []);

            $sheet->fromArray([
                $data->sku,
                isset($values['Listed']) ? ($values['Listed'] ? 'TRUE' : 'FALSE') : 'FALSE',
                isset($values['Live']) ? ($values['Live'] ? 'TRUE' : 'FALSE') : 'FALSE',
            ], NULL, 'A' . $rowIndex);

            $rowIndex++;
        }

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(10);

        // Output Download
        $fileName = 'Amazon_Analytics_Export_' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function downloadSample()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Sample Data
        $sampleData = [
            ['SKU001', 'TRUE', 'FALSE'],
            ['SKU002', 'FALSE', 'TRUE'],
            ['SKU003', 'TRUE', 'TRUE'],
        ];

        $sheet->fromArray($sampleData, NULL, 'A2');

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(10);

        // Output Download
        $fileName = 'Amazon_Analytics_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function amazonTabulatorView(Request $request)
    {
        return view("market-places.amazon_tabulator_view");
    }

    public function amazonDataJson(Request $request)
    {
        try {
            $response = $this->getViewAmazonData($request);
            $data = json_decode($response->getContent(), true);
            
            return response()->json($data['data'] ?? []);
        } catch (\Exception $e) {
            Log::error('Error fetching Amazon data for Tabulator: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch data'], 500);
        }
    }

    public function getAmazonColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "amazon_sales_tabulator_column_visibility_{$userId}";
        
        $visibility = Cache::get($key, []);
        
        return response()->json($visibility);
    }

    /**
     * Refresh links for Amazon listings (quick method using existing ASIN data)
     */
    public function refreshAmazonLinks(Request $request)
    {
        try {
            $sku = $request->input('sku');
            $updateAll = $request->input('update_all', false);

            if ($updateAll) {
                // Update all SKUs using ASIN from amazon_datsheets
                $skus = ProductMaster::whereNull('deleted_at')
                    ->whereNotNull('sku')
                    ->where('sku', '!=', '')
                    ->where('sku', 'NOT LIKE', '%PARENT%')
                    ->pluck('sku')
                    ->unique()
                    ->values();

                $updated = 0;
                $skipped = 0;

                foreach ($skus as $currentSku) {
                    $result = $this->updateLinksFromAsin($currentSku);
                    if ($result['success']) {
                        $updated++;
                    } else {
                        $skipped++;
                    }
                }

                return response()->json([
                    'status' => 'success',
                    'message' => "Updated {$updated} SKUs, {$skipped} skipped (no ASIN)",
                    'updated' => $updated,
                    'skipped' => $skipped
                ]);
            } else {
                if (!$sku) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'SKU is required'
                    ], 400);
                }

                $result = $this->updateLinksFromAsin($sku);
                
                if ($result['success']) {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Links updated successfully',
                        'data' => $result['data']
                    ]);
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => $result['message']
                    ], 400);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error refreshing Amazon links', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to refresh links: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update links from ASIN in amazon_datsheets (fast method, no API calls)
     */
    private function updateLinksFromAsin($sku)
    {
        try {
            $amazonSheet = AmazonDatasheet::where('sku', $sku)->first();
            
            if (!$amazonSheet || !$amazonSheet->asin) {
                return [
                    'success' => false,
                    'message' => 'ASIN not found for this SKU'
                ];
            }

            $asin = $amazonSheet->asin;
            $buyerLink = "https://www.amazon.com/dp/{$asin}";
            $sellerLink = "https://sellercentral.amazon.com/inventory/ref=xx_invmgr_dnav_xx?asin={$asin}";

            // Update links in amazon_data_view
            $status = AmazonDataView::where('sku', $sku)->first();
            $existing = $status ? $status->value : [];

            $existing['buyer_link'] = $buyerLink;
            $existing['seller_link'] = $sellerLink;

            AmazonDataView::updateOrCreate(
                ['sku' => $sku],
                ['value' => $existing]
            );

            return [
                'success' => true,
                'data' => [
                    'sku' => $sku,
                    'asin' => $asin,
                    'buyer_link' => $buyerLink,
                    'seller_link' => $sellerLink
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Error updating links from ASIN', [
                'sku' => $sku,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    public function exportAmazonPricingCVR(Request $request)
    {
        return $this->amazonDataService->exportPricingCVRToCSV($request);
    }

    public function exportAmazonSpriceUpload(Request $request)
    {
        return $this->amazonDataService->exportSpriceForUpload($request);
    }

    public function downloadAmazonRatingsSample(Request $request)
    {
        return $this->amazonDataService->downloadRatingsSampleTemplate($request);
    }

    public function importAmazonRatings(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt'
        ]);

        try {
            $file = $request->file('file');
            $content = file_get_contents($file->getRealPath());
            $content = preg_replace('/^\x{FEFF}/u', '', $content); // Remove BOM
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
            $csvData = array_map('str_getcsv', explode("\n", $content));
            $csvData = array_filter($csvData, function($row) {
                return count($row) > 0 && !empty(trim(implode('', $row)));
            });
            $header = array_shift($csvData); // Remove header

            $imported = 0;
            $skipped = 0;

            foreach ($csvData as $row) {
                $row = array_map('trim', $row); // Trim all elements
                if (empty($row) || count($row) < 1 || empty($row[0])) {
                    $skipped++;
                    continue;
                }

                if (count($row) !== count($header)) {
                    $skipped++;
                    continue;
                }

                $sku = strtoupper($row[0]);
                $rowData = array_combine($header, $row);
                unset($rowData['sku']);

                AmazonFbmManual::create([
                    'sku' => $sku,
                    'data' => json_encode($rowData)
                ]);

                $imported++;
            }

            return response()->json(['success' => 'Imported ' . $imported . ' ratings successfully' . ($skipped > 0 ? ', skipped ' . $skipped . ' invalid rows' : '')]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error importing ratings: ' . $e->getMessage()]);
        }
    }

    public function getMetricsHistory(Request $request)
    {
        $days = $request->input('days', 7); // Default to last 7 days
        $sku = $request->input('sku'); // Optional SKU filter
        $skus = $request->input('skus'); // Optional array of SKUs to filter (for INV > 0)
        
        // Ensure minimum 7 days if pulling from today
        $minDays = 7;
        if ($days < $minDays) {
            $days = $minDays;
        }
        
        $startDate = \Carbon\Carbon::today()->subDays($days - 1); // -1 to include today
        $endDate = \Carbon\Carbon::today();
        
        $chartData = [];
        $dataByDate = []; // Store data by date for filling gaps
        
        try {
            // Try to use the new table for JSON format data
            $query = \App\Models\AmazonSkuDailyData::where('record_date', '>=', $startDate)
                ->where('record_date', '<=', $endDate)
                ->orderBy('record_date', 'asc');
            
            // If SKU is provided, return data for specific SKU
            if ($sku) {
                $metricsData = $query->where('sku', strtoupper(trim($sku)))->get();
                
                foreach ($metricsData as $record) {
                    $data = $record->daily_data;
                    $dateKey = \Carbon\Carbon::parse($record->record_date)->format('Y-m-d');
                    $dataByDate[$dateKey] = [
                        'date' => $dateKey,
                        'date_formatted' => \Carbon\Carbon::parse($record->record_date)->format('M d'),
                        'price' => round($data['price'] ?? 0, 2),
                        'views' => $data['views'] ?? 0,
                        'cvr_percent' => round($data['cvr_percent'] ?? 0, 2),
                        'ad_percent' => round($data['ad_percent'] ?? 0, 2),
                        'a_l30' => round($data['a_l30'] ?? 0, 0), // Amazon L30 sold units
                    ];
                }
            } else {
                // If SKUs array is provided, filter by those SKUs (for INV > 0 filtering)
                if ($skus) {
                    $skuArray = is_string($skus) ? json_decode($skus, true) : $skus;
                    if (is_array($skuArray) && count($skuArray) > 0) {
                        $query->whereIn('sku', array_map(function($sku) {
                            return strtoupper(trim($sku));
                        }, $skuArray));
                    }
                }
                
                // Aggregate data for filtered SKUs
                $metricsData = $query->get()->groupBy('record_date');
                
                foreach ($metricsData as $date => $records) {
                    $dateKey = \Carbon\Carbon::parse($date)->format('Y-m-d');
                    
                    // Calculate weighted average price (same as summary badge: price * a_l30 / sum a_l30)
                    $totalWeightedPrice = 0;
                    $totalL30 = 0;
                    foreach ($records as $record) {
                        $price = floatval($record->daily_data['price'] ?? 0);
                        $aL30 = floatval($record->daily_data['a_l30'] ?? 0);
                        $totalWeightedPrice += $price * $aL30;
                        $totalL30 += $aL30;
                    }
                    $avgPrice = $totalL30 > 0 ? ($totalWeightedPrice / $totalL30) : 0;
                    
                    $dataByDate[$dateKey] = [
                        'date' => $dateKey,
                        'date_formatted' => \Carbon\Carbon::parse($date)->format('M d'),
                        'avg_price' => round($avgPrice, 2),
                        'total_views' => $records->sum(function($r) { return $r->daily_data['views'] ?? 0; }),
                        'avg_cvr_percent' => round($records->avg(function($r) { return $r->daily_data['cvr_percent'] ?? 0; }), 2),
                        'avg_ad_percent' => round($records->avg(function($r) { return $r->daily_data['ad_percent'] ?? 0; }), 2),
                    ];
                }
            }
            
            // If no data found in new table, try fallback
            if (empty($dataByDate)) {
                throw new \Exception('No data in new table, trying fallback');
            }
            
        } catch (\Exception $e) {
            // Fallback: Return empty data and let the frontend handle it
            Log::info('No Amazon daily metrics data available. Historical data will be populated by metrics collection command.');
        }

        // Fill in missing dates with zero values to ensure at least 7 days
        $currentDate = \Carbon\Carbon::parse($startDate);
        $today = \Carbon\Carbon::today();
        
        while ($currentDate->lte($today)) {
            $dateKey = $currentDate->format('Y-m-d');
            
            if (!isset($dataByDate[$dateKey])) {
                // Fill missing date with zero values
                if ($sku) {
                    $dataByDate[$dateKey] = [
                        'date' => $dateKey,
                        'date_formatted' => $currentDate->format('M d'),
                        'price' => 0,
                        'views' => 0,
                        'cvr_percent' => 0,
                        'ad_percent' => 0,
                    ];
                } else {
                    $dataByDate[$dateKey] = [
                        'date' => $dateKey,
                        'date_formatted' => $currentDate->format('M d'),
                        'avg_price' => 0,
                        'total_views' => 0,
                        'avg_cvr_percent' => 0,
                        'avg_ad_percent' => 0,
                    ];
                }
            }
            
            $currentDate->addDay();
        }
        
        // Sort by date and convert to array
        ksort($dataByDate);
        $chartData = array_values($dataByDate);

        return response()->json($chartData);
    }

    public function saveAmazonColumnVisibility(Request $request)
    {
        $userId = auth()->id();
        $key = "amazon_sales_tabulator_column_visibility_{$userId}";
        $visibility = $request->input('visibility', []);
        Cache::put($key, $visibility, now()->addDays(30));
        return response()->json(['success' => true]);
    }

    public function updateAmazonRating(Request $request)
    {
        $sku = strtoupper(trim($request->input('sku')));
        $rating = $request->input('rating');

        // Validate rating
        if (!is_numeric($rating) || $rating < 0 || $rating > 5) {
            return response()->json([
                'success' => false,
                'error' => 'Rating must be a number between 0 and 5'
            ], 400);
        }

        try {
            // Find or create the manual data record
            $manual = AmazonFbmManual::firstOrNew(['sku' => $sku]);
            
            // Decode existing data
            $data = $manual->data ? json_decode($manual->data, true) : [];
            
            // Update rating
            $data['rating'] = floatval($rating);
            
            // Save
            $manual->data = json_encode($data);
            $manual->save();

            return response()->json([
                'success' => true,
                'message' => 'Rating updated successfully',
                'rating' => floatval($rating)
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating Amazon rating: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error updating rating'
            ], 500);
        }
    }
}
