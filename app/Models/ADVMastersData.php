<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ADVMastersDailyData;
use Illuminate\Support\Facades\DB;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\AmazonDatasheet;
use App\Models\EbayMetric;
use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayPriorityReport;
use App\Models\EbayGeneralReport;
use App\Models\Ebay2GeneralReport;
use App\Models\Ebay3GeneralReport;
use App\Models\Ebay3PriorityReport;
use App\Models\AmazonSpCampaignReport;
use App\Models\AmazonDataView;
use App\Models\WalmartProductSheet;

class ADVMastersData extends Model
{
    use HasFactory;

    protected $table = 'adv_masters_datas';
    protected $primaryKey = 'adv_masters_data_id';  
    public $timestamps = false;

    protected $fillable = [
        'channel',
        'spent',
        'clicks',
        'ad_sales',
        'ad_sold',
        'missing_ads',
        'l30_sales'
    ];

    /**
     * Match campaign by SKU first, then fallback to PARENT_SKU (only for parent rows).
     * PARENT_SKU fallback is ONLY for parent rows to avoid double-counting when parent
     * campaign would match multiple children.
     */
    public static function matchKwCampaign($collection, string $sku, ?string $parent, bool $allowParentFallback = false): ?object
    {
        $cleanSku = strtoupper(trim(rtrim($sku, '.')));
        $match = $collection->first(function ($item) use ($cleanSku) {
            $cn = strtoupper(trim(rtrim($item->campaignName ?? '', '.')));
            return $cn === $cleanSku;
        });
        if ($match) {
            return $match;
        }
        if ($allowParentFallback && $parent) {
            $cleanParent = strtoupper(trim(rtrim($parent, '.')));
            return $collection->first(function ($item) use ($cleanParent) {
                $cn = strtoupper(trim(rtrim($item->campaignName ?? '', '.')));
                return $cn === $cleanParent;
            }) ?: null;
        }
        return null;
    }

    /**
     * Match PT campaign: try SKU first, then PARENT_SKU (only when allowParentFallback).
     * @param bool $requireEnabled For missing-ads we check existence, for metrics we require ENABLED
     * @param bool $allowParentFallback Only true for parent rows to avoid double-counting
     */
    public static function matchPtCampaign($collection, string $sku, ?string $parent, bool $requireEnabled = true, bool $allowParentFallback = false): ?object
    {
        $cleanSku = strtoupper(trim($sku));
        $match = $collection->first(function ($item) use ($cleanSku, $requireEnabled) {
            $cn = strtoupper(trim($item->campaignName ?? ''));
            $ok = (str_ends_with($cn, $cleanSku . ' PT') || str_ends_with($cn, $cleanSku . ' PT.'));
            return $ok && (!$requireEnabled || strtoupper($item->campaignStatus ?? '') === 'ENABLED');
        });
        if ($match) {
            return $match;
        }
        if ($allowParentFallback && $parent) {
            $cleanParent = strtoupper(trim($parent));
            return $collection->first(function ($item) use ($cleanParent, $requireEnabled) {
                $cn = strtoupper(trim($item->campaignName ?? ''));
                $ok = (str_ends_with($cn, $cleanParent . ' PT') || str_ends_with($cn, $cleanParent . ' PT.'));
                return $ok && (!$requireEnabled || strtoupper($item->campaignStatus ?? '') === 'ENABLED');
            }) ?: null;
        }
        return null;
    }

    /**
     * Match HL campaign: try SKU first, then PARENT_SKU (only when allowParentFallback).
     */
    public static function matchHlCampaign($collection, string $sku, ?string $parent, bool $allowParentFallback = false): ?object
    {
        $cleanSku = strtoupper(trim($sku));
        $candidates = [$cleanSku, $cleanSku . ' HEAD'];
        $match = $collection->first(function ($item) use ($candidates) {
            $cn = strtoupper(trim($item->campaignName ?? ''));
            return in_array($cn, $candidates) && strtoupper($item->campaignStatus ?? '') === 'ENABLED';
        });
        if ($match) {
            return $match;
        }
        if ($allowParentFallback && $parent) {
            $cleanParent = strtoupper(trim($parent));
            $parentCandidates = [$cleanParent, $cleanParent . ' HEAD'];
            return $collection->first(function ($item) use ($parentCandidates) {
                $cn = strtoupper(trim($item->campaignName ?? ''));
                return in_array($cn, $parentCandidates) && strtoupper($item->campaignStatus ?? '') === 'ENABLED';
            }) ?: null;
        }
        return null;
    }


