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
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class OverallAmazonController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
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

        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku', 'fba');

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();

        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 1; 
        $adUpdates  = $marketplaceData ? $marketplaceData->ad_updates : 0;   

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);

            if (str_starts_with($sku, 'PARENT ')) {
                continue;
            }

            $parent = $pm->parent;
            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $row = [];
            $row['Parent'] = $parent;
            $row['(Child) sku'] = $pm->sku;

            if ($amazonSheet) {
                $row['A_L30'] = $amazonSheet->units_ordered_l30;
                $row['Sess30'] = $amazonSheet->sessions_l30;
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
            if (!empty($parentKey) && $jungleScoutData->has($parentKey)) {
                $row['scout_data'] = $jungleScoutData[$parentKey];
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

            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];

                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }

                if (is_array($raw)) {
                    $row['NRL'] = $raw['NRL'] ?? null;
                    $row['NRA'] = $raw['NRA'] ?? null;
                    $row['FBA'] = $raw['FBA'] ?? null;
                    $row['shopify_id'] = $shopify->id ?? null;
                    $row['SPRICE'] = $raw['SPRICE'] ?? null;
                    $row['Spft%'] = $raw['SPFT'] ?? null;
                    $row['SROI'] = $raw['SROI'] ?? null;
                    $row['ad_spend'] = $raw['Spend_L30'] ?? null;
                    $row['Listed'] = isset($raw['Listed']) ? filter_var($raw['Listed'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['Live'] = isset($raw['Live']) ? filter_var($raw['Live'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['APlus'] = isset($raw['APlus']) ? filter_var($raw['APlus'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['js_comp_manual_api_link'] = $raw['js_comp_manual_api_link'] ?? '';
                    $row['js_comp_manual_link'] = $raw['js_comp_manual_link'] ?? '';
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
                'OV_L30' => $rows->sum('OV_L30'),
                'AVG_Price' => null,
                'MSRP' => null,
                'MAP' => null,
                'is_parent_summary' => true,
                'ad_updates' => $adUpdates
            ];

            $finalResult[] = (object) $sumRow;
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
        $nrInput = $request->input('nr');     
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

        // Handle NR
        if ($nrInput) {
            $nr = is_array($nrInput) ? $nrInput : json_decode($nrInput, true);
            if (!is_array($nr)) {
                return response()->json(['error' => 'Invalid NR format.'], 400);
            }

            foreach (['NRL', 'NRA'] as $key) {
                if (isset($nr[$key])) {
                    $existing[$key] = $nr[$key];
                }
            }
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

        return response()->json(['success' => true, 'data' => $amazonDataView]);
    }

    public function saveSpriceToDatabase(Request $request)
    {
        $sku = $request->input('sku');
        $price = $request["sprice"];
        $sID = env('AMAZON_SELLER_ID');

        $spriceData = $request->only(['sprice', 'spft_percent', 'sroi_percent']);

        if (!$sku || !$spriceData['sprice']) {
            return response()->json(['error' => 'SKU and sprice are required.'], 400);
        }

        $amazonDataView = AmazonDataView::firstOrNew(['sku' => $sku]);

        // Decode value column safely
        $existing = is_array($amazonDataView->value) ? $amazonDataView->value : (json_decode($amazonDataView->value, true) ?: []);

        // $changeAmzPrice = UpdateAmazonSPriceJob::dispatch($sku, $price)->delay(now()->addMinutes(3));

        // Merge new sprice data
        $merged = array_merge($existing, [
            'SPRICE' => $spriceData['sprice'],
            'SPFT' => $spriceData['spft_percent'],
            'SROI' => $spriceData['sroi_percent'],
        ]);

        $amazonDataView->value = $merged;
        $amazonDataView->save();

        return response()->json(['message' => 'Data saved successfully.', 'data' => $price]);
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
            'field' => 'required|in:Listed,Live',
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
}
