<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TemuMetric;
use App\Models\TemuDataView;
use App\Models\TemuListingStatus;
use App\Models\TemuDailyData;
use App\Models\TemuAdData;
use App\Models\TemuCampaignReport;
use App\Models\TemuPricing;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TemuAdsController extends Controller
{
    public function index()
    {
        $marketplaceData = MarketplacePercentage::where("marketplace", "Temu")->first();
        $temuPercentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $temuAdPercentage = $marketplaceData ? $marketplaceData->ad_updates : 100;

        // Get chart data for last 30 days
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30);

        // Create array for all 30 days with data or zeros
        $dates = [];
        $clicks = [];
        $spend = [];
        $adSales = [];
        $adSold = [];
        $acos = [];
        $cvr = [];

        for ($i = 30; $i >= 0; $i--) {
            $date = \Carbon\Carbon::now()->subDays($i)->format('Y-m-d');
            $dates[] = $date;
            
            // Placeholder values - can be populated with actual Temu metrics
            $clicks[] = 0;
            $spend[] = 0;
            $adSales[] = 0;
            $adSold[] = 0;
            $acos[] = 0;
            $cvr[] = 0;
        }

        return view('campaign.temu.temu-utilized', compact('temuPercentage', 'temuAdPercentage', 'dates', 'clicks', 'spend', 'adSales', 'adSold', 'acos', 'cvr'));
    }

    public function getTemuAdsData()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')
            ->orderBy("parent", "asc")
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy("sku", "asc")
            ->get();

        $skus = $productMasters->pluck("sku")->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn("sku", $skus)->get()->keyBy("sku");
        
        // Fetch Temu L30 from TemuDailyData (sum of quantity_purchased grouped by contribution_sku)
        $temuSalesData = TemuDailyData::whereIn('contribution_sku', $skus)
            ->selectRaw('contribution_sku as sku, SUM(quantity_purchased) as temu_l30')
            ->groupBy('contribution_sku')
            ->get()
            ->keyBy('sku');

        // Fetch NRA from TemuDataView (where it's actually stored)
        $temuDataViews = TemuDataView::whereIn("sku", $skus)->get()->keyBy("sku");

        // Fetch goods_id mapping from TemuPricing (same query as temu-decrease for consistency)
        $temuPricing = TemuPricing::whereIn("sku", $skus)
            ->get()
            ->keyBy('sku');

        // Get all goods_ids
        $goodsIds = $temuPricing->pluck('goods_id')->filter()->unique()->values()->all();

        // Fetch L30 ad data from temu_campaign_reports
        $adDataL30 = TemuCampaignReport::whereIn('goods_id', $goodsIds)
            ->where('report_range', 'L30')
            ->selectRaw('goods_id, 
                SUM(spend) as spend_l30,
                SUM(clicks) as clicks_l30,
                SUM(base_price_sales) as base_price_sales_l30,
                SUM(COALESCE(sub_orders,0)) as ad_sold_l30,
                AVG(acos_ad) as acos_l30,
                AVG(roas) as roas_l30,
                AVG(in_roas) as in_roas_l30,
                MAX(status) as status_l30')
            ->groupBy('goods_id')
            ->get()
            ->keyBy('goods_id');

        // Fetch L7 ad data from temu_campaign_reports
        $adDataL7 = TemuCampaignReport::whereIn('goods_id', $goodsIds)
            ->where('report_range', 'L7')
            ->selectRaw('goods_id, 
                SUM(spend) as spend_l7,
                SUM(clicks) as clicks_l7,
                SUM(base_price_sales) as base_price_sales_l7,
                AVG(acos_ad) as acos_l7,
                AVG(roas) as roas_l7,
                AVG(in_roas) as in_roas_l7,
                MAX(status) as status_l7')
            ->groupBy('goods_id')
            ->get()
            ->keyBy('goods_id');

        // Calculate total SKU count (excluding PARENT SKUs and deleted_at)
        $totalSkuCount = ProductMaster::whereNull('deleted_at')
            ->whereRaw("UPPER(sku) NOT LIKE 'PARENT %'")
            ->count();

        // Calculate zero INV count (excluding PARENT SKUs)
        $zeroInvCount = 0;
        $processedZeroInvSkus = [];
        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));
            $isParentSku = strpos($sku, 'PARENT') !== false;
            
            if (!$isParentSku && !in_array($sku, $processedZeroInvSkus)) {
                $processedZeroInvSkus[] = $sku;
                $shopify = $shopifyData->get($pm->sku);
                $inv = ($shopify && isset($shopify->inv)) ? (int)$shopify->inv : 0;
                if ($inv <= 0) {
                    $zeroInvCount++;
                }
            }
        }

        $data = [];

        foreach ($productMasters as $product) {
            $sku = $product->sku;
            $shopify = $shopifyData->get($sku);
            $temuSales = $temuSalesData->get($sku);
            $temuPricingItem = $temuPricing->get($sku);
            
            // Get INV from Shopify
            $inv = ($shopify && isset($shopify->inv)) ? (int)$shopify->inv : 0;
            
            // Get OV L30 from Shopify quantity column (as per getTemuDecreaseData)
            $ovL30 = ($shopify && isset($shopify->quantity)) ? (int)$shopify->quantity : 0;
            
            // Get Temu L30 from TemuDailyData
            $temuL30 = ($temuSales && isset($temuSales->temu_l30)) ? (int)$temuSales->temu_l30 : 0;
            
            // Get NRA from TemuDataView (where it's actually stored)
            $nraValue = 'RA'; // Default to RA
            if (isset($temuDataViews[$sku])) {
                $viewData = $temuDataViews[$sku];
                $valuesArr = is_array($viewData->value) 
                    ? $viewData->value 
                    : (json_decode($viewData->value, true) ?: []);
                if (isset($valuesArr['NR'])) {
                    $nraValue = $valuesArr['NR'] ?? 'RA';
                }
            }
            
            // Calculate DIL % (OV L30 / INV * 100)
            $dilPercent = 0;
            if ($inv > 0) {
                $dilPercent = round(($ovL30 / $inv) * 100, 2);
            }

            // Get ad data from TemuCampaignReport using goods_id
            $goodsId = $temuPricingItem ? $temuPricingItem->goods_id : null;
            $adL30 = $goodsId ? $adDataL30->get($goodsId) : null;
            $adL7 = $goodsId ? $adDataL7->get($goodsId) : null;
            
            // Check if campaign exists (has data in temu_campaign_reports)
            // Campaign exists if there's any record in temu_campaign_reports for this goods_id
            $hasCampaign = ($adL30 !== null) || ($adL7 !== null);

            // Determine status - If campaign exists, default to "Active", otherwise use status from database or "Not Created" (like TikTok)
            $status = null;
            // Get status from database (prioritize L30, fallback to L7)
            if ($adL30 && isset($adL30->status_l30) && !empty($adL30->status_l30) && $adL30->status_l30 !== 'NULL') {
                $status = $adL30->status_l30;
            } elseif ($adL7 && isset($adL7->status_l7) && !empty($adL7->status_l7) && $adL7->status_l7 !== 'NULL') {
                $status = $adL7->status_l7;
            }
            
            // If campaign exists and status is null/empty, default to "Active" (like TikTok)
            if ($hasCampaign && (empty($status) || $status === null)) {
                $status = 'Active';
            } elseif (empty($status) || $status === null) {
                $status = 'Not Created';
            }

            $row = [
                'sku' => $sku,
                'parent' => $product->parent,
                'INV' => $inv,
                'L30' => $ovL30,
                'temu_l30' => $temuL30,
                'DIL %' => $dilPercent,
                'NR' => $nraValue,
                'hasCampaign' => $hasCampaign,
                // L30 ad metrics
                'spend_l30' => $adL30 ? round((float)$adL30->spend_l30, 2) : 0,
                'clicks_l30' => $adL30 ? (int)$adL30->clicks_l30 : 0,
                'base_price_sales_l30' => $adL30 ? round((float)$adL30->base_price_sales_l30, 2) : 0,
                'ad_sold_l30' => $adL30 ? (int)$adL30->ad_sold_l30 : 0,
                'acos_l30' => $adL30 && $adL30->roas_l30 > 0 ? round((100 / (float)$adL30->roas_l30), 2) : 0,
                'in_roas_l30' => $adL30 ? round((float)$adL30->in_roas_l30, 2) : 0,
                'out_roas_l30' => $adL30 ? round((float)$adL30->roas_l30, 2) : 0,
                // L7 ad metrics
                'spend_l7' => $adL7 ? round((float)$adL7->spend_l7, 2) : 0,
                'clicks_l7' => $adL7 ? (int)$adL7->clicks_l7 : 0,
                'base_price_sales_l7' => $adL7 ? round((float)$adL7->base_price_sales_l7, 2) : 0,
                'acos_l7' => $adL7 && $adL7->roas_l7 > 0 ? round((100 / (float)$adL7->roas_l7), 2) : 0,
                'in_roas_l7' => $adL7 ? round((float)$adL7->in_roas_l7, 2) : 0,
                'out_roas_l7' => $adL7 ? round((float)$adL7->roas_l7, 2) : 0,
                // Status
                'status' => $status,
            ];

            $data[] = $row;
        }

        // Calculate total campaign count (unique goods_id that have campaigns)
        $totalCampaignCount = TemuCampaignReport::distinct('goods_id')
            ->pluck('goods_id')
            ->filter()
            ->unique()
            ->count();

        // Compute authoritative L30 totals from temu-decrease endpoint (uses normalized SKU matching)
        $temuCtrl = app(\App\Http\Controllers\MarketPlace\TemuController::class);
        $temuResponse = $temuCtrl->getTemuDecreaseData();
        $temuRows = json_decode($temuResponse->getContent(), true)['data'] ?? [];
        $l30Spend = 0; $l30Sales = 0; $l30Sold = 0; $l30Clicks = 0;
        foreach ($temuRows as $tr) {
            if (empty($tr['sku'])) continue;
            $l30Spend += round((float) ($tr['spend_l30'] ?? 0), 2);
            $l30Sales += round((float) ($tr['ad_sales_l30'] ?? 0), 2);
            $l30Sold += (int) ($tr['ad_sold_l30'] ?? 0);
            $l30Clicks += (int) ($tr['clicks_l30'] ?? 0);
        }

        return response()->json([
            'data' => $data,
            'total_sku_count' => $totalSkuCount,
            'zero_inv_count' => $zeroInvCount,
            'total_campaign_count' => $totalCampaignCount,
            'l30_totals' => [
                'spend' => round($l30Spend, 2),
                'ad_sales' => round($l30Sales, 2),
                'ad_sold' => $l30Sold,
                'clicks' => $l30Clicks,
            ],
        ]);
    }

    public function updateTemuAds(Request $request)
    {
        $sku = $request->input('sku');
        $field = $request->input('field');
        $value = $request->input('value');

        if (!$sku || !$field || $value === null) {
            return response()->json([
                'success' => false,
                'message' => 'SKU, field, and value are required'
            ], 400);
        }

        // Save NRA to TemuDataView (same as saveNrToDatabase in TemuController)
        if ($field === 'NR') {
            $dataView = TemuDataView::firstOrNew(['sku' => $sku]);
            $existingValue = is_array($dataView->value) 
                ? $dataView->value 
                : (json_decode($dataView->value, true) ?: []);
            
            $existingValue['NR'] = $value;
            $dataView->value = $existingValue;
            $dataView->save();

            return response()->json([
                'success' => true,
                'message' => 'NRA updated successfully',
                'data' => $dataView
            ]);
        }

        // Save IN ROAS L30 or L7 to TemuCampaignReport
        if ($field === 'in_roas_l30' || $field === 'in_roas_l7') {
            // Get goods_id from TemuPricing
            $temuPricing = TemuPricing::where('sku', $sku)
                ->whereNotNull('goods_id')
                ->first();

            if (!$temuPricing || !$temuPricing->goods_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'SKU does not have a goods_id mapping'
                ], 400);
            }

            $goodsId = $temuPricing->goods_id;
            $inRoasValue = (float) $value;
            $reportRange = ($field === 'in_roas_l30') ? 'L30' : 'L7';

            // Check if any records exist for this goods_id and report_range
            $existingRecords = TemuCampaignReport::where('goods_id', $goodsId)
                ->where('report_range', $reportRange)
                ->get();

            if ($existingRecords->count() > 0) {
                // Update all existing records
                $updated = TemuCampaignReport::where('goods_id', $goodsId)
                    ->where('report_range', $reportRange)
                    ->update(['in_roas' => $inRoasValue]);

                return response()->json([
                    'success' => true,
                    'message' => 'IN ROAS ' . $reportRange . ' updated successfully',
                    'updated_count' => $updated
                ]);
            } else {
                // No records exist, create a new one
                // Try to get goods_name from the other report_range record or use a default
                $otherRecord = TemuCampaignReport::where('goods_id', $goodsId)
                    ->where('report_range', $reportRange === 'L30' ? 'L7' : 'L30')
                    ->first();
                
                $goodsName = $otherRecord ? $otherRecord->goods_name : null;

                // Create a new record with minimal required fields
                TemuCampaignReport::create([
                    'goods_id' => $goodsId,
                    'goods_name' => $goodsName,
                    'report_range' => $reportRange,
                    'in_roas' => $inRoasValue,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'IN ROAS ' . $reportRange . ' created and saved successfully',
                    'created' => true
                ]);
            }
        }

        // Save Status to TemuCampaignReport
        if ($field === 'status') {
            // Get goods_id from TemuPricing
            $temuPricing = TemuPricing::where('sku', $sku)
                ->whereNotNull('goods_id')
                ->first();

            if (!$temuPricing || !$temuPricing->goods_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'SKU does not have a goods_id mapping'
                ], 400);
            }

            // Validate status value
            $validStatuses = ['Active', 'Inactive', 'Not Created'];
            if (!in_array($value, $validStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid status value. Must be one of: ' . implode(', ', $validStatuses)
                ], 400);
            }

            $goodsId = $temuPricing->goods_id;

            // Update status for all records with this goods_id (both L30 and L7)
            $updated = TemuCampaignReport::where('goods_id', $goodsId)
                ->update(['status' => $value]);

            // If no records exist, create records for both L30 and L7
            if ($updated === 0) {
                // Try to get goods_name from an existing TemuCampaignReport record (any report_range)
                $existingRecord = TemuCampaignReport::where('goods_id', $goodsId)->first();
                $goodsName = $existingRecord ? $existingRecord->goods_name : null;

                // Create records for both report ranges
                TemuCampaignReport::create([
                    'goods_id' => $goodsId,
                    'goods_name' => $goodsName,
                    'report_range' => 'L30',
                    'status' => $value,
                ]);

                TemuCampaignReport::create([
                    'goods_id' => $goodsId,
                    'goods_name' => $goodsName,
                    'report_range' => 'L7',
                    'status' => $value,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Status created and saved successfully',
                    'created' => true
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'updated_count' => $updated
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid field'
        ], 400);
    }

    public function uploadCampaignReport(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv',
                'report_range' => 'required|in:L7,L30,L60'
            ]);

            $file = $request->file('file');
            $reportRange = $request->input('report_range');
            
            $spreadsheet = IOFactory::load($file->getPathName());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Get headers from first row
            $headers = $rows[0];
            unset($rows[0]); // Remove header row
            
            $totalRowsBeforeFilter = count($rows);
            $totalRowsRemoved = 0;
            
            // Skip rows that contain "Total" in first column (summary rows)
            // Only skip if first column contains "Total" and it's likely a summary row
            foreach ($rows as $key => $row) {
                $firstCell = trim($row[0] ?? '');
                // Check if first cell contains "Total" (case insensitive) - this is likely a summary row
                if (!empty($firstCell) && stripos($firstCell, 'Total') !== false) {
                    unset($rows[$key]);
                    $totalRowsRemoved++;
                }
            }
            
            $totalRowsAfterFilter = count($rows);

            $imported = 0;
            $skipped = 0;

            DB::beginTransaction();
            try {
                // Delete existing data for this report_range before inserting new data
                TemuCampaignReport::where('report_range', $reportRange)->delete();
                
                foreach ($rows as $index => $row) {
                    // Skip empty rows
                    if (empty(array_filter($row))) {
                        $skipped++;
                        continue;
                    }

                    // Ensure row has same number of elements as headers
                    if (count($row) !== count($headers)) {
                        // Pad row or trim to match header count
                        $row = array_slice(array_pad($row, count($headers), null), 0, count($headers));
                    }

                    $rowData = array_combine($headers, $row);
                    
                    // Skip if goods_id is empty (required field)
                    if (empty($rowData['Goods ID'] ?? null)) {
                        $skipped++;
                        continue;
                    }
                    
                    // Helper function to parse currency values
                    $parseCurrency = function($value) {
                        if (empty($value) || $value === '∞') return null;
                        return floatval(str_replace(['$', ','], '', $value));
                    };
                    
                    // Helper function to parse percentage values
                    $parsePercent = function($value) {
                        if (empty($value) || $value === '∞') return null;
                        return floatval(str_replace('%', '', $value));
                    };

                    try {
                        $campaignData = [
                            'goods_name' => $rowData['Goods name'] ?? null,
                            'goods_id' => trim($rowData['Goods ID'] ?? ''),
                            'report_range' => $reportRange,
                            'spend' => $parseCurrency($rowData['Spend'] ?? null),
                            'base_price_sales' => $parseCurrency($rowData['Base price sales'] ?? null),
                            'roas' => floatval($rowData['ROAS'] ?? 0),
                            'acos_ad' => $parsePercent($rowData['ACOS(AD)'] ?? null),
                            'cost_per_transaction' => $parseCurrency($rowData['Cost per transaction'] ?? null),
                            'sub_orders' => !empty($rowData['Sub-Orders']) ? (int)$rowData['Sub-Orders'] : 0,
                            'items' => !empty($rowData['Items']) ? (int)$rowData['Items'] : 0,
                            'net_total_cost' => $parseCurrency($rowData['Net total cost'] ?? null),
                            'net_declared_sales' => $parseCurrency($rowData['Net declared sales'] ?? null),
                            'net_roas' => floatval($rowData['Net advertising return on investment (ROAS)'] ?? 0),
                            'net_acos_ad' => $parsePercent($rowData['Net advertising cost ratio (advertising)'] ?? null),
                            'net_cost_per_transaction' => $parseCurrency($rowData['Net cost per transaction'] ?? null),
                            'net_orders' => !empty($rowData['Net Orders']) ? (int)$rowData['Net Orders'] : 0,
                            'net_number_pieces' => !empty($rowData['Net number of pieces']) ? (int)$rowData['Net number of pieces'] : 0,
                            'impressions' => !empty($rowData['Impressions']) ? (int)str_replace(',', '', $rowData['Impressions']) : 0,
                            'clicks' => !empty($rowData['Clicks']) ? (int)str_replace(',', '', $rowData['Clicks']) : 0,
                            'ctr' => $parsePercent($rowData['CTR'] ?? null),
                            'cvr' => $parsePercent($rowData['Conversion Rate (CVR)'] ?? null),
                            'add_to_cart_number' => !empty($rowData['Add-to-cart number']) ? (int)str_replace(',', '', $rowData['Add-to-cart number']) : 0,
                            'weekly_roas' => floatval($rowData['Weekly ROAS'] ?? 0),
                            'target' => floatval($rowData['Target'] ?? 0),
                        ];

                        TemuCampaignReport::create($campaignData);
                        $imported++;
                    } catch (\Exception $e) {
                        $skipped++;
                        Log::warning("Failed to import row: " . ($rowData['Goods ID'] ?? 'unknown') . " - " . $e->getMessage());
                        continue;
                    }
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Successfully imported $imported records for $reportRange"
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error uploading Temu campaign report: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error uploading file: ' . $e->getMessage()
            ], 500);
        }
    }
}
