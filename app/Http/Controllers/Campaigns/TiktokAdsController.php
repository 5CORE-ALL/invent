<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use App\Models\TiktokCampaignReport;
use App\Models\ProductMaster;
use App\Models\ReverbViewData;
use App\Models\ShopifySku;
use App\Models\TikTokDailyData;
use App\Models\TikTokProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TiktokAdsController extends Controller
{
    public function index()
    {
        $marketplaceData = MarketplacePercentage::where("marketplace", "TiktokShop")->first();
        $tiktokPercentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $tiktokAdPercentage = $marketplaceData ? $marketplaceData->ad_updates : 100;

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

        return view('campaign.tiktok.tiktok-ads', compact('tiktokPercentage', 'tiktokAdPercentage', 'dates', 'clicks', 'spend', 'adSales', 'adSold', 'acos', 'cvr'));
    }

    public function utilized(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        
        return view('campaign.tiktok.tiktok-utilized', [
            'mode' => $mode,
            'demo' => $demo
        ]);
    }

    public function getUtilizedData(Request $request)
    {
        try {
            $productMasters = ProductMaster::whereNull('deleted_at')
                ->orderBy("parent", "asc")
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy("sku", "asc")
                ->get();

            $skus = $productMasters->pluck("sku")->filter()->unique()->values()->all();

            $shopifyData = ShopifySku::whereIn("sku", $skus)->get()->keyBy("sku");
            
            // Fetch TikTok L30 sales from ShipHub (like daily sales page)
            $latestDate = DB::connection('shiphub')
                ->table('orders')
                ->where('marketplace', '=', 'tiktok')
                ->max('order_date');

            $tiktokSalesData = collect();
            try {
                if ($latestDate) {
                    $latestDateCarbon = \Carbon\Carbon::parse($latestDate, 'America/Los_Angeles');
                    $startDate = $latestDateCarbon->copy()->subDays(29); // 30 days total
                    
                    $tiktokSalesData = DB::connection('shiphub')
                        ->table('orders as o')
                        ->join('order_items as i', 'o.id', '=', 'i.order_id')
                        ->whereBetween('o.order_date', [$startDate, $latestDateCarbon->endOfDay()])
                        ->where('o.marketplace', '=', 'tiktok')
                        ->where(function($query) {
                            $query->where('o.order_status', '!=', 'Canceled')
                                  ->where('o.order_status', '!=', 'Cancelled')
                                  ->where('o.order_status', '!=', 'canceled')
                                  ->where('o.order_status', '!=', 'cancelled')
                                  ->orWhereNull('o.order_status');
                        })
                        ->whereIn('i.sku', $skus)
                        ->selectRaw('UPPER(i.sku) as sku, SUM(i.quantity_ordered) as l30')
                        ->groupBy(DB::raw('UPPER(i.sku)'))
                        ->get()
                        ->keyBy(function($item) {
                            return strtoupper($item->sku);
                        });
                }
            } catch (\Exception $e) {
                // If ShipHub connection fails, continue with empty sales data
                \Log::warning('TikTok ShipHub connection error: ' . $e->getMessage());
            }

            // Get campaigns by matching campaign_name with SKU (simplified - only SKU matching)
            $skusUpper = array_map('strtoupper', $skus);
            
            // Get ALL campaigns from L30 and L7 reports - only Product card type and where product_id exists
            $allCampaignsL30 = TiktokCampaignReport::where('report_range', 'L30')
                ->where('creative_type', 'Product card')
                ->whereNotNull('campaign_name')
                ->where('campaign_name', '!=', '')
                ->whereNotNull('product_id')
                ->where('product_id', '!=', '')
                ->select('product_id', 'campaign_name', 'campaign_id', 'creative_type')
                ->get();
            
            $allCampaignsL7 = TiktokCampaignReport::where('report_range', 'L7')
                ->where('creative_type', 'Product card')
                ->whereNotNull('campaign_name')
                ->where('campaign_name', '!=', '')
                ->whereNotNull('product_id')
                ->where('product_id', '!=', '')
                ->select('product_id', 'campaign_name', 'campaign_id', 'creative_type')
                ->get();
            
            // Combine both L30 and L7 campaigns
            $allCampaigns = $allCampaignsL30->concat($allCampaignsL7);
            
            // Create a map of SKU (uppercase) to campaign names and metrics for faster lookup
            $campaignMapBySku = [];
            $campaignMetricsBySku = [];
            
            // Get campaign metrics aggregated by SKU (campaign_name) - only where product_id exists
            $campaignMetricsL30 = TiktokCampaignReport::where('report_range', 'L30')
                ->where('creative_type', 'Product card')
                ->whereNotNull('campaign_name')
                ->where('campaign_name', '!=', '')
                ->whereNotNull('product_id')
                ->where('product_id', '!=', '')
                ->get()
                ->groupBy(function($item) {
                    return strtoupper(trim($item->campaign_name));
                })
                ->map(function($group) {
                    // For ROI, take the first non-null value (don't average - use actual value from database)
                    $firstRecord = $group->first();
                    $roi = $firstRecord && $firstRecord->roi !== null ? (float)$firstRecord->roi : 0;
                    
                    // For In ROAS, take the first non-null value (don't average - use actual value from database)
                    $inRoas = $firstRecord && $firstRecord->in_roas !== null ? (float)$firstRecord->in_roas : 0;
                    
                    // For Custom Status, take the first non-null value (prioritize L30) - return null if not set
                    $customStatus = $firstRecord && $firstRecord->custom_status ? $firstRecord->custom_status : null;
                    $budget = $firstRecord && $firstRecord->budget !== null ? (float)$firstRecord->budget : null;
                    
                    return (object)[
                        'sku_upper' => strtoupper(trim($group->first()->campaign_name)),
                        'total_cost' => $group->sum('cost'),
                        'total_clicks' => $group->sum('product_ad_clicks'),
                        'total_revenue' => $group->sum('gross_revenue'),
                        'total_sku_orders' => $group->sum('sku_orders'),
                        'avg_roi' => $roi,
                        'avg_in_roas' => $inRoas,
                        'custom_status' => $customStatus,
                        'budget' => $budget,
                    ];
                });
            
            $campaignMetricsL7 = TiktokCampaignReport::where('report_range', 'L7')
                ->where('creative_type', 'Product card')
                ->whereNotNull('campaign_name')
                ->where('campaign_name', '!=', '')
                ->whereNotNull('product_id')
                ->where('product_id', '!=', '')
                ->get()
                ->groupBy(function($item) {
                    return strtoupper(trim($item->campaign_name));
                })
                ->map(function($group) {
                    // For ROI, take the first non-null value (don't average - use actual value from database)
                    $firstRecord = $group->first();
                    $roi = $firstRecord && $firstRecord->roi !== null ? (float)$firstRecord->roi : 0;
                    
                    // For In ROAS, take the first non-null value (don't average - use actual value from database)
                    $inRoas = $firstRecord && $firstRecord->in_roas !== null ? (float)$firstRecord->in_roas : 0;
                    
                    // For Custom Status, take the first non-null value (use L7 if L30 doesn't exist) - return null if not set
                    $customStatus = $firstRecord && $firstRecord->custom_status ? $firstRecord->custom_status : null;
                    $budget = $firstRecord && $firstRecord->budget !== null ? (float)$firstRecord->budget : null;
                    
                    return (object)[
                        'sku_upper' => strtoupper(trim($group->first()->campaign_name)),
                        'total_cost' => $group->sum('cost'),
                        'total_clicks' => $group->sum('product_ad_clicks'),
                        'total_revenue' => $group->sum('gross_revenue'),
                        'total_sku_orders' => $group->sum('sku_orders'),
                        'avg_roi' => $roi,
                        'avg_in_roas' => $inRoas,
                        'custom_status' => $customStatus,
                        'budget' => $budget,
                    ];
                });
            
            // Combine L30 and L7 metrics
            // Prioritize L30 ROI, In ROAS, and Custom Status, use L7 only if L30 doesn't exist
            foreach ($campaignMetricsL30 as $skuUpper => $metrics) {
                $campaignMetricsBySku[$skuUpper] = [
                    'cost' => (float)($metrics->total_cost ?? 0),
                    'clicks' => (int)($metrics->total_clicks ?? 0),
                    'revenue' => (float)($metrics->total_revenue ?? 0),
                    'sku_orders' => (int)($metrics->total_sku_orders ?? 0),
                    'roi' => (float)($metrics->avg_roi ?? 0),
                    'in_roas' => (float)($metrics->avg_in_roas ?? 0),
                    'custom_status' => $metrics->custom_status ?? null,
                    'budget' => $metrics->budget !== null ? (float)$metrics->budget : null,
                ];
            }
            
            foreach ($campaignMetricsL7 as $skuUpper => $metrics) {
                if (isset($campaignMetricsBySku[$skuUpper])) {
                    $campaignMetricsBySku[$skuUpper]['cost'] += (float)($metrics->total_cost ?? 0);
                    $campaignMetricsBySku[$skuUpper]['clicks'] += (int)($metrics->total_clicks ?? 0);
                    $campaignMetricsBySku[$skuUpper]['revenue'] += (float)($metrics->total_revenue ?? 0);
                    $campaignMetricsBySku[$skuUpper]['sku_orders'] += (int)($metrics->total_sku_orders ?? 0);
                    if ($campaignMetricsBySku[$skuUpper]['roi'] == 0) {
                        $campaignMetricsBySku[$skuUpper]['roi'] = (float)($metrics->avg_roi ?? 0);
                    }
                    if ($campaignMetricsBySku[$skuUpper]['in_roas'] == 0) {
                        $campaignMetricsBySku[$skuUpper]['in_roas'] = (float)($metrics->avg_in_roas ?? 0);
                    }
                    if (empty($campaignMetricsBySku[$skuUpper]['custom_status'])) {
                        $campaignMetricsBySku[$skuUpper]['custom_status'] = $metrics->custom_status ?? null;
                    }
                    if ($campaignMetricsBySku[$skuUpper]['budget'] === null && $metrics->budget !== null) {
                        $campaignMetricsBySku[$skuUpper]['budget'] = (float)$metrics->budget;
                    }
                } else {
                    $campaignMetricsBySku[$skuUpper] = [
                        'cost' => (float)($metrics->total_cost ?? 0),
                        'clicks' => (int)($metrics->total_clicks ?? 0),
                        'revenue' => (float)($metrics->total_revenue ?? 0),
                        'sku_orders' => (int)($metrics->total_sku_orders ?? 0),
                        'roi' => (float)($metrics->avg_roi ?? 0),
                        'in_roas' => (float)($metrics->avg_in_roas ?? 0),
                        'custom_status' => $metrics->custom_status ?? null,
                        'budget' => $metrics->budget !== null ? (float)$metrics->budget : null,
                    ];
                }
            }
            
            foreach ($allCampaigns as $campaign) {
                if (!empty($campaign->campaign_name)) {
                    $campaignNameUpper = strtoupper(trim($campaign->campaign_name));
                    if (!isset($campaignMapBySku[$campaignNameUpper])) {
                        $campaignMapBySku[$campaignNameUpper] = [];
                    }
                    if (!in_array($campaign->campaign_name, $campaignMapBySku[$campaignNameUpper])) {
                        $campaignMapBySku[$campaignNameUpper][] = $campaign->campaign_name;
                    }
                }
            }

            // Fetch NRA (NR) values from reverb_view_data (same store as TikTok pricing page)
            $reverbViewData = ReverbViewData::whereIn('sku', $skus)->get()->keyBy('sku');

            $data = [];

            foreach ($productMasters as $product) {
                $sku = $product->sku;
                $shopify = $shopifyData->get($sku);
                
                // Get INV from Shopify
                $inv = ($shopify && isset($shopify->inv)) ? (int)$shopify->inv : 0;
                
                // Get OV L30 from Shopify quantity column (similar to Temu)
                $ovL30 = ($shopify && isset($shopify->quantity)) ? (int)$shopify->quantity : 0;
                
                // Get TikTok L30 sales from ShipHub (normalize SKU to uppercase for matching)
                $skuUpper = strtoupper($sku);
                $tiktokL30 = ($tiktokSalesData->has($skuUpper)) ? (int)$tiktokSalesData->get($skuUpper)->l30 : 0;
                
                // Match campaigns only by SKU (campaign_name = SKU) - only Product card type
                $skuUpper = strtoupper(trim($sku));
                $hasCampaign = false;
                $campaignName = '';
                
                // Match by campaign_name = SKU using the map
                if (isset($campaignMapBySku[$skuUpper]) && !empty($campaignMapBySku[$skuUpper])) {
                    $hasCampaign = true;
                    $campaignName = implode(', ', array_unique($campaignMapBySku[$skuUpper]));
                }
                
                // Get campaign metrics for this SKU
                $metrics = $campaignMetricsBySku[$skuUpper] ?? [
                    'cost' => 0,
                    'clicks' => 0,
                    'revenue' => 0,
                    'sku_orders' => 0,
                    'roi' => 0,
                    'in_roas' => 0,
                    'custom_status' => null,
                    'budget' => null,
                ];
                
                $spend = $metrics['cost'];
                $adClicks = $metrics['clicks'];
                $revenue = $metrics['revenue'];
                $adSold = $metrics['sku_orders']; // Ad Sold taken directly from 'sku_orders' column in tiktok_campaign_reports table
                $outRoas = $metrics['roi']; // Out ROAS taken directly from 'roi' column in tiktok_campaign_reports table
                $inRoas = $metrics['in_roas']; // In ROAS taken directly from 'in_roas' column in tiktok_campaign_reports table
                
                // If campaign exists, default status to "Active", otherwise use custom_status or "Not Created"
                $customStatus = $metrics['custom_status'] ?? null;
                if ($hasCampaign && (empty($customStatus) || $customStatus === null)) {
                    $customStatus = 'Active';
                } elseif (empty($customStatus) || $customStatus === null) {
                    $customStatus = 'Not Created';
                }
                
                // Calculate ACOS: 100 / out_roas
                $acos = 0;
                if ($outRoas > 0) {
                    $acos = 100 / $outRoas;
                }
                
                // NRA - from reverb_view_data (saved by tiktok/utilized/update or TikTok pricing page)
                $nra = "RA";
                if (isset($reverbViewData[$sku]) && is_array($reverbViewData[$sku]->values ?? null) && array_key_exists('NR', $reverbViewData[$sku]->values)) {
                    $nra = $reverbViewData[$sku]->values['NR'];
                }

                $budget = isset($metrics['budget']) && $metrics['budget'] !== null ? round((float)$metrics['budget'], 2) : null;
                $data[] = [
                    'sku' => $sku,
                    'hasCampaign' => (bool)$hasCampaign,
                    'campaign_name' => $campaignName,
                    'INV' => $inv,
                    'L30' => $ovL30,
                    't_l30' => $tiktokL30,
                    'NR' => $nra,
                    'budget' => $budget,
                    'spend' => round($spend, 2),
                    'ad_sold' => $adSold,
                    'ad_clicks' => $adClicks,
                    'acos' => round($acos),
                    'out_roas' => round($outRoas, 2),
                    'in_roas' => round($inRoas, 2),
                    'status' => $customStatus,
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'total' => count($data)
            ]);
        } catch (\Exception $e) {
            \Log::error('TikTok Utilized Data Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error fetching data: ' . $e->getMessage(),
                'data' => []
            ], 400);
        }
    }

    public function uploadUtilized(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv',
                'report_range' => 'required|in:L7,L30'
            ]);

            $file = $request->file('file');
            $reportRange = $request->input('report_range');
            
            $spreadsheet = IOFactory::load($file->getPathName());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Get headers from first row
            $headers = array_map('trim', $rows[0]);
            unset($rows[0]); // Remove header row
            
            // Filter out empty rows and summary rows
            $filteredRows = [];
            foreach ($rows as $row) {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }
                
                // Skip rows that contain "Total" in first column (summary rows)
                $firstCell = trim($row[0] ?? '');
                if (!empty($firstCell) && stripos($firstCell, 'Total') !== false) {
                    continue;
                }
                
                $filteredRows[] = $row;
            }
            
            // Map header names to database field names
            $fieldMapping = [
                'Campaign name' => 'campaign_name',
                'Campaign ID' => 'campaign_id',
                'Product ID' => 'product_id',
                'Report range' => 'report_range',
                'Creative type' => 'creative_type',
                'Video title' => 'video_title',
                'Video ID' => 'video_id',
                'TikTok account' => 'tiktok_account',
                'Time posted' => 'time_posted',
                'Status' => 'status',
                'Authorization type' => 'authorization_type',
                'Cost' => 'cost',
                'SKU orders' => 'sku_orders',
                'Cost per order' => 'cost_per_order',
                'Gross revenue' => 'gross_revenue',
                'ROI' => 'roi',
                'Product ad impressions' => 'product_ad_impressions',
                'Product ad clicks' => 'product_ad_clicks',
                'Product ad click rate' => 'product_ad_click_rate',
                'Ad conversion rate' => 'ad_conversion_rate',
                '2-second ad video view rate' => 'video_view_rate_2_second',
                '6-second ad video view rate' => 'video_view_rate_6_second',
                '25% ad video view rate' => 'video_view_rate_25_percent',
                '50% ad video view rate' => 'video_view_rate_50_percent',
                '75% ad video view rate' => 'video_view_rate_75_percent',
                '100% ad video view rate' => 'video_view_rate_100_percent',
                'Currency' => 'currency',
            ];

            // Helper function to parse currency values
            $parseCurrency = function($value) {
                if (empty($value) || $value === '∞') return null;
                return floatval(str_replace(['$', ',', ' '], '', $value));
            };
            
            // Helper function to parse percentage values
            $parsePercent = function($value) {
                if (empty($value) || $value === '∞') return null;
                return floatval(str_replace(['%', ' ', ','], '', $value));
            };

            // Helper function to parse date
            $parseDate = function($value) {
                if (empty($value)) return null;
                try {
                    return \Carbon\Carbon::parse($value);
                } catch (\Exception $e) {
                    return null;
                }
            };

            DB::beginTransaction();
            try {
                // If report_range is provided, delete existing data for that range (like Temu)
                if ($reportRange) {
                    TiktokCampaignReport::where('report_range', $reportRange)->delete();
                }
                
                $imported = 0;
                $skipped = 0;
                $data = [];

                // Process data rows
                foreach ($filteredRows as $row) {
                    // Ensure row has same number of elements as headers
                    if (count($row) !== count($headers)) {
                        $row = array_slice(array_pad($row, count($headers), null), 0, count($headers));
                    }
                    
                    // Create associative array from headers and row
                    $rowData = array_combine($headers, $row);
                    
                    // Skip if campaign_id is empty (required field)
                    $campaignId = $rowData['Campaign ID'] ?? $rowData['Campaign ID'] ?? null;
                    if (empty($campaignId)) {
                        $skipped++;
                        continue;
                    }
                    
                    // Map data to database fields
                    $dbData = [];
                    foreach ($fieldMapping as $headerName => $dbField) {
                        $value = $rowData[$headerName] ?? null;
                        
                        if ($value !== null && $value !== '') {
                            // Parse based on field type
                            if (in_array($dbField, ['cost', 'cost_per_order', 'gross_revenue'])) {
                                $dbData[$dbField] = $parseCurrency($value);
                            } elseif (in_array($dbField, ['roi', 'product_ad_click_rate', 'ad_conversion_rate', 
                                'video_view_rate_2_second', 'video_view_rate_6_second', 
                                'video_view_rate_25_percent', 'video_view_rate_50_percent', 
                                'video_view_rate_75_percent', 'video_view_rate_100_percent'])) {
                                $dbData[$dbField] = $parsePercent($value);
                            } elseif ($dbField === 'time_posted') {
                                $dbData[$dbField] = $parseDate($value);
                            } elseif (in_array($dbField, ['product_ad_impressions', 'product_ad_clicks', 'sku_orders'])) {
                                $dbData[$dbField] = !empty($value) ? (int)str_replace(',', '', $value) : 0;
                            } else {
                                $dbData[$dbField] = trim($value);
                            }
                        }
                    }
                    
                    // Always set report_range from request parameter (required)
                    $dbData['report_range'] = $reportRange;
                    
                    // Create record (allow duplicates - upload all data)
                    TiktokCampaignReport::create($dbData);
                    
                    $imported++;
                    
                    // Also add to data array for frontend display
                    $rowDataForFrontend = [];
                    foreach ($headers as $index => $header) {
                        $field = strtolower(preg_replace('/[^a-z0-9]/', '_', $header));
                        $value = $row[$index] ?? null;
                        $rowDataForFrontend[$field] = $value;
                    }
                    $data[] = $rowDataForFrontend;
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'File uploaded successfully. ' . $imported . ' rows imported, ' . $skipped . ' rows skipped.',
                    'columns' => $headers,
                    'data' => $data
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Error processing file: ' . $e->getMessage()
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing file: ' . $e->getMessage()
            ], 400);
        }
    }

    public function updateUtilized(Request $request)
    {
        // Ensure JSON body is merged for requests from tiktok tabulator view
        if ($request->header('Content-Type') && str_contains($request->header('Content-Type'), 'application/json')) {
            $body = json_decode($request->getContent(), true);
            if (is_array($body)) {
                $request->merge($body);
            }
        }

        $request->validate([
            'sku' => 'required|string',
            'field' => 'required|string',
            'value' => 'required'
        ]);

        $sku = $request->input('sku');
        $field = $request->input('field');
        $value = $request->input('value');

        if ($field === 'NR') {
            // Validate NRA value
            $validNraValues = ['RA', 'NRA', 'LATER'];
            if (!in_array($value, $validNraValues)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid NRA value. Must be one of: ' . implode(', ', $validNraValues)
                ], 400);
            }

            // Store NRA in reverb_view_data (same table used by TikTok pricing for SPRICE), so it shows on both tiktok/utilized and TikTok pricing page
            $view = ReverbViewData::firstOrNew(['sku' => $sku]);
            $values = is_array($view->values) ? $view->values : [];
            $values['NR'] = $value;
            $view->values = $values;
            $view->save();

            return response()->json([
                'success' => true,
                'message' => 'NRA updated successfully'
            ]);
        }

        // Handle Status update (saves to custom_status column)
        if ($field === 'status') {
            $skuUpper = strtoupper(trim($sku));
            
            // Validate status value
            $validStatusValues = ['Active', 'Inactive', 'Not Created'];
            if (!in_array($value, $validStatusValues)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid status value. Must be one of: ' . implode(', ', $validStatusValues)
                ], 400);
            }
            
            // Update custom_status column for all matching campaigns
            $updated = TiktokCampaignReport::where('creative_type', 'Product card')
                ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$skuUpper])
                ->whereNotNull('product_id')
                ->where('product_id', '!=', '')
                ->update(['custom_status' => $value]);
            
            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'updated_count' => $updated
            ]);
        }

        // Handle In ROAS update only
        if ($field === 'in_roas') {
            $skuUpper = strtoupper(trim($sku));
            $roasValue = (float) $value;
            
            // Find campaigns matching this SKU (campaign_name = SKU) - only where product_id exists
            $campaigns = TiktokCampaignReport::where('creative_type', 'Product card')
                ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$skuUpper])
                ->whereNotNull('product_id')
                ->where('product_id', '!=', '')
                ->get();
            
            if ($campaigns->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No campaigns found for this SKU; value not saved to report',
                    'updated_count' => 0
                ]);
            }

            // Update in_roas column directly in database for all matching campaigns
            $updated = TiktokCampaignReport::where('creative_type', 'Product card')
                ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$skuUpper])
                ->whereNotNull('product_id')
                ->where('product_id', '!=', '')
                ->update(['in_roas' => $roasValue]);
            
            return response()->json([
                'success' => true,
                'message' => 'In ROAS updated successfully',
                'updated_count' => $updated
            ]);
        }

        // Handle Budget update - save to tiktok_campaign_reports for all rows matching this SKU
        if ($field === 'budget') {
            $skuUpper = strtoupper(trim($sku));
            $budgetValue = $value === '' || $value === null ? null : (float) $value;

            $updated = TiktokCampaignReport::where('creative_type', 'Product card')
                ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$skuUpper])
                ->whereNotNull('product_id')
                ->where('product_id', '!=', '')
                ->update(['budget' => $budgetValue]);

            return response()->json([
                'success' => true,
                'message' => 'Budget updated successfully',
                'updated_count' => $updated
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid field'
        ], 400);
    }
}