    protected function getAmazonAdRunningSaveAdvMasterDataProceed($request)
    {
        try {
            DB::beginTransaction();

                $updateAmazon = ADVMastersData::where('channel', 'AMAZON')->first();
                $updateAmazon->spent = $request->spendl30Total;
                $updateAmazon->clicks = $request->clicksL30Total;
                $updateAmazon->ad_sales = $request->salesL30Total;
                $updateAmazon->ad_sold = $request->soldL30Total;
                $updateAmazon->save();

                $updateAmazonkw = ADVMastersData::where('channel', 'AMZ KW')->first();
                $updateAmazonkw->spent = $request->kwSpendL30Total;
                $updateAmazonkw->clicks = $request->kwClicksL30Total;
                $updateAmazonkw->ad_sales = $request->kwSalesL30Total;
                $updateAmazonkw->ad_sold = $request->kwSoldL30Total;
                $updateAmazonkw->save();

                $updateAmazonpt = ADVMastersData::where('channel', 'AMZ PT')->first();
                $updateAmazonpt->spent = $request->ptSpendL30Total;
                $updateAmazonpt->clicks = $request->ptClicksL30Total;
                $updateAmazonpt->ad_sales = $request->ptSalesL30Total;
                $updateAmazonpt->ad_sold = $request->ptSoldL30Total;
                $updateAmazonpt->save();

                $updateAmazonhl = ADVMastersData::where('channel', 'AMZ HL')->first();
                $updateAmazonhl->spent = $request->hlSpendL30Total;
                $updateAmazonhl->clicks = $request->hlClicksL30Total;
                $updateAmazonhl->ad_sales = $request->hlSalesL30Total;
                $updateAmazonhl->ad_sold = $request->hlSoldL30Total;
                $updateAmazonhl->save();
    
            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    protected function getEbayRunningDataSaveProceed($request)
    {
        try {
            DB::beginTransaction();

                $updateEbay = ADVMastersData::where('channel', 'EBAY')->first();
                $updateEbay->spent = $request->spendL30Total;
                $updateEbay->clicks = $request->clicksL30Total;
                $updateEbay->ad_sales = $request->salesL30Total;
                $updateEbay->ad_sold = $request->soldL30Total;
                $updateEbay->save();

                $updateEbaykw = ADVMastersData::where('channel', 'EB KW')->first();
                $updateEbaykw->spent = $request->kwSpendL30Total;
                $updateEbaykw->clicks = $request->kwClicksL30Total;
                $updateEbaykw->ad_sales = $request->kwSalesL30Total;
                $updateEbaykw->ad_sold = $request->kwSoldL30Total;
                $updateEbaykw->save();

                $updateEbaypt = ADVMastersData::where('channel', 'EB PMT')->first();
                $updateEbaypt->spent = $request->pmtSpendL30Total;
                $updateEbaypt->clicks = $request->pmtClicksL30Total;
                $updateEbaypt->ad_sales = $request->pmtSalesL30Total;
                $updateEbaypt->ad_sold = $request->pmtSoldL30Total;
                $updateEbaypt->save();
    
            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    protected function getAmzonAdvSaveMissingDataProceed($request)
    {
        try {
            DB::beginTransaction();

                $updateAmazon = ADVMastersData::where('channel', 'AMAZON')->first();
                $updateAmazon->missing_ads = $request->totalMissingAds;
                $updateAmazon->save();

                $updateAmazonkw = ADVMastersData::where('channel', 'AMZ KW')->first();
                $updateAmazonkw->missing_ads = $request->kwMissing;
                $updateAmazonkw->save();

                $updateAmazonpt = ADVMastersData::where('channel', 'AMZ PT')->first();
                $updateAmazonpt->missing_ads = $request->ptMissing;
                $updateAmazonpt->save();
    
            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    protected function getEbayMissingSaveDataProceed($request)
    {
        try {
            DB::beginTransaction();

                $updateAmazon = ADVMastersData::where('channel', 'EBAY')->first();
                $updateAmazon->missing_ads = $request->totalMissingAds;
                $updateAmazon->save();

                $updateAmazonkw = ADVMastersData::where('channel', 'EB KW')->first();
                $updateAmazonkw->missing_ads = $request->kwMissing;
                $updateAmazonkw->save();

                $updateAmazonpt = ADVMastersData::where('channel', 'EB PMT')->first();
                $updateAmazonpt->missing_ads = $request->ptMissing;
                $updateAmazonpt->save();
    
            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    protected function getAmazonTotalSalesSaveDataProceed($request)
    {
        try {
            DB::beginTransaction();

                $updateAmazon = ADVMastersData::where('channel', 'AMAZON')->first();
                $updateAmazon->l30_sales = $request->totalSales;
                $updateAmazon->save();
    
            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    protected function getAdvEbayTotalSaveDataProceed($request)
    {
        try {
            DB::beginTransaction();

                $updateAmazon = ADVMastersData::where('channel', 'EBAY')->first();
                $updateAmazon->l30_sales = $request->totalSales;
                $updateAmazon->save();
    
            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    public static function getAdvWalmartRunningSaveDataProceed($request)
    {
        try {
            DB::beginTransaction();
            
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();
            
            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
            $normalizeSku = fn($sku) => strtoupper(trim($sku));
            $normalizedSkus = array_map($normalizeSku, $skus);
            
            // Get Walmart campaign reports for L30
            $walmartCampaignReportsL30 = DB::table('walmart_campaign_reports')
                ->where('report_range', 'L30')
                ->whereIn('campaignName', $normalizedSkus)
                ->get();
            
            // Get Walmart prices from WalmartProductSheet for sales calculation
            $walmartProductSheets = WalmartProductSheet::whereIn('sku', $skus)
                ->get()
                ->keyBy(function($item) use ($normalizeSku) {
                    return $normalizeSku($item->sku ?? '');
                });
            
            // Calculate totals directly from database
            $total_spend_l30 = 0;
            $total_clicks_l30 = 0;
            $total_sales_l30 = 0;
            $total_sold_l30 = 0;
            
            foreach ($walmartCampaignReportsL30 as $report) {
                $campaignName = $normalizeSku($report->campaignName ?? '');
                // Skip if it's a parent SKU
                if (str_starts_with($campaignName, 'PARENT ')) {
                    continue;
                }
                
                $spend = floatval($report->spend ?? 0);
                $clicks = intval($report->clicks ?? 0);
                $sold = intval($report->sold ?? 0);
                
                // Calculate sales: sold * price (if price available)
                $price = 0;
                if (isset($walmartProductSheets[$campaignName])) {
                    $price = floatval($walmartProductSheets[$campaignName]->price ?? 0);
                }
                $sales = $sold * $price;
                
                $total_spend_l30 += $spend;
                $total_clicks_l30 += $clicks;
                $total_sales_l30 += $sales;
                $total_sold_l30 += $sold;
            }
            
            // Round values
            $total_spend_l30 = round($total_spend_l30);
            $total_clicks_l30 = round($total_clicks_l30);
            $total_sales_l30 = round($total_sales_l30);
            $total_sold_l30 = round($total_sold_l30);
            
            // Calculate metrics
            $cpc = $total_clicks_l30 > 0 ? round($total_spend_l30 / $total_clicks_l30, 2) : 0;
            $cvr = $total_clicks_l30 > 0 ? round(($total_sold_l30 / $total_clicks_l30) * 100, 2) : 0;
            $acos = $total_sales_l30 > 0 ? round(($total_spend_l30 / $total_sales_l30) * 100, 2) : 0;
            $tacos = 0; // Will be calculated when l30_sales is available

            // Save to main table
            ADVMastersData::updateOrCreate(
                ['channel' => 'WALMART'],
                [
                    'spent' => $total_spend_l30,
                    'clicks' => $total_clicks_l30,
                    'ad_sales' => $total_sales_l30,
                    'ad_sold' => $total_sold_l30
                ]
            );

            // Save daily data for WALMART
            $todayDate = date('Y-m-d');
            ADVMastersDailyData::updateOrCreate(
                ['date' => $todayDate, 'channel' => 'WALMART'],
                [
                    'spent' => $total_spend_l30,
                    'clicks' => $total_clicks_l30,
                    'ad_sales' => $total_sales_l30,
                    'ad_sold' => $total_sold_l30,
                    'cpc' => $cpc,
                    'cvr' => $cvr,
                    'acos' => $acos,
                    'tacos' => $tacos,
                    'gpft' => 0,
                    'tpft' => 0
                ]
            );
    
            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    public static function getAdvEbay3AdRunningDataSaveProceed($request)
    {
        try {
            DB::beginTransaction();
            $normalizeSku = fn($sku) => strtoupper(trim($sku));
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            $skus = $productMasters->pluck('sku')->filter()->map($normalizeSku)->unique()->values()->all();
            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->sku));
            
            $ebayMetricData = Ebay3Metric::whereIn('sku', $skus)
                ->select('sku', 'ebay_price', 'item_id', 'ebay_l30')
                ->get()
                ->keyBy(fn($item) => $normalizeSku($item->sku));

            $ebayCampaignReportsL30 = Ebay3PriorityReport::where('report_range', 'L30')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) {
                        $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                    }
                })->get();
            $ebayCampaignReportsL7 = Ebay3PriorityReport::where('report_range', 'L7')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) {
                        $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                    }
                })->get();

            $itemIds = $ebayMetricData->pluck('item_id')->filter()->toArray();
            $ebayGeneralReportsL30 = !empty($itemIds) 
                ? Ebay3GeneralReport::where('report_range', 'L30')
                    ->whereIn('listing_id', $itemIds)
                    ->get()
                : collect();
            
            $total_kw_spend_l30 = 0;
            $total_kw_clicks_l30 = 0;
            $total_kw_sales_l30 = 0;
            $total_kw_sold_l30 = 0;
            $total_pmt_spend_l30 = 0;
            $total_pmt_clicks_l30 = 0;
            $total_pmt_sales_l30 = 0;
            $total_pmt_sold_l30 = 0;
            $total_spend_l30 = 0;
            $total_click_l30 = 0;
            $total_sales_l30 = 0;
            $total_sold_l30 = 0;
            
            $kw_campaigns_found = 0;
            $kw_campaigns_with_data = 0;

            foreach ($productMasters as $pm) {
                $sku = strtoupper($pm->sku);
                $ebay = $ebayMetricData[$sku] ?? null;

                $matchedCampaignL30 = $ebayCampaignReportsL30->first(function ($item) use ($sku) {
                    return strtoupper(trim($item->campaign_name)) === strtoupper(trim($sku));
                });

                $matchedCampaignL7 = $ebayCampaignReportsL7->first(function ($item) use ($sku) {
                    return strtoupper(trim($item->campaign_name)) === strtoupper(trim($sku));
                });
                
                $matchedGeneralL30 = null;
                if ($ebay && !empty($ebay->item_id)) {
                    $matchedGeneralL30 = $ebayGeneralReportsL30->first(function ($item) use ($ebay) {
                        return trim((string)$item->listing_id) == trim((string)$ebay->item_id);
                    });
                }

                $row = [];
                // Use L30 campaign name for checking since we're calculating L30 data
                $row['campaignName'] = $matchedCampaignL30 ? ($matchedCampaignL30->campaign_name ?? '') : '';

                // Extract KW data - check if matchedCampaignL30 exists and has data
                if ($matchedCampaignL30) {
                    $row['kw_spend_L30'] = (float) str_replace(['USD ', ','], '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? '0');
                    $row['kw_clicks_L30'] = (int) ($matchedCampaignL30->cpc_clicks ?? 0);
                    $row['kw_sales_L30'] = (float) str_replace(['USD ', ','], '', $matchedCampaignL30->cpc_sale_amount_payout_currency ?? '0');
                    $row['kw_sold_L30'] = (int) ($matchedCampaignL30->cpc_attributed_sales ?? 0);
                } else {
                    $row['kw_spend_L30'] = 0;
                    $row['kw_clicks_L30'] = 0;
                    $row['kw_sales_L30'] = 0;
                    $row['kw_sold_L30'] = 0;
                }
            
                // PMT data extraction with null check
                if ($matchedGeneralL30) {
                    $row['pmt_spend_L30'] = (float) str_replace('USD ', '', $matchedGeneralL30->ad_fees ?? 0);
                    $row['pmt_clicks_L30'] = (int) ($matchedGeneralL30->clicks ?? 0);
                    $row['pmt_sales_L30'] = (float) str_replace('USD ', '', $matchedGeneralL30->sale_amount ?? 0);
                    $row['pmt_sold_L30'] = (int) ($matchedGeneralL30->sales ?? 0);
                } else {
                    $row['pmt_spend_L30'] = 0;
                    $row['pmt_clicks_L30'] = 0;
                    $row['pmt_sales_L30'] = 0;
                    $row['pmt_sold_L30'] = 0;
                }

                $row['SPEND_L30'] = $row['kw_spend_L30'] + $row['pmt_spend_L30'];
                $row['CLICKS_L30'] = $row['kw_clicks_L30'] + $row['pmt_clicks_L30'];
                $row['SALES_L30'] = $row['kw_sales_L30'] + $row['pmt_sales_L30'];
                $row['SOLD_L30'] = $row['kw_sold_L30'] + $row['pmt_sold_L30'];

                $sku2 = strtolower(trim($pm->sku));
                if (strpos($sku2, 'parent ') === false) {
                    // Count KW data only if KW campaign exists and has data
                    if($matchedCampaignL30 && $row['campaignName'] !== '') {
                        $kw_campaigns_found++;
                        if ($row['kw_spend_L30'] > 0 || $row['kw_clicks_L30'] > 0 || $row['kw_sales_L30'] > 0 || $row['kw_sold_L30'] > 0) {
                            $kw_campaigns_with_data++;
                        }
                        $total_kw_spend_l30 += $row['kw_spend_L30'];
                        $total_kw_clicks_l30 += $row['kw_clicks_L30'];
                        $total_kw_sales_l30 += $row['kw_sales_L30'];
                        $total_kw_sold_l30 += $row['kw_sold_L30'];
                    }
                    
                    // Count PMT data separately (even if KW campaign doesn't exist)
                    $total_pmt_spend_l30 += $row['pmt_spend_L30'];
                    $total_pmt_clicks_l30 += $row['pmt_clicks_L30'];
                    $total_pmt_sales_l30 += $row['pmt_sales_L30'];
                    $total_pmt_sold_l30 += $row['pmt_sold_L30'];
                    
                    // Total = KW + PMT (always count both, even if KW doesn't exist)
                    $total_spend_l30 += $row['SPEND_L30'];
                    $total_click_l30 += $row['CLICKS_L30'];
                    $total_sales_l30 += $row['SALES_L30'];
                    $total_sold_l30 += $row['SOLD_L30'];
                }
            }

            $todayDate = date('Y-m-d');
            $total_spend_l30 = round($total_spend_l30);
            $total_click_l30 = round($total_click_l30);
            $total_sales_l30 = round($total_sales_l30);
            $total_sold_l30 = round($total_sold_l30);
            $total_pmt_spend_l30 = round($total_pmt_spend_l30);
            $total_pmt_clicks_l30 = round($total_pmt_clicks_l30);
            $total_pmt_sales_l30 = round($total_pmt_sales_l30);
            $total_pmt_sold_l30 = round($total_pmt_sold_l30);
            $total_kw_spend_l30 = round($total_kw_spend_l30);
            $total_kw_clicks_l30 = round($total_kw_clicks_l30);
            $total_kw_sales_l30 = round($total_kw_sales_l30);
            $total_kw_sold_l30 = round($total_kw_sold_l30);

            // Calculate metrics
            $ebay3_cpc = $total_click_l30 > 0 ? round($total_spend_l30 / $total_click_l30, 2) : 0;
            $ebay3_cvr = $total_click_l30 > 0 ? round(($total_sold_l30 / $total_click_l30) * 100, 2) : 0;
            $ebay3_acos = $total_sales_l30 > 0 ? round(($total_spend_l30 / $total_sales_l30) * 100, 2) : 0;
            $ebay3_tacos = 0; // Will be calculated when l30_sales is available
            
            $ebay3kw_cpc = $total_kw_clicks_l30 > 0 ? round($total_kw_spend_l30 / $total_kw_clicks_l30, 2) : 0;
            $ebay3kw_cvr = $total_kw_clicks_l30 > 0 ? round(($total_kw_sold_l30 / $total_kw_clicks_l30) * 100, 2) : 0;
            $ebay3kw_acos = $total_kw_sales_l30 > 0 ? round(($total_kw_spend_l30 / $total_kw_sales_l30) * 100, 2) : 0;
            $ebay3kw_tacos = 0;
            
            $ebay3pmt_cpc = $total_pmt_clicks_l30 > 0 ? round($total_pmt_spend_l30 / $total_pmt_clicks_l30, 2) : 0;
            $ebay3pmt_cvr = $total_pmt_clicks_l30 > 0 ? round(($total_pmt_sold_l30 / $total_pmt_clicks_l30) * 100, 2) : 0;
            $ebay3pmt_acos = $total_pmt_sales_l30 > 0 ? round(($total_pmt_spend_l30 / $total_pmt_sales_l30) * 100, 2) : 0;
            $ebay3pmt_tacos = 0;

            // Save to ADVMastersData
            ADVMastersData::updateOrCreate(
                ['channel' => 'EBAY 3'],
                [
                    'spent' => $total_spend_l30,
                    'clicks' => $total_click_l30,
                    'ad_sales' => $total_sales_l30,
                    'ad_sold' => $total_sold_l30
                ]
            );

            ADVMastersData::updateOrCreate(
                ['channel' => 'EB KW3'],
                [
                    'spent' => $total_kw_spend_l30,
                    'clicks' => $total_kw_clicks_l30,
                    'ad_sales' => $total_kw_sales_l30,
                    'ad_sold' => $total_kw_sold_l30
                ]
            );

            ADVMastersData::updateOrCreate(
                ['channel' => 'EB PMT3'],
                [
                    'spent' => $total_pmt_spend_l30,
                    'clicks' => $total_pmt_clicks_l30,
                    'ad_sales' => $total_pmt_sales_l30,
                    'ad_sold' => $total_pmt_sold_l30
                ]
            );
            
            // Save daily data for EBAY 3
            ADVMastersDailyData::updateOrCreate(
                ['date' => $todayDate, 'channel' => 'EBAY 3'],
                [
                    'spent' => $total_spend_l30,
                    'clicks' => $total_click_l30,
                    'ad_sales' => $total_sales_l30,
                    'ad_sold' => $total_sold_l30,
                    'cpc' => $ebay3_cpc,
                    'cvr' => $ebay3_cvr,
                    'acos' => $ebay3_acos,
                    'tacos' => $ebay3_tacos,
                    'gpft' => 0,
                    'tpft' => 0
                ]
            );
            
            // Sub-channels daily data removed - only main channel data is saved
     
            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    public static function getEbay2AdvRunningAdDataSaveProceed($request)
    {
        try {
            DB::beginTransaction();
            $normalizeSku = fn($sku) => strtoupper(trim($sku));
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            $skus = $productMasters->pluck('sku')->filter()->map($normalizeSku)->unique()->values()->all();
            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->sku));
            
            $ebayMetricData = DB::connection('apicentral')->table('ebay2_metrics')
                ->select('sku', 'ebay_price', 'item_id', 'ebay_l30')
                ->whereIn('sku', $skus)
                ->get()
                ->keyBy(fn($item) => $normalizeSku($item->sku));

            $itemIds = $ebayMetricData->pluck('item_id')->filter()->toArray();
            $ebayGeneralReportsL30 = !empty($itemIds) 
                ? Ebay2GeneralReport::where('report_range', 'L30')
                    ->whereIn('listing_id', $itemIds)
                    ->get()
                : collect();
            
            $total_pmt_spend_l30 = 0;
            $total_pmt_clicks_l30 = 0;
            $total_pmt_sales_l30 = 0;
            $total_pmt_sold_l30 = 0;
            $total_spend_l30 = 0;
            $total_click_l30 = 0;
            $total_sales_l30 = 0;
            $total_sold_l30 = 0;

            foreach ($productMasters as $pm) {
                $sku = strtoupper($pm->sku);
                $ebay = $ebayMetricData[$sku] ?? null;
                
                $matchedGeneralL30 = null;
                if ($ebay && !empty($ebay->item_id)) {
                    $matchedGeneralL30 = $ebayGeneralReportsL30->first(function ($item) use ($ebay) {
                        return trim((string)$item->listing_id) == trim((string)$ebay->item_id);
                    });
                }

                // PMT data extraction
                if ($matchedGeneralL30) {
                    $pmt_spend = (float) str_replace(['USD ', ','], '', $matchedGeneralL30->ad_fees ?? '0');
                    $pmt_clicks = (int) ($matchedGeneralL30->clicks ?? 0);
                    $pmt_sales = (float) str_replace(['USD ', ','], '', $matchedGeneralL30->sale_amount ?? '0');
                    $pmt_sold = (int) ($matchedGeneralL30->sales ?? 0);
                } else {
                    $pmt_spend = 0;
                    $pmt_clicks = 0;
                    $pmt_sales = 0;
                    $pmt_sold = 0;
                }

                $sku2 = strtolower(trim($pm->sku));
                if (strpos($sku2, 'parent ') === false) {
                    // Count PMT data (eBay 2 only has PMT, no KW)
                    $total_spend_l30 += $pmt_spend;
                    $total_click_l30 += $pmt_clicks;
                    $total_sales_l30 += $pmt_sales;
                    $total_sold_l30 += $pmt_sold;
                    $total_pmt_spend_l30 += $pmt_spend;
                    $total_pmt_clicks_l30 += $pmt_clicks;
                    $total_pmt_sales_l30 += $pmt_sales;
                    $total_pmt_sold_l30 += $pmt_sold;
                }
            }

            $todayDate = date('Y-m-d');
            $total_spend_l30 = round($total_spend_l30);
            $total_click_l30 = round($total_click_l30);
            $total_sales_l30 = round($total_sales_l30);
            $total_sold_l30 = round($total_sold_l30);
            $total_pmt_spend_l30 = round($total_pmt_spend_l30);
            $total_pmt_clicks_l30 = round($total_pmt_clicks_l30);
            $total_pmt_sales_l30 = round($total_pmt_sales_l30);
            $total_pmt_sold_l30 = round($total_pmt_sold_l30);

            // Calculate metrics
            $ebay2_cpc = $total_click_l30 > 0 ? round($total_spend_l30 / $total_click_l30, 2) : 0;
            $ebay2_cvr = $total_click_l30 > 0 ? round(($total_sold_l30 / $total_click_l30) * 100, 2) : 0;
            $ebay2_acos = $total_sales_l30 > 0 ? round(($total_spend_l30 / $total_sales_l30) * 100, 2) : 0;
            $ebay2_tacos = 0; // Will be calculated when l30_sales is available
            
            $ebay2pmt_cpc = $total_pmt_clicks_l30 > 0 ? round($total_pmt_spend_l30 / $total_pmt_clicks_l30, 2) : 0;
            $ebay2pmt_cvr = $total_pmt_clicks_l30 > 0 ? round(($total_pmt_sold_l30 / $total_pmt_clicks_l30) * 100, 2) : 0;
            $ebay2pmt_acos = $total_pmt_sales_l30 > 0 ? round(($total_pmt_spend_l30 / $total_pmt_sales_l30) * 100, 2) : 0;
            $ebay2pmt_tacos = 0;

            // Save to ADVMastersData
            ADVMastersData::updateOrCreate(
                ['channel' => 'EBAY 2'],
                [
                    'spent' => $total_spend_l30,
                    'clicks' => $total_click_l30,
                    'ad_sales' => $total_sales_l30,
                    'ad_sold' => $total_sold_l30
                ]
            );

            ADVMastersData::updateOrCreate(
                ['channel' => 'EB PMT2'],
                [
                    'spent' => $total_pmt_spend_l30,
                    'clicks' => $total_pmt_clicks_l30,
                    'ad_sales' => $total_pmt_sales_l30,
                    'ad_sold' => $total_pmt_sold_l30
                ]
            );
            
            // Save daily data for EBAY 2
            ADVMastersDailyData::updateOrCreate(
                ['date' => $todayDate, 'channel' => 'EBAY 2'],
                [
                    'spent' => $total_spend_l30,
                    'clicks' => $total_click_l30,
                    'ad_sales' => $total_sales_l30,
                    'ad_sold' => $total_sold_l30,
                    'cpc' => $ebay2_cpc,
                    'cvr' => $ebay2_cvr,
                    'acos' => $ebay2_acos,
                    'tacos' => $ebay2_tacos,
                    'gpft' => 0,
                    'tpft' => 0
                ]
            );
            
            // Sub-channels daily data removed - only main channel data is saved
    
            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    public static function getEbay2TotsalSaleDataSaveProceed($request)
    {
        try {
            DB::beginTransaction();
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();
            
            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
            
            $ebayMetrics = DB::connection('apicentral')->table('ebay2_metrics')
                ->whereIn('sku', $skus)
                ->get()
                ->keyBy('sku');

            $total_ebay2_sales = 0;
            foreach ($productMasters as $pm) {
                $sku = strtoupper($pm->sku);
                if (str_starts_with($sku, 'PARENT ')) {
                    continue;
                }
                
                $shopify = $shopifyData[$pm->sku] ?? null;
                $ebayMetric = $ebayMetrics[$pm->sku] ?? null;
                
                $row = [];
                $row["eBay L30"] = $ebayMetric ? ($ebayMetric->ebay_l30 ?? 0) : 0;
                $row["eBay Price"] = $ebayMetric ? ($ebayMetric->ebay_price ?? 0) : 0;

                $total_ebay2_sales = $total_ebay2_sales + ($row["eBay L30"] * floatval($row["eBay Price"]));
            }
            
            $total_ebay2_sales = round($total_ebay2_sales);
            $todayDate = date('Y-m-d');
            
            // Get current spent value to calculate TACOS
            $ebay2Data = ADVMastersData::where('channel', 'EBAY 2')->first();
            $current_spent = $ebay2Data->spent ?? 0;
            $tacos = $total_ebay2_sales > 0 ? round(($current_spent / $total_ebay2_sales) * 100, 2) : 0;
            
            // Save to ADVMastersData
            ADVMastersData::updateOrCreate(
                ['channel' => 'EBAY 2'],
                ['l30_sales' => $total_ebay2_sales]
            );

            // Save daily data for eBay 2 L30 sales and TACOS
            ADVMastersDailyData::updateOrCreate(
                ['date' => $todayDate, 'channel' => 'EBAY 2'],
                [
                    'l30_sales' => $total_ebay2_sales,
                    'tacos' => $tacos
                ]
            );
            
            // TACOS is already calculated and saved for main EBAY 2 channel above

            DB::commit();
            return 1;
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    public static function getEbay3TotalSaleSaveDataProceed($request)
    {
        try {
            DB::beginTransaction();
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();
            
            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
            
            $ebayMetrics = Ebay3Metric::whereIn('sku', $skus)->get()->keyBy('sku');

            $total_ebay3_sales = 0;
            foreach ($productMasters as $pm) {
                $sku = strtoupper($pm->sku);
                if (str_starts_with($sku, 'PARENT ')) {
                    continue;
                }
                
                $shopify = $shopifyData[$pm->sku] ?? null;
                $ebayMetric = $ebayMetrics[$pm->sku] ?? null;
                
                $row = [];
                $row["eBay L30"] = $ebayMetric ? ($ebayMetric->ebay_l30 ?? 0) : 0;
                $row["eBay Price"] = $ebayMetric ? ($ebayMetric->ebay_price ?? 0) : 0;

                $total_ebay3_sales = $total_ebay3_sales + ($row["eBay L30"] * floatval($row["eBay Price"]));
            }
            
            $total_ebay3_sales = round($total_ebay3_sales);
            $todayDate = date('Y-m-d');
            
            // Get current spent value to calculate TACOS
            $ebay3Data = ADVMastersData::where('channel', 'EBAY 3')->first();
            $current_spent = $ebay3Data->spent ?? 0;
            $tacos = $total_ebay3_sales > 0 ? round(($current_spent / $total_ebay3_sales) * 100, 2) : 0;
            
            // Save to ADVMastersData
            ADVMastersData::updateOrCreate(
                ['channel' => 'EBAY 3'],
                ['l30_sales' => $total_ebay3_sales]
            );
            
            // Save daily data for eBay 3 L30 sales and TACOS
            ADVMastersDailyData::updateOrCreate(
                ['date' => $todayDate, 'channel' => 'EBAY 3'],
                [
                    'l30_sales' => $total_ebay3_sales,
                    'tacos' => $tacos
                ]
            );
            
            // TACOS is already calculated and saved for main EBAY 3 channel above

            DB::commit();
            return 1;
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    public static function getAdvEbay2MissingSaveDataProceed($request)
    {
        try {
            DB::beginTransaction();

                $updateEbay2 = ADVMastersData::updateOrCreate(
                    ['channel' => 'EBAY 2'],
                    ['missing_ads' => $request->ptMissing]
                );

                $updateEbay2Pmt = ADVMastersData::updateOrCreate(
                    ['channel' => 'EB PMT2'],
                    ['missing_ads' => $request->ptMissing]
                );
            
            // Save daily data for eBay 2 missing ads
            $todayDate = date('Y-m-d');
            $ptMissing = (int)($request->ptMissing ?? 0);
            
            // Save daily data for main channel only (not sub-channels)
            ADVMastersDailyData::updateOrCreate(
                ['date' => $todayDate, 'channel' => 'EBAY 2'],
                ['missing_ads' => $ptMissing]
            );

            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    public static function getEbay3MissingDataSaveProceed($request)
    {
        try {
            DB::beginTransaction();

                $updateEbay3 = ADVMastersData::updateOrCreate(
                    ['channel' => 'EBAY 3'],
                    ['missing_ads' => $request->totalMissingAds]
                );

                $updateEbay3Kw = ADVMastersData::updateOrCreate(
                    ['channel' => 'EB KW3'],
                    ['missing_ads' => $request->kwMissing]
                );

                $updateEbay3Pmt = ADVMastersData::updateOrCreate(
                    ['channel' => 'EB PMT3'],
                    ['missing_ads' => $request->ptMissing]
                );
            
            // Save daily data for eBay 3 missing ads
            $todayDate = date('Y-m-d');
            $totalMissingAds = (int)($request->totalMissingAds ?? 0);
            $kwMissing = (int)($request->kwMissing ?? 0);
            $ptMissing = (int)($request->ptMissing ?? 0);
            
            // Save daily data for main channel only (not sub-channels)
            ADVMastersDailyData::updateOrCreate(
                ['date' => $todayDate, 'channel' => 'EBAY 3'],
                ['missing_ads' => $totalMissingAds]
            );

            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    public static function getAdvShopifyGShoppingSaveDataProceed($request)
    {
        try {
            DB::beginTransaction();
            
            // Get last 30 days data from google_ads_campaigns table
            $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
            $today = date('Y-m-d');
            
            // Calculate totals from google_ads_campaigns table
            $gShoppingData = DB::table('google_ads_campaigns')
                ->selectRaw('
                    SUM(metrics_clicks) as total_clicks,
                    SUM(metrics_cost_micros) / 1000000 as total_spend,
                    SUM(ga4_ad_sales) as total_ad_sales,
                    SUM(ga4_sold_units) as total_ad_sold
                ')
                ->whereDate('date', '>=', $thirtyDaysAgo)
                ->whereDate('date', '<=', $today)
                ->where('campaign_status', 'ENABLED')
                ->first();
            
            $total_spend_l30 = floatval($gShoppingData->total_spend ?? 0);
            $total_clicks_l30 = intval($gShoppingData->total_clicks ?? 0);
            $total_ad_sales_l30 = floatval($gShoppingData->total_ad_sales ?? 0);
            $total_ad_sold_l30 = intval($gShoppingData->total_ad_sold ?? 0);
            
            // Round values
            $total_spend_l30 = round($total_spend_l30);
            $total_clicks_l30 = round($total_clicks_l30);
            $total_ad_sales_l30 = round($total_ad_sales_l30);
            $total_ad_sold_l30 = round($total_ad_sold_l30);

            // Calculate metrics
            $cpc = $total_clicks_l30 > 0 ? round($total_spend_l30 / $total_clicks_l30, 2) : 0;
            $cvr = $total_clicks_l30 > 0 ? round(($total_ad_sold_l30 / $total_clicks_l30) * 100, 2) : 0;
            $acos = $total_ad_sales_l30 > 0 ? round(($total_spend_l30 / $total_ad_sales_l30) * 100, 2) : 0;
            $tacos = 0; // Will be calculated when l30_sales is available

            // Save to main table
            ADVMastersData::updateOrCreate(
                ['channel' => 'G SHOPPING'],
                [
                    'spent' => $total_spend_l30,
                    'clicks' => $total_clicks_l30,
                    'ad_sales' => $total_ad_sales_l30,
                    'ad_sold' => $total_ad_sold_l30
                ]
            );

            // Save daily data for G SHOPPING
            $todayDate = date('Y-m-d');
            ADVMastersDailyData::updateOrCreate(
                ['date' => $todayDate, 'channel' => 'G SHOPPING'],
                [
                    'spent' => $total_spend_l30,
                    'clicks' => $total_clicks_l30,
                    'ad_sales' => $total_ad_sales_l30,
                    'ad_sold' => $total_ad_sold_l30,
                    'cpc' => $cpc,
                    'cvr' => $cvr,
                    'acos' => $acos,
                    'tacos' => $tacos,
                    'gpft' => 0,
                    'tpft' => 0
                ]
            );
                 
            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    public static function getChannelAdvMasterAmazonCronDataProceed($request)
    {
        try {
            DB::beginTransaction();
            
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

            $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
                return strtoupper($item->sku);
            });

            // Use normalized (uppercase) key for case-insensitive SKU lookup - prevents missed inventory
            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy(function ($item) {
                return strtoupper(trim($item->sku));
            });

            // Inventory by parent: SUM(child inv) grouped by PARENT_SKU - for parent-level display
            $inventoryByParent = [];
            foreach ($productMasters->filter(fn($pm) => $pm->parent && !str_starts_with(strtoupper($pm->sku), 'PARENT')) as $child) {
                $pKey = strtoupper(trim($child->parent));
                $inv = (float) (($shopifyData[strtoupper(trim($child->sku))] ?? null)?->inv ?? 0);
                $inventoryByParent[$pKey] = ($inventoryByParent[$pKey] ?? 0) + $inv;
            }

            // Get KW campaigns with MAX(spend) per campaign (same logic as amazonKwAdsView)
            // Directly sum all KW campaigns without SKU filtering (exactly like amazonKwAdsView)
            $amazonKwL30Raw = DB::table('amazon_sp_campaign_reports')
                ->selectRaw('
                    campaignName,
                    MAX(spend) as max_spend,
                    SUM(clicks) as clicks,
                    SUM(purchases1d) as orders,
                    SUM(sales1d) as sales,
                    SUM(unitsSoldSameSku30d) as unitsSoldSameSku30d,
                    SUM(sales30d) as sales30d
                ')
                ->where('report_date_range', 'L30')
                ->whereRaw("campaignName NOT REGEXP '(PT\\.?$|FBA$)'") // Exclude PT and FBA campaigns
                ->groupBy('campaignName')
                ->get();
            
            // Calculate total KW spend directly - use sales30d and unitsSoldSameSku30d for totals (as it matches actual page value)
            $total_kw_spend_l30_direct = $amazonKwL30Raw->sum('max_spend');
            $total_kw_clicks_l30_direct = $amazonKwL30Raw->sum('clicks');
            $total_kw_sold_l30_direct = $amazonKwL30Raw->sum('unitsSoldSameSku30d'); // unitsSoldSameSku30d = ad sold (for totals, matches actual page)
            $total_kw_sales_l30_direct = $amazonKwL30Raw->sum('sales30d'); // sales30d = ad sales (for totals, matches actual page)
            
            // Convert to collection with proper structure for SKU-based matching (for row-level calculations)
            // Use unitsSoldSameSku30d and sales30d for row-level matching (as used in AmazonAdRunningController)
            $amazonKwL30 = collect($amazonKwL30Raw)->map(function($item) {
                $obj = new \stdClass();
                $obj->campaignName = $item->campaignName;
                $obj->spend = $item->max_spend;
                $obj->clicks = $item->clicks;
                $obj->unitsSoldSameSku30d = $item->unitsSoldSameSku30d; // For row-level calculations
                $obj->sales30d = $item->sales30d; // For row-level calculations
                return $obj;
            });

            // Get PT campaigns with MAX(spend) per campaign (same logic as amazonPtAdsView)
            // Directly sum all PT campaigns without SKU filtering (exactly like amazonPtAdsView)
            $amazonPtL30Raw = DB::table('amazon_sp_campaign_reports')
                ->selectRaw('
                    campaignName,
                    MAX(spend) as max_spend,
                    SUM(clicks) as clicks,
                    SUM(purchases1d) as orders,
                    SUM(sales1d) as sales,
                    SUM(unitsSoldSameSku30d) as unitsSoldSameSku30d,
                    SUM(sales30d) as sales30d,
                    MAX(campaignStatus) as campaignStatus
                ')
                ->where('report_date_range', 'L30')
                ->where(function($query) {
                    $query->whereRaw("campaignName LIKE '%PT'")
                        ->orWhereRaw("campaignName LIKE '%PT.'");
                })
                ->whereRaw("campaignName NOT LIKE '%FBA PT%'") // Exclude FBA PT campaigns
                ->whereRaw("campaignName NOT LIKE '%FBA PT.%'") // Exclude FBA PT. campaigns
                ->groupBy('campaignName')
                ->get();
            
            // Calculate total PT spend directly - use sales30d and unitsSoldSameSku30d for totals (as it matches actual page value)
            $total_pt_spend_l30_direct = $amazonPtL30Raw->sum('max_spend');
            $total_pt_clicks_l30_direct = $amazonPtL30Raw->sum('clicks');
            $total_pt_sold_l30_direct = $amazonPtL30Raw->sum('unitsSoldSameSku30d'); // unitsSoldSameSku30d = ad sold (for totals, matches actual page)
            $total_pt_sales_l30_direct = $amazonPtL30Raw->sum('sales30d'); // sales30d = ad sales (for totals, matches actual page)
            
            // Convert to collection with proper structure for SKU-based matching (for row-level calculations)
            // Use unitsSoldSameSku30d and sales30d for row-level matching (as used in AmazonAdRunningController)
            $amazonPtL30 = collect($amazonPtL30Raw)->map(function($item) {
                $obj = new \stdClass();
                $obj->campaignName = $item->campaignName;
                $obj->spend = $item->max_spend;
                $obj->clicks = $item->clicks;
                $obj->unitsSoldSameSku30d = $item->unitsSoldSameSku30d; // For row-level calculations
                $obj->sales30d = $item->sales30d; // For row-level calculations
                $obj->campaignStatus = $item->campaignStatus;
                return $obj;
            });

            // Get HL campaigns with MAX(cost) per campaign (same logic as amazonHlAdsView)
            // Directly sum all HL campaigns without SKU filtering (exactly like amazonHlAdsView)
            $amazonHlL30Raw = DB::table('amazon_sb_campaign_reports')
                ->selectRaw('
                    campaignName,
                    MAX(cost) as max_cost,
                    SUM(clicks) as clicks,
                    SUM(purchases) as orders,
                    SUM(sales) as sales,
                    SUM(unitsSold) as unitsSold,
                    MAX(campaignStatus) as campaignStatus
                ')
                ->where('report_date_range', 'L30')
                ->groupBy('campaignName')
                ->get();
            
            // Calculate total HL spend directly - use sales and unitsSold for totals (as it matches actual page value)
            $total_hl_spend_l30_direct = $amazonHlL30Raw->sum('max_cost');
            $total_hl_clicks_l30_direct = $amazonHlL30Raw->sum('clicks');
            $total_hl_sold_l30_direct = $amazonHlL30Raw->sum('unitsSold'); // unitsSold = ad sold (for totals, matches actual page)
            $total_hl_sales_l30_direct = $amazonHlL30Raw->sum('sales'); // sales = ad sales (for totals)
            
            // Convert to collection with proper structure for SKU-based matching (for row-level calculations)
            // Use unitsSold and sales for row-level matching (as used in AmazonAdRunningController)
            $amazonHlL30 = collect($amazonHlL30Raw)->map(function($item) {
                $obj = new \stdClass();
                $obj->campaignName = $item->campaignName;
                $obj->cost = $item->max_cost;
                $obj->clicks = $item->clicks;
                $obj->unitsSold = $item->unitsSold; // For row-level calculations
                $obj->sales = $item->sales; // For row-level calculations
                $obj->campaignStatus = $item->campaignStatus;
                return $obj;
            });

            $amazonHlL7 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
                ->where('report_date_range', 'L7')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                })
                ->get();

            $parentSkuCounts = $productMasters
                ->filter(fn($pm) => $pm->parent && !str_starts_with(strtoupper($pm->sku), 'PARENT'))
                ->groupBy('parent')
                ->map->count();

            $parentHlSpendData = [];
            foreach ($productMasters as $pm) {
                $sku = strtoupper($pm->sku);
                $parent = trim($pm->parent ?? '');
                if (str_starts_with($sku, 'PARENT') && !$parent) {
                    $parent = $pm->sku; // Use full sku when parent column is empty for PARENT rows
                }

                $isParentRow = str_starts_with($sku, 'PARENT');
                $matchedCampaignHlL30 = static::matchHlCampaign($amazonHlL30, $pm->sku, $parent ?: null, $isParentRow);
                $matchedCampaignHlL7 = static::matchHlCampaign($amazonHlL7, $pm->sku, $parent ?: null, $isParentRow);

                if (str_starts_with($sku, 'PARENT')) {
                    $childCount = $parentSkuCounts[$parent] ?? 0;
                    $parentHlSpendData[$parent] = [
                        'total_L30' => $matchedCampaignHlL30?->cost ?? 0,
                        'total_L7'  => $matchedCampaignHlL7?->cost ?? 0,
                        'total_L30_sales' => $matchedCampaignHlL30?->sales ?? 0,
                        'total_L7_sales'  => $matchedCampaignHlL7?->sales ?? 0,
                        'total_L30_sold'  => $matchedCampaignHlL30?->unitsSold ?? 0,
                        'total_L7_sold'   => $matchedCampaignHlL7?->unitsSold ?? 0,
                        'total_L30_impr'  => $matchedCampaignHlL30?->impressions ?? 0,
                        'total_L7_impr'   => $matchedCampaignHlL7?->impressions ?? 0,
                        'total_L30_clicks'=> $matchedCampaignHlL30?->clicks ?? 0,
                        'total_L7_clicks' => $matchedCampaignHlL7?->clicks ?? 0,
                        'childCount'=> $childCount,
                    ];
                }
            }

            $total_spend_l30 = 0;  
            $total_kw_spend_l30 = 0;    
            $total_pt_spend_l30 = 0;
            $total_hl_spend_l30 = 0;
            $total_clicks_l30 = 0;
            $total_kw_clicks_l30 = 0;
            $total_pt_clicks_l30 = 0;
            $total_hl_clicks_l30 = 0;
            $total_sales_l30 = 0;
            $total_kw_sales_l30 = 0;
            $total_pt_sales_l30 = 0;
            $total_hl_sales_l30 = 0;
            $total_sold_l30 = 0;
            $total_kw_sold_l30 = 0;
            $total_pt_sold_l30 = 0;
            $total_hl_sold_l30 = 0;

            foreach ($productMasters as $pm) {
                $sku = strtoupper($pm->sku);
                $parent = trim($pm->parent ?? '');
                if (str_starts_with($sku, 'PARENT') && !$parent) {
                    $parent = $pm->sku;
                }

                $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
                $shopify = $shopifyData[$sku] ?? null; // Normalized key lookup

                // Campaign matching: SKU first, then PARENT_SKU fallback (only for parent rows)
                $isParentRow = str_starts_with($sku, 'PARENT');
                $matchedCampaignKwL30 = static::matchKwCampaign($amazonKwL30, $pm->sku, $parent ?: null, $isParentRow);
                $matchedCampaignPtL30 = static::matchPtCampaign($amazonPtL30, $pm->sku, $parent ?: null, true, $isParentRow);
                $matchedCampaignHlL30 = static::matchHlCampaign($amazonHlL30, $pm->sku, $parent ?: null, $isParentRow);

                $row = [];
                $row['parent'] = $parent;
                $row['sku'] = $pm->sku;
                // Inventory: parent rows = SUM(children), child rows = own inv
                if (str_starts_with($sku, 'PARENT')) {
                    $row['INV'] = (float) ($inventoryByParent[strtoupper($parent)] ?? 0);
                } else {
                    $row['INV'] = (float) ($shopify?->inv ?? 0);
                }
                $row['L30'] = $shopify?->quantity ?? 0;
                $row['fba'] = $pm->fba ?? null;
                $row['A_L30'] = $amazonSheet->units_ordered_l30 ?? 0;

                $row['kw_spend_L30']  = $matchedCampaignKwL30?->spend ?? 0;
                $row['kw_sold_L30']  = $matchedCampaignKwL30?->unitsSoldSameSku30d ?? 0;
                $row['kw_sales_L30']  = $matchedCampaignKwL30?->sales30d ?? 0;
                $row['kw_clicks_L30'] = $matchedCampaignKwL30?->clicks ?? 0;
                $row['pt_clicks_L30'] = $matchedCampaignPtL30?->clicks ?? 0;
                $row['pt_spend_L30']  = $matchedCampaignPtL30?->spend ?? 0;
                $row['hl_clicks_L30'] = $matchedCampaignHlL30?->clicks ?? 0;
                $row['pt_sales_L30']  = $matchedCampaignPtL30?->sales30d ?? 0;
                $row['hl_sales_L30']  = $matchedCampaignHlL30?->sales ?? 0;
                $row['hl_sold_L30']  = $matchedCampaignHlL30?->unitsSold ?? 0;
                $row['pt_sold_L30']  = $matchedCampaignPtL30?->unitsSoldSameSku30d ?? 0;

                if (str_starts_with($sku, 'PARENT')) {

                    $row['hl_spend_L30'] = $matchedCampaignHlL30?->cost ?? 0;
                    $row['hl_clicks_L30'] = $matchedCampaignHlL30->clicks ?? 0;
                    $row['hl_sales_L30']  = $matchedCampaignHlL30->sales ?? 0;
                    $row['hl_sold_L30']  = $matchedCampaignHlL30->unitsSold ?? 0;

                } elseif (isset($parentHlSpendData[$parent]) && $parentHlSpendData[$parent]['childCount'] > 0) {

                    $row['hl_spend_L30'] = $parentHlSpendData[$parent]['total_L30'] / $parentHlSpendData[$parent]['childCount'];
                    $row['hl_clicks_L30'] = $parentHlSpendData[$parent]['total_L30_clicks'] / $parentHlSpendData[$parent]['childCount'];
                    $row['hl_sales_L30']  = $parentHlSpendData[$parent]['total_L30_sales'] / $parentHlSpendData[$parent]['childCount'];
                    $row['hl_sold_L30']  = $parentHlSpendData[$parent]['total_L30_sold'] / $parentHlSpendData[$parent]['childCount'];

                } else {

                    $row['hl_spend_L30'] = 0;
                    $row['hl_clicks_L30'] = 0;
                    $row['hl_sales_L30'] = 0;
                    $row['hl_sold_L30']  = 0;
                }
            
                // Calculate total row metrics (KW + PT + HL)
                $row['SPEND_L30'] = $row['pt_spend_L30'] + $row['kw_spend_L30'] + $row['hl_spend_L30'];
                $row['CLICKS_L30'] = $row['pt_clicks_L30'] + $row['kw_clicks_L30'] + $row['hl_clicks_L30'];
                $row['SALES_L30'] = $row['pt_sales_L30'] + $row['kw_sales_L30'] + $row['hl_sales_L30'];
                $row['SOLD_L30'] = $row['pt_sold_L30'] + $row['kw_sold_L30'] + $row['hl_sold_L30'];


                $invFilterVal = '';
                $inv = floatval($row['INV'] ?? 0);
                if (!$invFilterVal && $inv == 0) {
                    continue;
                }
                if ($invFilterVal === 'INV_0' && $inv != 0) {
                    continue;
                }
                if ($invFilterVal === 'OTHERS' && $inv == 0) {
                    continue;
                }

                $sku2 = strtolower(trim($row['sku'] ?? ''));
                if (strpos($sku2, 'parent ') !== false) {
                    continue;
                }

                $total_spend_l30 = $total_spend_l30 + (is_numeric($row['SPEND_L30']) ? (float)$row['SPEND_L30'] : 0);
                $total_kw_spend_l30 = $total_kw_spend_l30 + (is_numeric($row['kw_spend_L30']) ? (float)$row['kw_spend_L30'] : 0);
                $total_pt_spend_l30 = $total_pt_spend_l30 + (is_numeric($row['pt_spend_L30']) ? (float)$row['pt_spend_L30'] : 0);
                $total_hl_spend_l30 = $total_hl_spend_l30 + (is_numeric($row['hl_spend_L30']) ? (float)$row['hl_spend_L30'] : 0);
                $total_clicks_l30 = $total_clicks_l30 + (is_numeric($row['CLICKS_L30']) ? (float)$row['CLICKS_L30'] : 0);
                $total_kw_clicks_l30 = $total_kw_clicks_l30 + (is_numeric($row['kw_clicks_L30']) ? (float)$row['kw_clicks_L30'] : 0);
                $total_pt_clicks_l30 = $total_pt_clicks_l30 + (is_numeric($row['pt_clicks_L30']) ? (float)$row['pt_clicks_L30'] : 0);
                $total_hl_clicks_l30 = $total_hl_clicks_l30 + (is_numeric($row['hl_clicks_L30']) ? (float)$row['hl_clicks_L30'] : 0);
                $total_sales_l30 = $total_sales_l30 + (is_numeric($row['SALES_L30']) ? (float)$row['SALES_L30'] : 0);
                $total_kw_sales_l30 = $total_kw_sales_l30 + (is_numeric($row['kw_sales_L30']) ? (float)$row['kw_sales_L30'] : 0);
                $total_pt_sales_l30 = $total_pt_sales_l30 + (is_numeric($row['pt_sales_L30']) ? (float)$row['pt_sales_L30'] : 0);
                $total_hl_sales_l30 = $total_hl_sales_l30 + (is_numeric($row['hl_sales_L30']) ? (float)$row['hl_sales_L30'] : 0);      
                $total_sold_l30 = $total_sold_l30 + (is_numeric($row['SOLD_L30']) ? (float)$row['SOLD_L30'] : 0);
                $total_kw_sold_l30 = $total_kw_sold_l30 + (is_numeric($row['kw_sold_L30']) ? (float)$row['kw_sold_L30'] : 0); 
                $total_pt_sold_l30 = $total_pt_sold_l30 + (is_numeric($row['pt_sold_L30']) ? (float)$row['pt_sold_L30'] : 0);
                $total_hl_sold_l30 = $total_hl_sold_l30 + (is_numeric($row['hl_sold_L30']) ? (float)$row['hl_sold_L30'] : 0);
         
            }
        
            // Use direct calculation for all metrics (same as amazonKwAdsView, amazonPtAdsView, amazonHlAdsView)
            // Total Amazon = KW + PT + HL (sum of rounded individual values to match frontend)
            $total_kw_spend_l30 = round($total_kw_spend_l30_direct);
            $total_pt_spend_l30 = round($total_pt_spend_l30_direct);
            $total_hl_spend_l30 = round($total_hl_spend_l30_direct);
            // Sum of rounded values (not round of sum) to match frontend calculation
            $total_spend_l30 = $total_kw_spend_l30 + $total_pt_spend_l30 + $total_hl_spend_l30;
            
            // Total clicks = KW + PT + HL (sum of rounded individual values)
            $total_kw_clicks_l30 = round($total_kw_clicks_l30_direct);
            $total_pt_clicks_l30 = round($total_pt_clicks_l30_direct);
            $total_hl_clicks_l30 = round($total_hl_clicks_l30_direct);
            $total_clicks_l30 = $total_kw_clicks_l30 + $total_pt_clicks_l30 + $total_hl_clicks_l30;
            
            // Total ad sales = KW + PT + HL (sum of rounded individual values)
            $total_kw_sales_l30 = round($total_kw_sales_l30_direct);
            $total_pt_sales_l30 = round($total_pt_sales_l30_direct);
            $total_hl_sales_l30 = round($total_hl_sales_l30_direct);
            $total_sales_l30 = $total_kw_sales_l30 + $total_pt_sales_l30 + $total_hl_sales_l30;
            
            // Total ad sold = KW + PT + HL (sum of rounded individual values)
            $total_kw_sold_l30 = round($total_kw_sold_l30_direct);
            $total_pt_sold_l30 = round($total_pt_sold_l30_direct);
            $total_hl_sold_l30 = round($total_hl_sold_l30_direct);
            $total_sold_l30 = $total_kw_sold_l30 + $total_pt_sold_l30 + $total_hl_sold_l30;        

            $todayDate = date('Y-m-d');

            // Calculate CPC, CVR, ACOS, TACOS for all channels
            $amazon_cpc = $total_clicks_l30 > 0 ? round($total_spend_l30 / $total_clicks_l30, 2) : 0;
            $amazon_cvr = $total_clicks_l30 > 0 ? round(($total_sold_l30 / $total_clicks_l30) * 100, 2) : 0;
            $amazon_acos = $total_sales_l30 > 0 ? round(($total_spend_l30 / $total_sales_l30) * 100, 2) : 0;
            $amazon_tacos = 0; // Will be calculated when l30_sales is available from total sales cron
            
            $kw_cpc = $total_kw_clicks_l30 > 0 ? round($total_kw_spend_l30 / $total_kw_clicks_l30, 2) : 0;
            $kw_cvr = $total_kw_clicks_l30 > 0 ? round(($total_kw_sold_l30 / $total_kw_clicks_l30) * 100, 2) : 0;
            $kw_acos = $total_kw_sales_l30 > 0 ? round(($total_kw_spend_l30 / $total_kw_sales_l30) * 100, 2) : 0;
            $kw_tacos = 0; // TACOS requires l30_sales which is not available in this cron
            
            $pt_cpc = $total_pt_clicks_l30 > 0 ? round($total_pt_spend_l30 / $total_pt_clicks_l30, 2) : 0;
            $pt_cvr = $total_pt_clicks_l30 > 0 ? round(($total_pt_sold_l30 / $total_pt_clicks_l30) * 100, 2) : 0;
            $pt_acos = $total_pt_sales_l30 > 0 ? round(($total_pt_spend_l30 / $total_pt_sales_l30) * 100, 2) : 0;
            $pt_tacos = 0; // TACOS requires l30_sales which is not available in this cron
            
            $hl_cpc = $total_hl_clicks_l30 > 0 ? round($total_hl_spend_l30 / $total_hl_clicks_l30, 2) : 0;
            $hl_cvr = $total_hl_clicks_l30 > 0 ? round(($total_hl_sold_l30 / $total_hl_clicks_l30) * 100, 2) : 0;
            $hl_acos = $total_hl_sales_l30 > 0 ? round(($total_hl_spend_l30 / $total_hl_sales_l30) * 100, 2) : 0;
            $hl_tacos = 0; // TACOS requires l30_sales which is not available in this cron

            // Save daily data for main channel only (not sub-channels)
            ADVMastersDailyData::updateOrCreate(
                ['date' => $todayDate, 'channel' => 'AMAZON'],
                [
                    'spent' => $total_spend_l30,
                    'clicks' => $total_clicks_l30,
                    'ad_sales' => $total_sales_l30,
                    'ad_sold' => $total_sold_l30,
                    'cpc' => $amazon_cpc,
                    'cvr' => $amazon_cvr,
                    'acos' => $amazon_acos,
                    'tacos' => $amazon_tacos,
                    'gpft' => 0, // GPFT calculation requires additional data (profit margins)
                    'tpft' => 0  // TPFT calculation requires additional data (profit margins)
                ]
            );


            $updateAmazon = ADVMastersData::updateOrCreate(
                ['channel' => 'AMAZON'],
                [
                    'spent' => $total_spend_l30,
                    'clicks' => $total_clicks_l30,
                    'ad_sales' => $total_sales_l30,
                    'ad_sold' => $total_sold_l30
                ]
            );

            $updateAmazonKw = ADVMastersData::updateOrCreate(
                ['channel' => 'AMZ KW'],
                [
                    'spent' => $total_kw_spend_l30,
                    'clicks' => $total_kw_clicks_l30,
                    'ad_sales' => $total_kw_sales_l30,
                    'ad_sold' => $total_kw_sold_l30
                ]
            );

            $updateAmazonPt = ADVMastersData::updateOrCreate(
                ['channel' => 'AMZ PT'],
                [
                    'spent' => $total_pt_spend_l30,
                    'clicks' => $total_pt_clicks_l30,
                    'ad_sales' => $total_pt_sales_l30,
                    'ad_sold' => $total_pt_sold_l30
                ]
            );

            $updateAmazonHl = ADVMastersData::updateOrCreate(
                ['channel' => 'AMZ HL'],
                [
                    'spent' => $total_hl_spend_l30,
                    'clicks' => $total_hl_clicks_l30,
                    'ad_sales' => $total_hl_sales_l30,
                    'ad_sold' => $total_hl_sold_l30
                ]
            );
            
            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    protected function getChannelAdvMasterAmazonCronMissingDataProceed($request)
    { 
        try {
        DB::beginTransaction();
            // Increase memory limit for this operation
            ini_set('memory_limit', '512M');
            
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
            $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
                return strtoupper($item->sku);
            });

            // Normalized key for case-insensitive lookup
            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy(function ($item) {
                return strtoupper(trim($item->sku));
            });
            $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

            $amazonKwCampaigns = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
            })
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

            $amazonPtCampaigns = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

            $result = [];
            $bothRunning = 0;
            $ptMissing = 0;
            $kwMissing = 0;
            $bothMissing = 0;
            $kwRunning = 0;
            $ptRunning = 0;
            $totalMissingAds = 0;
            $totalMissingAds2 = 0;
            foreach ($productMasters as $pm) {
                $sku = strtoupper($pm->sku);
                $parent = trim($pm->parent ?? '');

                $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
                $shopify = $shopifyData[$sku] ?? null; // Normalized key

                // Campaign matching: SKU first, PARENT_SKU fallback only for parent rows
                $isParentRow = str_starts_with($sku, 'PARENT');
                $matchedKwCampaign = static::matchKwCampaign($amazonKwCampaigns, $pm->sku, $parent ?: null, $isParentRow);
                $matchedPtCampaign = static::matchPtCampaign($amazonPtCampaigns, $pm->sku, $parent ?: null, false, $isParentRow);

                $row = [
                    'parent' => $parent,
                    'sku' => $pm->sku,
                    'INV' => $shopify?->inv ?? 0,
                    'L30' => $shopify?->quantity ?? 0,
                    'A_L30' => $amazonSheet->units_ordered_l30 ?? 0,
                    'kw_campaign_name' => $matchedKwCampaign?->campaignName ?? '',
                    'pt_campaign_name' => $matchedPtCampaign?->campaignName ?? '',
                    'campaignStatus' => $matchedKwCampaign?->campaignStatus ?? '',
                    'NRL' => '',
                    'NRA' => '',
                    'FBA' => '',
                ];

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

                $sku = $row['sku'] ?? '';
                $isParent = str_contains(strtoupper($sku), 'PARENT');
                if ($isParent) {
                    continue;
                }

                $kw = $row['kw_campaign_name'] ?? '';
                $pt = $row['pt_campaign_name'] ?? '';
                $nra = isset($row['NRA']) ? trim($row['NRA']) : '';

                if ($nra !== 'NRA' && ((float)($row['INV'] ?? 0)) > 0) {
                    if (!empty($kw) && !empty($pt)) {
                        $bothRunning++;
                    } elseif (!empty($kw) && empty($pt)) {
                        $ptMissing++;
                    } elseif (empty($kw) && !empty($pt)) {
                        $kwMissing++;
                    } else {
                        $bothMissing++;
                    }
                }

                if ($nra !== 'NRA' && ((float)($row['INV'] ?? 0)) > 0) {
                    if (!empty($kw)) {
                        $kwRunning++;
                    }
                    if (!empty($pt)) {
                        $ptRunning++;
                    }
                }

                if ($nra !== 'NRA' && ((float)($row['INV'] ?? 0)) > 0) {
                    $totalMissingAds  = (float)$ptMissing + (float)$kwMissing + (float)$bothMissing;
                    $totalMissingAds2 = (float)$ptMissing + (float)$kwMissing + (float)$bothMissing;
                }          
            }

            $kwMissing = $kwMissing + $bothMissing;
            $ptMissing = $ptMissing + $bothMissing;
            $todayDate = date('Y-m-d');
            $totalMissingAds = round($totalMissingAds);
            $kwMissing = round($kwMissing);
            $ptMissing = round($ptMissing);

            // Save missing ads daily data for main channel and sub-channels
            // Save daily data for main channel only (not sub-channels)
            ADVMastersDailyData::updateOrCreate(
                ['date' => $todayDate, 'channel' => 'AMAZON'],
                ['missing_ads' => $totalMissingAds]
            );

            $updateAmazon = ADVMastersData::updateOrCreate(
                ['channel' => 'AMAZON'],
                ['missing_ads' => $totalMissingAds]
            );

            $updateAmazonKw = ADVMastersData::updateOrCreate(
                ['channel' => 'AMZ KW'],
                ['missing_ads' => $kwMissing]
            );

            $updateAmazonPt = ADVMastersData::updateOrCreate(
                ['channel' => 'AMZ PT'],
                ['missing_ads' => $ptMissing]
            );

         DB::commit();
            
            // Free memory
            unset($productMasters, $amazonDatasheetsBySku, $shopifyData, $nrValues, $amazonKwCampaigns, $amazonPtCampaigns, $result);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack();
            return 0;
        }
    }

    protected function getChannelAdvMasterAmazonCronTotalSaleDataProceed($request)
    {
        try {
        DB::beginTransaction();
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();
            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
            $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
                return strtoupper($item->sku);
            });
            $result = [];
            $total_sales = 0;
            foreach ($productMasters as $pm) {
                $sku = strtoupper($pm->sku);
                if (str_starts_with($sku, 'PARENT ')) {
                    continue;
                }
                $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
                $row = [];

                if ($amazonSheet) {
                    $row['A_L30'] = $amazonSheet->units_ordered_l30 ?? 0;
                    $row['price'] = $amazonSheet->price ?? 0;
                } else {
                    $row['A_L30'] = 0;
                    $row['price'] = 0;
                }

                $price = floatval($row['price'] ?? 0);
                $units_ordered_l30 = floatval($row['A_L30'] ?? 0);
                $row['T_Sale_l30'] = round($price * $units_ordered_l30, 2);  
                $total_sales = $total_sales + $row['T_Sale_l30'];         
            }
            $total_sales = round($total_sales);
    
            $todayDate = date('Y-m-d');
            
            // Get current spent value to calculate TACOS
            $amazonData = ADVMastersData::where('channel', 'AMAZON')->first();
            $current_spent = $amazonData->spent ?? 0;
            $tacos = $total_sales > 0 ? round(($current_spent / $total_sales) * 100, 2) : 0;
            
            // Save L30 sales daily data and update TACOS
            ADVMastersDailyData::updateOrCreate(
                ['date' => $todayDate, 'channel' => 'AMAZON'],
                [
                    'l30_sales' => $total_sales,
                    'tacos' => $tacos
                ]
            );
            
            // TACOS is already calculated and saved for main AMAZON channel above

            $updateAmazon = ADVMastersData::updateOrCreate(
                ['channel' => 'AMAZON'],
                ['l30_sales' => $total_sales]
            );

            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    public static function getChannelAdvMasterEbayCronDataProceed($request)
    {
        try {
        DB::beginTransaction();
            $normalizeSku = fn($sku) => strtoupper(trim($sku));
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            $skus = $productMasters->pluck('sku')->filter()->map($normalizeSku)->unique()->values()->all();
            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->sku));
            $ebayMetricData = EbayMetric::whereIn('sku', $skus)
                ->select('sku', 'ebay_price', 'item_id', 'ebay_l30')
                ->get()
                ->keyBy(fn($item) => $normalizeSku($item->sku));

            $ebayCampaignReportsL30 = EbayPriorityReport::where('report_range', 'L30')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) {
                        $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                    }
                })->get();
            $ebayCampaignReportsL7 = EbayPriorityReport::where('report_range', 'L7')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) {
                        $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                    }
                })->get();

            $itemIds = $ebayMetricData->pluck('item_id')->filter()->toArray();
            $ebayGeneralReportsL30 = !empty($itemIds) 
                ? EbayGeneralReport::where('report_range', 'L30')
                    ->whereIn('listing_id', $itemIds)
                    ->get()
                : collect();
            
            $total_kw_spend_l30 = 0;
            $total_kw_clicks_l30 = 0;
            $total_kw_sales_l30 = 0;
            $total_kw_sold_l30 = 0;
            $total_pmt_spend_l30 = 0;
            $total_pmt_clicks_l30 = 0;
            $total_pmt_sales_l30 = 0;
            $total_pmt_sold_l30 = 0;
            $total_spend_l30 = 0;
            $total_click_l30 = 0;
            $total_sales_l30 = 0;
            $total_sold_l30 = 0;

            foreach ($productMasters as $pm) {
                $sku = strtoupper($pm->sku);
                $parent = $pm->parent;

                $shopify = $shopifyData[$sku] ?? null;
                $ebay = $ebayMetricData[$sku] ?? null;

                $matchedCampaignL30 = $ebayCampaignReportsL30->first(function ($item) use ($sku) {
                    return strtoupper(trim($item->campaign_name)) === strtoupper(trim($sku));
                });

                $matchedCampaignL7 = $ebayCampaignReportsL7->first(function ($item) use ($sku) {
                    return strtoupper(trim($item->campaign_name)) === strtoupper(trim($sku));
                });
                
                $matchedGeneralL30 = null;
                if ($ebay && !empty($ebay->item_id)) {
                    $matchedGeneralL30 = $ebayGeneralReportsL30->first(function ($item) use ($ebay) {
                        return trim((string)$item->listing_id) == trim((string)$ebay->item_id);
                    });
                }

                $row = [];
                $row['parent'] = $parent;
                $row['sku'] = $pm->sku;
                $row['INV'] = $shopify->inv ?? 0;
                $row['L30'] = $shopify->quantity ?? 0;
                $row['e_l30'] = $ebay ? ($ebay->ebay_l30 ?? 0) : 0;
                // Use L30 campaign name for checking since we're calculating L30 data
                $row['campaignName'] = $matchedCampaignL30 ? ($matchedCampaignL30->campaign_name ?? '') : '';

                // Extract KW data - check if matchedCampaignL30 exists and has data
                if ($matchedCampaignL30) {
                    $row['kw_spend_L30'] = (float) str_replace(['USD ', ','], '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? '0');
                    $row['kw_clicks_L30'] = (int) ($matchedCampaignL30->cpc_clicks ?? 0);
                    $row['kw_sales_L30'] = (float) str_replace(['USD ', ','], '', $matchedCampaignL30->cpc_sale_amount_payout_currency ?? '0');
                    $row['kw_sold_L30'] = (int) ($matchedCampaignL30->cpc_attributed_sales ?? 0);
                } else {
                    $row['kw_spend_L30'] = 0;
                    $row['kw_clicks_L30'] = 0;
                    $row['kw_sales_L30'] = 0;
                    $row['kw_sold_L30'] = 0;
                }
            
                // PMT data extraction with null check
                if ($matchedGeneralL30) {
                    $row['pmt_spend_L30'] = (float) str_replace(['USD ', ','], '', $matchedGeneralL30->ad_fees ?? '0');
                    $row['pmt_clicks_L30'] = (int) ($matchedGeneralL30->clicks ?? 0);
                    $row['pmt_sales_L30'] = (float) str_replace(['USD ', ','], '', $matchedGeneralL30->sale_amount ?? '0');
                    $row['pmt_sold_L30'] = (int) ($matchedGeneralL30->sales ?? 0);
                } else {
                    $row['pmt_spend_L30'] = 0;
                    $row['pmt_clicks_L30'] = 0;
                    $row['pmt_sales_L30'] = 0;
                    $row['pmt_sold_L30'] = 0;
                }

                
                $row['SPEND_L30'] = $row['kw_spend_L30'] + $row['pmt_spend_L30'];
                $row['CLICKS_L30'] = $row['kw_clicks_L30'] + $row['pmt_clicks_L30'];
                $row['SALES_L30'] = $row['kw_sales_L30'] + $row['pmt_sales_L30'];
                $row['SOLD_L30'] = $row['kw_sold_L30'] + $row['pmt_sold_L30'];

                $sku = strtolower(trim($row['sku'] ?? ''));
                if (strpos($sku, 'parent ') == false)
                {
                    // Count KW data only if KW campaign exists
                    if($matchedCampaignL30 && $row['campaignName'] !== '')
                    {
                        $total_kw_spend_l30 = $total_kw_spend_l30 + $row['kw_spend_L30']; 
                        $total_kw_clicks_l30 = $total_kw_clicks_l30 + $row['kw_clicks_L30'];
                        $total_kw_sales_l30 = $total_kw_sales_l30 + $row['kw_sales_L30']; 
                        $total_kw_sold_l30 = $total_kw_sold_l30 + $row['kw_sold_L30']; 
                    }
                    
                    // Count PMT data separately (even if KW campaign doesn't exist)
                    $total_pmt_spend_l30 = $total_pmt_spend_l30 + $row['pmt_spend_L30']; 
                    $total_pmt_clicks_l30 = $total_pmt_clicks_l30 + $row['pmt_clicks_L30']; 
                    $total_pmt_sales_l30 = $total_pmt_sales_l30 + $row['pmt_sales_L30'];
                    $total_pmt_sold_l30 = $total_pmt_sold_l30 + $row['pmt_sold_L30'];
                    
                    // Total = KW + PMT (always count both, even if KW doesn't exist)
                    $total_spend_l30 = $total_spend_l30 + $row['SPEND_L30'];
                    $total_click_l30 = $total_click_l30 + $row['CLICKS_L30']; 
                    $total_sales_l30 = $total_sales_l30 + $row['SALES_L30']; 
                    $total_sold_l30 = $total_sold_l30 + $row['SOLD_L30'];
                }
            }

            $todayDate = date('Y-m-d');
            $total_spend_l30 = round($total_spend_l30);
            $total_click_l30 = round($total_click_l30);
            $total_sales_l30 = round($total_sales_l30); 
            $total_sold_l30 = round($total_sold_l30);
            $total_pmt_spend_l30 = round($total_pmt_spend_l30); 
            $total_pmt_clicks_l30 = round($total_pmt_clicks_l30); 
            $total_pmt_sales_l30 = round($total_pmt_sales_l30);
            $total_pmt_sold_l30 = round($total_pmt_sold_l30);
            $total_kw_spend_l30 = round($total_kw_spend_l30); 
            $total_kw_clicks_l30 = round($total_kw_clicks_l30);
            $total_kw_sales_l30 = round($total_kw_sales_l30);
            $total_kw_sold_l30 = round($total_kw_sold_l30);
            
            // Calculate CPC, CVR, ACOS for all channels
            $ebay_cpc = $total_click_l30 > 0 ? round($total_spend_l30 / $total_click_l30, 2) : 0;
            $ebay_cvr = $total_click_l30 > 0 ? round(($total_sold_l30 / $total_click_l30) * 100, 2) : 0;
            $ebay_acos = $total_sales_l30 > 0 ? round(($total_spend_l30 / $total_sales_l30) * 100, 2) : 0;
            $ebay_tacos = 0; // Will be calculated when l30_sales is available from total sales cron
            
            $ebay_kw_cpc = $total_kw_clicks_l30 > 0 ? round($total_kw_spend_l30 / $total_kw_clicks_l30, 2) : 0;
            $ebay_kw_cvr = $total_kw_clicks_l30 > 0 ? round(($total_kw_sold_l30 / $total_kw_clicks_l30) * 100, 2) : 0;
            $ebay_kw_acos = $total_kw_sales_l30 > 0 ? round(($total_kw_spend_l30 / $total_kw_sales_l30) * 100, 2) : 0;
            $ebay_kw_tacos = 0; // TACOS requires l30_sales which is not available in this cron
            
            $ebay_pmt_cpc = $total_pmt_clicks_l30 > 0 ? round($total_pmt_spend_l30 / $total_pmt_clicks_l30, 2) : 0;
            $ebay_pmt_cvr = $total_pmt_clicks_l30 > 0 ? round(($total_pmt_sold_l30 / $total_pmt_clicks_l30) * 100, 2) : 0;
            $ebay_pmt_acos = $total_pmt_sales_l30 > 0 ? round(($total_pmt_spend_l30 / $total_pmt_sales_l30) * 100, 2) : 0;
            $ebay_pmt_tacos = 0; // TACOS requires l30_sales which is not available in this cron
            
            // Save daily data for main channel only (not sub-channels)
            ADVMastersDailyData::updateOrCreate(
                ['date' => $todayDate, 'channel' => 'EBAY'],
                [
                    'spent' => $total_spend_l30,
                    'clicks' => $total_click_l30,
                    'ad_sales' => $total_sales_l30,
                    'ad_sold' => $total_sold_l30,
                    'cpc' => $ebay_cpc,
                    'cvr' => $ebay_cvr,
                    'acos' => $ebay_acos,
                    'tacos' => $ebay_tacos,
                    'gpft' => 0, // GPFT calculation requires additional data (profit margins)
                    'tpft' => 0  // TPFT calculation requires additional data (profit margins)
                ]
            );
        

            $updateEbay = ADVMastersData::updateOrCreate(
                ['channel' => 'EBAY'],
                [
                    'spent' => $total_spend_l30,
                    'clicks' => $total_click_l30,
                    'ad_sales' => $total_sales_l30,
                    'ad_sold' => $total_sold_l30
                ]
            );

            $updateEbayKw = ADVMastersData::updateOrCreate(
                ['channel' => 'EB KW'],
                [
                    'spent' => $total_kw_spend_l30,
                    'clicks' => $total_kw_clicks_l30,
                    'ad_sales' => $total_kw_sales_l30,
                    'ad_sold' => $total_kw_sold_l30
                ]
            );

            $updateEbayPmt = ADVMastersData::updateOrCreate(
                ['channel' => 'EB PMT'],
                [
                    'spent' => $total_pmt_spend_l30,
                    'clicks' => $total_pmt_clicks_l30,
                    'ad_sales' => $total_pmt_sales_l30,
                    'ad_sold' => $total_pmt_sold_l30
                ]
            );

            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    public static function getChannelAdvMasterEbayCronMissingDataProceed($request)
    {
        try {
        DB::beginTransaction();

            $normalizeSku = fn($sku) => strtoupper(trim($sku));
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            if ($productMasters->isEmpty()) {
                return response()->json([
                    'message' => 'No product masters found',
                    'data'    => [],
                    'status'  => 200,
                ]);
            }

            $skus = $productMasters->pluck('sku')->filter()->map($normalizeSku)->unique()->values()->all();

            // Fetch all required data
            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->sku));
            $nrValues = EbayDataView::whereIn('sku', $skus)->pluck('value', 'sku');
            $ebayMetricData = EbayMetric::whereIn('sku', $skus)
                ->select('sku', 'ebay_price', 'item_id', 'ebay_l30')
                ->get()
                ->keyBy(fn($item) => $normalizeSku($item->sku));

            // Fetch campaign reports and create efficient lookup
            $ebayCampaignReports = EbayPriorityReport::where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })->get();

            $campaignLookup = [];
            foreach ($ebayCampaignReports as $campaign) {
                foreach ($skus as $sku) {
                    if (strpos($campaign->campaign_name, $sku) !== false) {
                        if (!isset($campaignLookup[$sku])) {
                            $campaignLookup[$sku] = $campaign;
                        }
                    }
                }
            }
            $campaignListings = DB::connection('apicentral')
                ->table('ebay_campaign_ads_listings')
                ->select('listing_id', 'bid_percentage')
                ->get()
                ->keyBy('listing_id')
                ->toArray();

            $result = [];
            $bothRunning = 0;
            $ptMissing = 0;
            $kwMissing = 0;
            $bothMissing = 0;
            $totalMissingAds = 0;

            foreach ($productMasters as $pm) {
                $sku = strtoupper($pm->sku);
                $shopify = $shopifyData->get($sku);
                $ebayMetric = $ebayMetricData->get($sku);
                $campaignReport = $campaignLookup[$sku] ?? null;
                
                $nrActual = null;
                if (isset($nrValues[$pm->sku])) {
                    $raw = $nrValues[$pm->sku];
                    if (!is_array($raw)) {
                        $raw = json_decode($raw, true);
                    }
                    if (is_array($raw)) {
                        $nrActual = $raw['NRA'] ?? null;
                    }
                }

                $sku2 = $sku ?? '';
                $isParent = str_contains(strtoupper($sku2), 'PARENT');
                if ($isParent) {
                    continue;
                }

                $pmt_bid_percentage = ($ebayMetric && isset($ebayMetric->item_id) && isset($campaignListings[$ebayMetric->item_id])) 
                        ? $campaignListings[$ebayMetric->item_id]->bid_percentage : null;

                $kw = $campaignReport->campaign_name ?? '';
                $pt = $pmt_bid_percentage ?? ''; 
                $nra = trim($nrActual ?? '');

                if ($nra !== "NRA") {
                    if (!empty($kw) && !empty($pt)) {
                        $bothRunning++;
                    } elseif (!empty($kw) && empty($pt)) {
                        $ptMissing++;
                    } elseif (empty($kw) && !empty($pt)) {
                        $kwMissing++;
                    } else {
                        $bothMissing++;
                    }
                }

                if ($nra !== "NRA") {
                    $totalMissingAds = floatval($ptMissing) + floatval($kwMissing) + floatval($bothMissing);
                }         
            }

            $todayDate = date('Y-m-d');
            $kwMissing = $kwMissing + $bothMissing;
            $ptMissing = $ptMissing + $bothMissing;

            // Save missing ads daily data for main channel and sub-channels
            // Save daily data for main channel only (not sub-channels)
            ADVMastersDailyData::updateOrCreate(
                ['date' => $todayDate, 'channel' => 'EBAY'],
                ['missing_ads' => $totalMissingAds]
            );
        

            $updateEbay = ADVMastersData::updateOrCreate(
                ['channel' => 'EBAY'],
                ['missing_ads' => $totalMissingAds]
            );

            $updateEbayKw = ADVMastersData::updateOrCreate(
                ['channel' => 'EB KW'],
                ['missing_ads' => $kwMissing]
            );

            $updateEbayPmt = ADVMastersData::updateOrCreate(
                ['channel' => 'EB PMT'],
                ['missing_ads' => $ptMissing]
            );
        
            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    public static function getChannelAdvMasterEbayCronTotalSaleDataProceed($request)
    {
        try {
        DB::beginTransaction();
            // 1. Base ProductMaster fetch
            $productMasters = ProductMaster::orderBy("parent", "asc")
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy("sku", "asc")
                ->get();
            // 2. SKU list
            $skus = $productMasters->pluck("sku")
                ->filter()
                ->unique()
                ->values()
                ->all();
            // 3. Related Models
            $shopifyData = ShopifySku::whereIn("sku", $skus)
                ->get()
                ->keyBy("sku");
            $ebayMetrics = EbayMetric::whereIn('sku', $skus)->get()->keyBy('sku');

            $total_ebay_sales = 0;
            foreach ($productMasters as $pm) {
                $sku = strtoupper($pm->sku);
                // Skip parent SKUs
                if (str_starts_with($sku, 'PARENT ')) {
                    continue;
                }
                
                $parent = $pm->parent;

                $shopify = $shopifyData[$pm->sku] ?? null;
                $ebayMetric = $ebayMetrics[$pm->sku] ?? null;
                $row = [];
                $row["Parent"] = $parent;
                $row["(Child) sku"] = $pm->sku;
                $row['fba'] = $pm->fba;
                $row["INV"] = $shopify->inv ?? 0;
                $row["L30"] = $shopify->quantity ?? 0;
                $row["eBay L30"] = $ebayMetric ? ($ebayMetric->ebay_l30 ?? 0) : 0;
                $row["eBay Price"] = $ebayMetric ? ($ebayMetric->ebay_price ?? 0) : 0;
    
                $total_ebay_sales = $total_ebay_sales + ($row["eBay L30"] * floatval($row["eBay Price"]));
            
            }
            $total_ebay_sales = round($total_ebay_sales);
            $todayDate = date('Y-m-d');
            
            // Get current spent value to calculate TACOS
            $ebayData = ADVMastersData::where('channel', 'EBAY')->first();
            $current_spent = $ebayData->spent ?? 0;
            $tacos = $total_ebay_sales > 0 ? round(($current_spent / $total_ebay_sales) * 100, 2) : 0;
            
            // Save L30 sales daily data and update TACOS
            ADVMastersDailyData::updateOrCreate(
                ['date' => $todayDate, 'channel' => 'EBAY'],
                [
                    'l30_sales' => $total_ebay_sales,
                    'tacos' => $tacos
                ]
            );
            
            // TACOS is already calculated and saved for main EBAY channel above

            $updateEbay = ADVMastersData::updateOrCreate(
                ['channel' => 'EBAY'],
                ['l30_sales' => $total_ebay_sales]
            );
            
            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }


}
