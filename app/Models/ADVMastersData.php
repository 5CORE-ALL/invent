<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ADVMastersDailyData;
use DB;

class ADVMastersData extends Model
{
    use HasFactory;

    protected $table = 'adv_masters_datas';
    protected $primaryKey = 'adv_masters_data_id';  
    public $timestamps = false;


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

    protected function getAdvWalmartRunningSaveDataProceed($request)
    {
         try {
            DB::beginTransaction();

                $updateEbay = ADVMastersData::where('channel', 'WALMART')->first();
                $updateEbay->spent = $request->spendL30Total;
                $updateEbay->clicks = $request->clicksL30Total;
                $updateEbay->ad_sales = $request->salesL30Total;
                $updateEbay->ad_sold = $request->soldL30Total;
                $updateEbay->save();
    
            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    protected function getAdvEbay3AdRunningDataSaveProceed($request)
    {
        try {
            DB::beginTransaction();

                $updateAmazon = ADVMastersData::where('channel', 'EBAY 3')->first();
                $updateAmazon->spent = $request->spendL30Total;
                $updateAmazon->clicks = $request->clicksL30Total;
                $updateAmazon->ad_sales = $request->salesL30Total;
                $updateAmazon->ad_sold = $request->soldL30Total;
                $updateAmazon->save();

                $updateAmazonkw = ADVMastersData::where('channel', 'EB KW3')->first();
                $updateAmazonkw->spent = $request->kwSpendL30Total;
                $updateAmazonkw->clicks = $request->kwClicksL30Total;
                $updateAmazonkw->ad_sales = $request->kwSalesL30Total;
                $updateAmazonkw->ad_sold = $request->kwSoldL30Total;
                $updateAmazonkw->save();

                $updateAmazonpt = ADVMastersData::where('channel', 'EB PMT3')->first();
                $updateAmazonpt->spent = $request->pmtSpendL30Total;
                $updateAmazonpt->clicks = $request->pmtClicksL30Total;
                $updateAmazonpt->ad_sales = $request->pmtSalesL30Total;
                $updateAmazonpt->ad_sold = $request->pmtSoldL30Total;
                $updateAmazonpt->save();
     
            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    protected function getEbay2AdvRunningAdDataSaveProceed($request)
    {
        try {
            DB::beginTransaction();

                $updateAmazon = ADVMastersData::where('channel', 'EBAY 2')->first();
                $updateAmazon->spent = $request->spendL30Total;
                $updateAmazon->clicks = $request->clicksL30Total;
                $updateAmazon->ad_sales = $request->salesL30Total;
                $updateAmazon->ad_sold = $request->soldL30Total;
                $updateAmazon->save();

                $updateAmazonpmt = ADVMastersData::where('channel', 'EB PMT2')->first();
                $updateAmazonpmt->spent = $request->pmpSpendL30Total;
                $updateAmazonpmt->clicks = $request->pmtClicksL30Total;
                $updateAmazonpmt->ad_sales = $request->pmtSalesL30Total;
                $updateAmazonpmt->ad_sold = $request->pmpSoldL30Total;
                $updateAmazonpmt->save();
    
            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    protected function getEbay2TotsalSaleDataSaveProceed($request)
    {
        try {
            DB::beginTransaction();

                $updateAmazon = ADVMastersData::where('channel', 'EBAY 2')->first();
                $updateAmazon->l30_sales = $request->totalSales;
                $updateAmazon->save();

            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    protected function getEbay3TotalSaleSaveDataProceed($request)
    {
        try {
            DB::beginTransaction();

                $updateAmazon = ADVMastersData::where('channel', 'EBAY 3')->first();
                $updateAmazon->l30_sales = $request->salesTotal;
                $updateAmazon->save();

            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    protected function getAdvEbay2MissingSaveDataProceed($request)
    {
        try {
            DB::beginTransaction();

                $updateEbay2 = ADVMastersData::where('channel', 'EBAY 2')->first();
                $updateEbay2->missing_ads = $request->ptMissing;
                $updateEbay2->save();

                $updateEbay2 = ADVMastersData::where('channel', 'EB PMT2')->first();
                $updateEbay2->missing_ads = $request->ptMissing;
                $updateEbay2->save();

            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    protected function getEbay3MissingDataSaveProceed($request)
    {
        try {
            DB::beginTransaction();

                $updateEbay2 = ADVMastersData::where('channel', 'EBAY 3')->first();
                $updateEbay2->missing_ads = $request->totalMissingAds;
                $updateEbay2->save();

                $updateEbay2 = ADVMastersData::where('channel', 'EB KW3')->first();
                $updateEbay2->missing_ads = $request->kwMissing;
                $updateEbay2->save();

                $updateEbay2 = ADVMastersData::where('channel', 'EB PMT3')->first();
                $updateEbay2->missing_ads = $request->ptMissing;
                $updateEbay2->save();

            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    protected function getAdvShopifyGShoppingSaveDataProceed($request)
    {
        try {
            DB::beginTransaction();

                $updateAmazon = ADVMastersData::where('channel', 'G SHOPPING')->first();
                $updateAmazon->spent = $request->spendL30Total;
                $updateAmazon->clicks = $request->clicksl30Total;
                $updateAmazon->ad_sales = $request->adSalesl30Total;
                $updateAmazon->ad_sold = $request->adSoldl30Total;
                $updateAmazon->save();
                 
            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    protected function getChannelAdvMasterAmazonCronDataProceed($request)
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

            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

            $amazonKwL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', 'L30')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                })
                ->where('campaignName', 'NOT LIKE', '%PT')
                ->where('campaignName', 'NOT LIKE', '%PT.')
                ->get();

            $amazonPtL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', 'L30')
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

            $parentSkuCounts = $productMasters
                ->filter(fn($pm) => $pm->parent && !str_starts_with(strtoupper($pm->sku), 'PARENT'))
                ->groupBy('parent')
                ->map->count();

            $parentHlSpendData = [];
            foreach ($productMasters as $pm) {
                $sku = strtoupper($pm->sku);
                $parent = trim($pm->parent);

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
                        'total_L30' => $matchedCampaignHlL30->cost ?? 0,
                        'total_L7'  => $matchedCampaignHlL7->cost ?? 0,
                        'total_L30_sales' => $matchedCampaignHlL30->sales ?? 0,
                        'total_L7_sales'  => $matchedCampaignHlL7->sales ?? 0,
                        'total_L30_sold'  => $matchedCampaignHlL30->unitsSold ?? 0,
                        'total_L7_sold'   => $matchedCampaignHlL7->unitsSold ?? 0,
                        'total_L30_impr'  => $matchedCampaignHlL30->impressions ?? 0,
                        'total_L7_impr'   => $matchedCampaignHlL7->impressions ?? 0,
                        'total_L30_clicks'=> $matchedCampaignHlL30->clicks ?? 0,
                        'total_L7_clicks' => $matchedCampaignHlL7->clicks ?? 0,
                        'childCount'=> $childCount,
                    ];
                }
            }

            $total_spend_l30 = 0;  
            $total_kw_spend_l30 = 0;    
            $total_pt_spend_l30 = 0;
            $total_h1_spend_l30 = 0;
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
                $parent = trim($pm->parent);

                $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
                $shopify = $shopifyData[$pm->sku] ?? null;

                $matchedCampaignKwL30 = $amazonKwL30->first(function ($item) use ($sku) {
                    $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                    $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                    return $campaignName === $cleanSku;
                });

                $matchedCampaignPtL30 = $amazonPtL30->first(function ($item) use ($sku) {
                    $cleanName = strtoupper(trim($item->campaignName));
                    return (str_ends_with($cleanName, $sku . ' PT') || str_ends_with($cleanName, $sku . ' PT.'))
                        && strtoupper($item->campaignStatus) === 'ENABLED';
                });

                $row = [];
                $row['parent'] = $parent;
                $row['sku'] = $pm->sku;
                $row['INV'] = $shopify->inv ?? 0;
                $row['L30'] = $shopify->quantity ?? 0;
                $row['fba'] = $pm->fba ?? null;
                $row['A_L30'] = $amazonSheet->units_ordered_l30 ?? 0;

                $row['kw_spend_L30']  = $matchedCampaignKwL30->spend ?? 0;
                $row['kw_sold_L30']  = $matchedCampaignKwL30->unitsSoldSameSku30d ?? 0;
                $row['kw_sales_L30']  = $matchedCampaignKwL30->sales30d ?? 0;
                $row['kw_clicks_L30'] = $matchedCampaignKwL30->clicks ?? 0;
                $row['pt_clicks_L30'] = $matchedCampaignPtL30->clicks ?? 0;
                $row['pt_spend_L30']  = $matchedCampaignPtL30->spend ?? 0;
                $row['hl_clicks_L30'] = $matchedCampaignHlL30->clicks ?? 0;
                $row['pt_sales_L30']  = $matchedCampaignPtL30->sales30d ?? 0;
                $row['hl_sales_L30']  = $matchedCampaignHlL30->sales ?? 0;
                $row['hl_sold_L30']  = $matchedCampaignHlL30->unitsSold ?? 0;
                $row['pt_sold_L30']  = $matchedCampaignPtL30->unitsSoldSameSku30d ?? 0;

                if (str_starts_with($sku, 'PARENT')) {

                    $row['hl_spend_L30'] = $matchedCampaignHlL30->cost ?? 0;
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
            
                $childCount = $parentSkuCounts[$parent] ?? 0;
                $childCount = max($childCount, 1);

                $hl_share_clicks_L30 = ($matchedCampaignHlL30->impressions ?? 0) / $childCount;
                $row['SPEND_L30'] = $row['pt_spend_L30'] + $row['kw_spend_L30'] + $row['hl_spend_L30'];
                $row['CLICKS_L30'] = ($row['pt_clicks_L30'] + $row['kw_clicks_L30'] + $hl_share_clicks_L30);
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
                $total_h1_spend_l30 = $total_h1_spend_l30 + (is_numeric($row['hl_spend_L30']) ? (float)$row['hl_spend_L30'] : 0);
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
        
            $total_spend_l30 = round($total_spend_l30);
            $total_kw_spend_l30 = round($total_kw_spend_l30);
            $total_pt_spend_l30 = round($total_pt_spend_l30);
            $total_h1_spend_l30 = round($total_h1_spend_l30);
            $total_clicks_l30 = round($total_clicks_l30);
            $total_kw_clicks_l30 = round($total_kw_clicks_l30);
            $total_pt_clicks_l30 = round($total_pt_clicks_l30);
            $total_hl_clicks_l30 = round($total_hl_clicks_l30);
            $total_sales_l30 = round($total_sales_l30);
            $total_kw_sales_l30 = round($total_kw_sales_l30);
            $total_pt_sales_l30 = round($total_pt_sales_l30);
            $total_hl_sales_l30 = round($total_hl_sales_l30);
            $total_sold_l30 = round($total_sold_l30);
            $total_kw_sold_l30 = round($total_kw_sold_l30);
            $total_pt_sold_l30 = round($total_pt_sold_l30);
            $total_hl_sold_l30 = round($total_hl_sold_l30);        

            $todayDate = date('Y-m-d');

            $addUpdateAmazon = ADVMastersDailyData::where([['date', '=', $todayDate], ['channel', '=', 'AMAZON']])->first();
            if(isset($addUpdateAmazon) && !empty($addUpdateAmazon)){
            }else{
                $addUpdateAmazon = new ADVMastersDailyData();
            }
                $addUpdateAmazon->date = $todayDate;
                $addUpdateAmazon->channel = 'AMAZON';
                $addUpdateAmazon->spent = $total_spend_l30;
                $addUpdateAmazon->clicks = $total_clicks_l30;
                $addUpdateAmazon->ad_sales = $total_sales_l30;
                $addUpdateAmazon->ad_sold = $total_sold_l30;
                $addUpdateAmazon->save();


            $updateAmazon = ADVMastersData::where('channel', 'AMAZON')->first();
            $updateAmazon->spent = $total_spend_l30;
            $updateAmazon->clicks = $total_clicks_l30;
            $updateAmazon->ad_sales = $total_sales_l30;
            $updateAmazon->ad_sold = $total_sold_l30;
            $updateAmazon->save();

            $updateAmazonKw = ADVMastersData::where('channel', 'AMZ KW')->first();
            $updateAmazonKw->spent = $total_kw_spend_l30;
            $updateAmazonKw->clicks = $total_kw_clicks_l30;
            $updateAmazonKw->ad_sales = $total_kw_sales_l30;
            $updateAmazonKw->ad_sold = $total_kw_sold_l30;
            $updateAmazonKw->save();

            $updateAmazonPt = ADVMastersData::where('channel', 'AMZ PT')->first();
            $updateAmazonPt->spent = $total_pt_spend_l30;
            $updateAmazonPt->clicks = $total_pt_clicks_l30;
            $updateAmazonPt->ad_sales = $total_pt_sales_l30;
            $updateAmazonPt->ad_sold = $total_pt_sold_l30;
            $updateAmazonPt->save();

            $updateAmazonHl = ADVMastersData::where('channel', 'AMZ HL')->first();
            $updateAmazonHl->spent = $total_h1_spend_l30;
            $updateAmazonHl->clicks = $total_hl_clicks_l30;
            $updateAmazonHl->ad_sales = $total_hl_sales_l30;
            $updateAmazonHl->ad_sold = $total_hl_sold_l30;
            $updateAmazonHl->save();
            
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
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
            $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
                return strtoupper($item->sku);
            });

            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
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
                $parent = $pm->parent;

                $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
                $shopify = $shopifyData[$pm->sku] ?? null;

                $matchedKwCampaign = $amazonKwCampaigns->first(function ($item) use ($sku) {
                    $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                    $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                    return $campaignName === $cleanSku;
                });

                $matchedPtCampaign = $amazonPtCampaigns->first(function ($item) use ($sku) {
                    $cleanName = strtoupper(trim($item->campaignName));

                    return (
                        (str_ends_with($cleanName, $sku . ' PT') || str_ends_with($cleanName, $sku . ' PT.'))
                    );
                });

                $row = [
                    'parent' => $parent,
                    'sku' => $pm->sku,
                    'INV' => $shopify->inv ?? 0,
                    'L30' => $shopify->quantity ?? 0,
                    'A_L30' => $amazonSheet->units_ordered_l30 ?? 0,
                    'kw_campaign_name' => $matchedKwCampaign->campaignName ?? '',
                    'pt_campaign_name' => $matchedPtCampaign->campaignName ?? '',
                    'campaignStatus' => $matchedKwCampaign->campaignStatus ?? '',
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

            $addUpdateAmazon = ADVMastersDailyData::where([['date', '=', $todayDate], ['channel', '=', 'AMAZON']])->first();
            if(isset($addUpdateAmazon) && !empty($addUpdateAmazon)){
            }else{
                $addUpdateAmazon = new ADVMastersDailyData();
            }
            $addUpdateAmazon->date = $todayDate;
            $addUpdateAmazon->channel = 'AMAZON';
            $addUpdateAmazon->missing_ads = $totalMissingAds;
            $addUpdateAmazon->save();

            $updateAmazon = ADVMastersData::where('channel', 'AMAZON')->first();
            $updateAmazon->missing_ads = $totalMissingAds;
            $updateAmazon->save();

            $updateAmazonKw = ADVMastersData::where('channel', 'AMZ KW')->first();
            $updateAmazonKw->missing_ads = $kwMissing;
            $updateAmazonKw->save();

            $updateAmazonPt = ADVMastersData::where('channel', 'AMZ PT')->first();
            $updateAmazonPt->missing_ads = $ptMissing;
            $updateAmazonPt->save();

         DB::commit();
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
                    $row['A_L30'] = $amazonSheet->units_ordered_l30;
                    $row['price'] = $amazonSheet->price;
                }

                $price = isset($row['price']) ? floatval($row['price']) : 0;
                $units_ordered_l30 = isset($row['A_L30']) ? floatval($row['A_L30']) : 0;
                $row['T_Sale_l30'] = round($price * $units_ordered_l30, 2);  
                $total_sales = $total_sales + $row['T_Sale_l30'];         
            }
            $total_sales = round($total_sales);
    
            $todayDate = date('Y-m-d');
            $addUpdateAmazon = ADVMastersDailyData::where([['date', '=', $todayDate], ['channel', '=', 'AMAZON']])->first();
            if(isset($addUpdateAmazon) && !empty($addUpdateAmazon)){
            }else{
                $addUpdateAmazon = new ADVMastersDailyData();
            }
            $addUpdateAmazon->date = $todayDate;
            $addUpdateAmazon->channel = 'AMAZON';
            $addUpdateAmazon->l30_sales = $total_sales;
            $addUpdateAmazon->save();

            $updateAmazon = ADVMastersData::where('channel', 'AMAZON')->first();
            $updateAmazon->l30_sales = $total_sales;
            $updateAmazon->save();

            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    protected function getChannelAdvMasterEbayCronDataProceed($request)
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
            $ebayMetricData = DB::connection('apicentral')->table('ebay_one_metrics')
                ->select('sku', 'ebay_price', 'item_id')
                ->whereIn('sku', $skus)
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

            $itemIds = $ebayMetricData->pluck('item_id')->toArray();
            $ebayGeneralReportsL30 = EbayGeneralReport::where('report_range', 'L30')
                ->whereIn('listing_id', $itemIds)
                ->get();

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
                
                $matchedGeneralL30 = $ebayGeneralReportsL30->first(function ($item) use ($ebay) {
                    if (!$ebay || empty($ebay->item_id)) return false;
                    return trim((string)$item->listing_id) == trim((string)$ebay->item_id);
                });

                $row = [];
                $row['parent'] = $parent;
                $row['sku'] = $pm->sku;
                $row['INV'] = $shopify->inv ?? 0;
                $row['L30'] = $shopify->quantity ?? 0;
                $row['e_l30'] = $ebay->ebay_l30 ?? 0;
                $row['campaignName'] = $matchedCampaignL7->campaign_name ?? '';

                $row['kw_spend_L30'] = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? 0);
                $row['kw_clicks_L30'] = (int) ($matchedCampaignL30?->cpc_clicks ?? 0);
                $row['kw_sales_L30'] = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_sale_amount_payout_currency ?? 0);
                $row['kw_sold_L30'] = (int) ($matchedCampaignL30->cpc_attributed_sales ?? 0);
            
                $row['pmt_spend_L30'] = (float) str_replace('USD ', '', $matchedGeneralL30->ad_fees ?? 0);
                $row['pmt_clicks_L30'] = (int) ($matchedGeneralL30->clicks ?? 0);
                $row['pmt_sales_L30'] = (float) str_replace('USD ', '', $matchedGeneralL30->sale_amount ?? 0);
                $row['pmt_sold_L30'] = (int) ($matchedGeneralL30->sales ?? 0);

                
                $row['SPEND_L30'] = $row['kw_spend_L30'] + $row['pmt_spend_L30'];
                $row['CLICKS_L30'] = $row['kw_clicks_L30'] + $row['pmt_clicks_L30'];
                $row['SALES_L30'] = $row['kw_sales_L30'] + $row['pmt_sales_L30'];
                $row['SOLD_L30'] = $row['kw_sold_L30'] + $row['pmt_sold_L30'];

                $sku = strtolower(trim($row['sku'] ?? ''));
                if (strpos($sku, 'parent ') == false)
                {
                    if($row['campaignName'] !== '')
                    {
                        $total_spend_l30 = $total_spend_l30 +  $row['SPEND_L30'];
                        $total_click_l30 = $total_click_l30 + $row['CLICKS_L30']; 
                        $total_sales_l30 = $total_sales_l30 + $row['SALES_L30']; 
                        $total_sold_l30 = $total_sold_l30 + $row['SOLD_L30'];
                        $total_pmt_spend_l30 = $total_pmt_spend_l30 + $row['pmt_spend_L30']; 
                        $total_pmt_clicks_l30 = $total_pmt_clicks_l30 + $row['pmt_clicks_L30']; 
                        $total_pmt_sales_l30 = $total_pmt_sales_l30 + $row['pmt_sales_L30'];
                        $total_pmt_sold_l30 = $total_pmt_sold_l30 + $row['pmt_sold_L30'];
                        $total_kw_spend_l30 = $total_kw_spend_l30 + $row['kw_spend_L30']; 
                        $total_kw_clicks_l30 = $total_kw_clicks_l30 + $row['kw_clicks_L30'];
                        $total_kw_sales_l30 = $total_kw_sales_l30 + $row['kw_sales_L30']; 
                        $total_kw_sold_l30 = $total_kw_sold_l30 + $row['kw_sold_L30']; 
                    }
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
            
            $addUpdateAmazon = ADVMastersDailyData::where([['date', '=', $todayDate], ['channel', '=', 'EBAY']])->first();
            if(isset($addUpdateAmazon) && !empty($addUpdateAmazon)){
            }else{
                $addUpdateAmazon = new ADVMastersDailyData();
            }
            $addUpdateAmazon->date = $todayDate;
            $addUpdateAmazon->channel = 'EBAY';
            $addUpdateAmazon->spent = $total_spend_l30;
            $addUpdateAmazon->clicks = $total_click_l30;
            $addUpdateAmazon->ad_sales = $total_sales_l30;
            $addUpdateAmazon->ad_sold = $total_sold_l30;
            $addUpdateAmazon->save();
        

            $updateEbay = ADVMastersData::where('channel', 'EBAY')->first();
            $updateEbay->spent = $total_spend_l30;
            $updateEbay->clicks = $total_click_l30;
            $updateEbay->ad_sales = $total_sales_l30;
            $updateEbay->ad_sold = $total_sold_l30;
            $updateEbay->save();

            $updateEbayKw = ADVMastersData::where('channel', 'EB KW')->first();
            $updateEbayKw->spent = $total_kw_spend_l30;
            $updateEbayKw->clicks = $total_kw_clicks_l30;
            $updateEbayKw->ad_sales = $total_kw_sales_l30;
            $updateEbayKw->ad_sold = $total_kw_sold_l30;
            $updateEbayKw->save();

            $updateEbayPmt = ADVMastersData::where('channel', 'EB PMT')->first();
            $updateEbayPmt->spent = $total_pmt_spend_l30;
            $updateEbayPmt->clicks = $total_pmt_clicks_l30;
            $updateEbayPmt->ad_sales = $total_pmt_sales_l30;
            $updateEbayPmt->ad_sold = $total_pmt_sold_l30;
            $updateEbayPmt->save();

            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    protected function getChannelAdvMasterEbayCronMissingDataProceed($request)
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
            $ebayMetricData = DB::connection('apicentral')->table('ebay_one_metrics')
                ->select('sku', 'ebay_price', 'item_id')
                ->whereIn('sku', $skus)
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

            $addUpdateAmazon = ADVMastersDailyData::where([['date', '=', $todayDate], ['channel', '=', 'EBAY']])->first();
            if(isset($addUpdateAmazon) && !empty($addUpdateAmazon)){
            }else{
                $addUpdateAmazon = new ADVMastersDailyData();
            }
            $addUpdateAmazon->date = $todayDate;
            $addUpdateAmazon->channel = 'EBAY';
            $addUpdateAmazon->missing_ads = $totalMissingAds;
            $addUpdateAmazon->save();
        

            $updateEbay = ADVMastersData::where('channel', 'EBAY')->first();
            $updateEbay->missing_ads = $totalMissingAds;
            $updateEbay->save();

            $updateEbayKw = ADVMastersData::where('channel', 'EB KW')->first();
            $updateEbayKw->missing_ads = $kwMissing;
            $updateEbayKw->save();

            $updateEbayPmt = ADVMastersData::where('channel', 'EB PMT')->first();
            $updateEbayPmt->missing_ads = $ptMissing;
            $updateEbayPmt->save();
        
            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }

    protected function getChannelAdvMasterEbayCronTotalSaleDataProceed($request)
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
            $ebayMetrics = DB::connection('apicentral')->table('ebay_one_metrics')->whereIn('sku', $skus)->get()->keyBy('sku');

            $total_ebay_sales = 0;
            foreach ($productMasters as $pm) {
                $sku = strtoupper($pm->sku);
                $parent = $pm->parent;

                $shopify = $shopifyData[$pm->sku] ?? null;
                $ebayMetric = $ebayMetrics[$pm->sku] ?? null;
                $row = [];
                $row["Parent"] = $parent;
                $row["(Child) sku"] = $pm->sku;
                $row['fba'] = $pm->fba;
                $row["INV"] = $shopify->inv ?? 0;
                $row["L30"] = $shopify->quantity ?? 0;
                $row["eBay L30"] = $ebayMetric->ebay_l30 ?? 0;
                $row["eBay Price"] = $ebayMetric->ebay_price ?? 0;
    
                $total_ebay_sales = $total_ebay_sales + ($row["eBay L30"] * floatval($row["eBay Price"]));
            
            }
            $total_ebay_sales = round($total_ebay_sales);
            $todayDate = date('Y-m-d');
            $addUpdateAmazon = ADVMastersDailyData::where([['date', '=', $todayDate], ['channel', '=', 'EBAY']])->first();
            if(isset($addUpdateAmazon) && !empty($addUpdateAmazon)){
            }else{
                $addUpdateAmazon = new ADVMastersDailyData();
            }
            $addUpdateAmazon->date = $todayDate;
            $addUpdateAmazon->channel = 'EBAY';
            $addUpdateAmazon->l30_sales = $total_ebay_sales;
            $addUpdateAmazon->save();
        

            $updateEbay = ADVMastersData::where('channel', 'EBAY')->first();
            $updateEbay->l30_sales = $total_ebay_sales;
            $updateEbay->save();
            
            DB::commit();
            return 1; 
        } catch (\Exception $e) {
            DB::rollBack(); 
            return 0;
        }
    }


}
