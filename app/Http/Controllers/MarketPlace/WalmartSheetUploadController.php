<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WalmartPriceData;
use App\Models\WalmartListingViewsData;
use App\Models\WalmartOrderData;
use App\Models\ProductMaster;
use App\Models\WalmartCampaignReport;
use App\Models\ShopifySku;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WalmartSheetUploadController extends Controller
{
    /**
     * Display the Walmart Sheet Upload view
     */
    public function index()
    {
        return view('market-places.walmart_sheet_upload_view');
    }

    /**
     * Upload Price Data - TRUNCATE and INSERT
     */
    public function uploadPriceData(Request $request)
    {
        $request->validate([
            'price_file' => 'required|file'
        ]);

        try {
            $file = $request->file('price_file');
            $rows = $this->parseFile($file);

            if (empty($rows)) {
                return response()->json(['error' => 'File is empty'], 400);
            }

            $headers = array_shift($rows);
            $headers = array_map('trim', $headers);
            
            // TRUNCATE TABLE
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            WalmartPriceData::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            
            Log::info('Walmart Price Data table truncated before import');

            $imported = 0;
            $skipped = 0;

            DB::beginTransaction();
            try {
                foreach ($rows as $row) {
                    $row = array_map('trim', $row);
                    
                    // Skip empty rows
                    if (count(array_filter($row)) === 0) {
                        $skipped++;
                        continue;
                    }
                    
                    $rowData = array_combine($headers, $row);
                    
                    $sku = strtoupper($rowData['SKU'] ?? '');
                    if (empty($sku)) {
                        $skipped++;
                        continue;
                    }

                    WalmartPriceData::create([
                        'sku' => $sku,
                        'item_id' => $rowData['Item ID'] ?? null,
                        'product_name' => $rowData['Product Name'] ?? null,
                        'lifecycle_status' => $rowData['Lifecycle Status'] ?? null,
                        'publish_status' => $rowData['Publish Status'] ?? null,
                        'price' => !empty($rowData['Price']) ? floatval($rowData['Price']) : null,
                        'currency' => $rowData['Currency'] ?? null,
                        'comparison_price' => !empty($rowData['Comparison Price']) ? floatval($rowData['Comparison Price']) : null,
                        'buy_box_price' => !empty($rowData['Buy Box Item Price']) ? floatval($rowData['Buy Box Item Price']) : null,
                        'buy_box_shipping_price' => !empty($rowData['Buy Box Shipping Price']) ? floatval($rowData['Buy Box Shipping Price']) : null,
                        'msrp' => !empty($rowData['MSRP']) ? floatval($rowData['MSRP']) : null,
                        'ratings' => !empty($rowData['Average Rating']) ? floatval($rowData['Average Rating']) : null,
                        'reviews_count' => !empty($rowData['Reviews Count']) ? intval($rowData['Reviews Count']) : null,
                        'brand' => $rowData['Brand'] ?? null,
                        'product_category' => $rowData['Product Category'] ?? null,
                    ]);

                    $imported++;
                }

                DB::commit();
                
                return response()->json([
                    'success' => "Successfully imported $imported price records (skipped $skipped)",
                    'imported' => $imported,
                    'skipped' => $skipped
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error importing Walmart price data: ' . $e->getMessage());
            return response()->json(['error' => 'Error importing file: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Upload Listing Views Data - TRUNCATE and INSERT
     */
    public function uploadListingViewsData(Request $request)
    {
        $request->validate([
            'listing_file' => 'required|file'
        ]);

        try {
            $file = $request->file('listing_file');
            $rows = $this->parseFile($file);

            if (empty($rows)) {
                return response()->json(['error' => 'File is empty'], 400);
            }

            $headers = array_shift($rows);
            $headers = array_map('trim', $headers);
            
            // TRUNCATE TABLE
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            WalmartListingViewsData::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            
            Log::info('Walmart Listing Views Data table truncated before import');

            $imported = 0;
            $skipped = 0;

            DB::beginTransaction();
            try {
                foreach ($rows as $row) {
                    $row = array_map('trim', $row);
                    
                    // Skip empty rows
                    if (count(array_filter($row)) === 0) {
                        $skipped++;
                        continue;
                    }
                    
                    $rowData = array_combine($headers, $row);
                    
                    $sku = strtoupper($rowData['SKU'] ?? '');
                    if (empty($sku)) {
                        $skipped++;
                        continue;
                    }

                    WalmartListingViewsData::create([
                        'sku' => $sku,
                        'item_id' => $rowData['Item Id'] ?? null,
                        'product_name' => $rowData['Product Name'] ?? null,
                        'product_type' => $rowData['Product Type'] ?? null,
                        'listing_quality' => $rowData['Listing Quality'] ?? null,
                        'content_discoverability' => $rowData['Content & Discoverability'] ?? null,
                        'ratings_reviews' => $rowData['Ratings and Reviews'] ?? null,
                        'competitive_price_score' => $rowData['CompetitivePrice Score'] ?? null,
                        'shipping_score' => $rowData['Shipping Score'] ?? null,
                        'transactibility_score' => $rowData['Transactibility Score'] ?? null,
                        'conversion_rate' => !empty($rowData['Conversion Rate']) ? floatval(str_replace('%', '', $rowData['Conversion Rate'])) : null,
                        'competitive_price' => $rowData['Competitive Price'] ?? null,
                        'walmart_price' => !empty($rowData['Walmart Price']) ? floatval(str_replace('$', '', $rowData['Walmart Price'])) : null,
                        'gmv' => !empty($rowData['Gmv']) ? floatval(str_replace('$', '', $rowData['Gmv'])) : null,
                        'ratings' => !empty($rowData['Ratings']) ? floatval($rowData['Ratings']) : null,
                        'priority' => $rowData['Priority'] ?? null,
                        'oos' => $rowData['Oos'] ?? null,
                        'condition' => $rowData['Condition'] ?? null,
                        'page_views' => !empty($rowData['Page Views']) ? intval($rowData['Page Views']) : null,
                        'total_issues' => !empty($rowData['Total Issues']) ? intval($rowData['Total Issues']) : null,
                        'customer_favourites' => $rowData['Customer Favourites'] ?? null,
                        'collectible_grade' => $rowData['Collectible Grade'] ?? null,
                        'fast_free_shipping' => $rowData['Fast & Free Shipping'] ?? null,
                    ]);

                    $imported++;
                }

                DB::commit();
                
                return response()->json([
                    'success' => "Successfully imported $imported listing view records (skipped $skipped)",
                    'imported' => $imported,
                    'skipped' => $skipped
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error importing Walmart listing views data: ' . $e->getMessage());
            return response()->json(['error' => 'Error importing file: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Upload Order Data - TRUNCATE and INSERT
     */
    public function uploadOrderData(Request $request)
    {
        $request->validate([
            'order_file' => 'required|file'
        ]);

        try {
            $file = $request->file('order_file');
            $rows = $this->parseFile($file);

            if (empty($rows)) {
                return response()->json(['error' => 'File is empty'], 400);
            }

            $headers = array_shift($rows);
            $headers = array_map('trim', $headers);
            
            // TRUNCATE TABLE
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            WalmartOrderData::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            
            Log::info('Walmart Order Data table truncated before import');

            $imported = 0;
            $skipped = 0;

            DB::beginTransaction();
            try {
                foreach ($rows as $row) {
                    $row = array_map('trim', $row);
                    
                    // Skip empty rows
                    if (count(array_filter($row)) === 0) {
                        $skipped++;
                        continue;
                    }
                    
                    $rowData = array_combine($headers, $row);
                    
                    $sku = strtoupper($rowData['SKU'] ?? '');
                    if (empty($sku)) {
                        $skipped++;
                        continue;
                    }

                    WalmartOrderData::create([
                        'sku' => $sku,
                        'po_number' => $rowData['PO#'] ?? null,
                        'order_number' => $rowData['Order#'] ?? null,
                        'order_date' => !empty($rowData['Order Date']) ? date('Y-m-d', strtotime($rowData['Order Date'])) : null,
                        'ship_by' => !empty($rowData['Ship By']) ? date('Y-m-d', strtotime($rowData['Ship By'])) : null,
                        'delivery_date' => !empty($rowData['Delivery Date']) ? date('Y-m-d', strtotime($rowData['Delivery Date'])) : null,
                        'customer_name' => $rowData['Customer Name'] ?? null,
                        'customer_address' => $rowData['Customer Shipping Address'] ?? null,
                        'qty' => !empty($rowData['Qty']) ? intval($rowData['Qty']) : null,
                        'item_cost' => !empty($rowData['Item Cost']) ? floatval($rowData['Item Cost']) : null,
                        'shipping_cost' => !empty($rowData['Shipping Cost']) ? floatval($rowData['Shipping Cost']) : null,
                        'tax' => !empty($rowData['Tax']) ? floatval($rowData['Tax']) : null,
                        'status' => $rowData['Status'] ?? null,
                        'carrier' => $rowData['Carrier'] ?? null,
                        'tracking_number' => $rowData['Tracking Number'] ?? null,
                        'tracking_url' => $rowData['Tracking Url'] ?? null,
                        'item_description' => $rowData['Item Description'] ?? null,
                        'shipping_method' => $rowData['Shipping Method'] ?? null,
                        'fulfillment_entity' => $rowData['Fulfillment Entity'] ?? null,
                    ]);

                    $imported++;
                }

                DB::commit();
                
                return response()->json([
                    'success' => "Successfully imported $imported order records (skipped $skipped)",
                    'imported' => $imported,
                    'skipped' => $skipped
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error importing Walmart order data: ' . $e->getMessage());
            return response()->json(['error' => 'Error importing file: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Parse file - supports CSV, TSV, Excel (.xlsx, .xls)
     */
    private function parseFile($file)
    {
        $fileName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        
        // Try Excel format first if extension suggests it
        if (in_array($extension, ['xlsx', 'xls'])) {
            try {
                $spreadsheet = IOFactory::load($file->getPathName());
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();
                
                // Filter out empty rows
                return array_filter($rows, function($row) {
                    return count(array_filter($row)) > 0;
                });
            } catch (\Exception $e) {
                Log::warning('Failed to parse as Excel, trying text format: ' . $e->getMessage());
            }
        }
        
        // Parse as text (CSV/TSV/Tab-separated)
        $content = file_get_contents($file->getRealPath());
        $content = preg_replace('/^\x{FEFF}/u', '', $content); // Remove BOM
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        
        // Detect delimiter (tab or comma)
        $firstLine = explode("\n", $content)[0] ?? '';
        $delimiter = (strpos($firstLine, "\t") !== false) ? "\t" : ",";
        
        // Parse with detected delimiter
        $rows = array_map(function($line) use ($delimiter) {
            return str_getcsv($line, $delimiter);
        }, explode("\n", $content));
        
        // Filter out empty rows
        return array_filter($rows, function($row) {
            return count($row) > 0 && count(array_filter($row)) > 0;
        });
    }

    /**
     * Get combined data with summary statistics (Like Temu) - Aggregated by SKU
     */
    public function getCombinedDataJson()
    {
        try {
            set_time_limit(300);
            ini_set('memory_limit', '512M');
            
            $priceData = WalmartPriceData::all()->keyBy('sku');
            $listingData = WalmartListingViewsData::all()->keyBy('sku');
            
            // Aggregate orders by SKU (sum quantities and count orders)
            $orderData = WalmartOrderData::selectRaw('
                sku,
                COUNT(*) as total_orders,
                SUM(qty) as total_qty,
                SUM(item_cost) as total_revenue,
                MAX(order_date) as last_order_date,
                SUM(shipping_cost) as total_shipping,
                SUM(tax) as total_tax
            ')
            ->where('status', '!=', 'Canceled')
            ->groupBy('sku')
            ->get()
            ->keyBy('sku');
            
            $allSkus = collect($priceData->keys())
                ->merge($listingData->keys())
                ->merge($orderData->keys())
                ->unique();
            
            $productMasterRows = ProductMaster::whereIn('sku', $allSkus)->get()->keyBy('sku');
            $shopifyData = ShopifySku::whereIn('sku', $allSkus)->get()->keyBy('sku');
            
            // Fetch spend data from WalmartCampaignReport (L30) - campaign name matches SKU
            $normalizeSku = fn($sku) => strtoupper(trim(preg_replace('/\s+/', ' ', str_replace("\xc2\xa0", ' ', $sku))));
            $normalizedSkus = $allSkus->map($normalizeSku)->values()->all();
            
            $walmartCampaignReportsL30 = WalmartCampaignReport::where('report_range', 'L30')
                ->whereIn('campaignName', $normalizedSkus)
                ->get()
                ->keyBy(fn($item) => $normalizeSku($item->campaignName));

            $data = [];

            foreach ($allSkus as $sku) {
                $price = $priceData->get($sku);
                $listing = $listingData->get($sku);
                $orders = $orderData->get($sku);
                $pm = $productMasterRows->get($sku);
                $shopify = $shopifyData->get($sku);
                
                // Get campaign data
                $normalizedSku = $normalizeSku($sku);
                $campaignL30 = $walmartCampaignReportsL30->get($normalizedSku);
                
                // Get LP and Ship from ProductMaster
                $lp = 0;
                $ship = 0;
                if ($pm) {
                    $pmValues = is_array($pm->Values) 
                        ? $pm->Values 
                        : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    
                    foreach ($pmValues as $k => $v) {
                        if (strtolower($k) === 'lp') {
                            $lp = floatval($v);
                            break;
                        }
                    }
                    if ($lp === 0 && isset($pm->lp)) {
                        $lp = floatval($pm->lp);
                    }
                    
                    if (isset($pmValues["ship"])) {
                        $ship = floatval($pmValues["ship"]);
                    } elseif (isset($pm->ship)) {
                        $ship = floatval($pm->ship);
                    }
                }

                // Get price (SPRICE)
                $sprice = floatval($price->price ?? $price->comparison_price ?? 0);
                
                // Get quantities (from Shopify L30 sales)
                $totalQty = $shopify ? intval($shopify->quantity) : 0;
                $totalOrders = $orders ? intval($orders->total_orders) : 0;
                $totalRevenue = $orders ? floatval($orders->total_revenue) : 0;
                
                // Calculate W L30 (Walmart L30 Sales) = SPRICE × Total Qty
                $wL30 = $sprice * $totalQty;
                
                // Calculate COGS = (LP + Ship) × Total Qty
                $cogs = ($lp + $ship) * $totalQty;
                
                // Calculate GPFT (Gross Profit) = (SPRICE × 0.80 - LP - Ship) × Total Qty
                $gpft = ($sprice * 0.80 - $lp - $ship) * $totalQty;
                
                // Calculate SPFT% = ((SPRICE × 0.80 - LP - Ship) / SPRICE) × 100
                $spft = $sprice > 0 ? ((($sprice * 0.80 - $lp - $ship) / $sprice) * 100) : 0;
                
                // Calculate SROI% = ((SPRICE × 0.80 - LP - Ship) / LP) × 100
                $sroi = $lp > 0 ? ((($sprice * 0.80 - $lp - $ship) / $lp) * 100) : 0;
                
                // Calculate GROI% = (GPFT / COGS) × 100
                $groi = $cogs > 0 ? (($gpft / $cogs) * 100) : 0;
                
                // Get Ad Spend from campaign data (L30)
                $adSpendL30 = $campaignL30 ? floatval($campaignL30->spend ?? 0) : 0;
                
                // Calculate ADS% = (Ad Spend / Total Sales) × 100
                // Total Sales = SPRICE × Total Qty
                $adsPercent = 0;
                if ($wL30 > 0) {
                    $adsPercent = ($adSpendL30 / $wL30) * 100;
                } elseif ($adSpendL30 > 0) {
                    $adsPercent = 100; // If there's spend but no sales
                }
                
                // For compatibility: profit = GPFT, profit_percent = SPFT%, roi_percent = GROI%
                $profit = $gpft;
                $profitPercent = $spft;
                $roiPercent = $groi;
                
                // Ad metrics (from WalmartCampaignReport L30)
                $spend = $adSpendL30;

                $row = [
                    'sku' => $sku,
                    'INV' => $shopify ? intval($shopify->inv) : 0,
                    'L30' => $shopify ? intval($shopify->quantity) : 0,
                    'product_name' => $price->product_name ?? $listing->product_name ?? null,
                    'lp' => $lp,
                    'ship' => $ship,
                    'price' => $sprice,
                    'sprice' => $sprice,
                    
                    // Listing metrics
                    'page_views' => $listing->page_views ?? 0,
                    'conversion_rate' => $listing->conversion_rate ?? 0,
                    'listing_quality' => $listing->listing_quality ?? null,
                    'competitive_price_score' => $listing->competitive_price_score ?? null,
                    
                    // Order aggregations
                    'total_orders' => $totalOrders,
                    'total_qty' => $totalQty,
                    'total_revenue' => $totalRevenue,
                    'last_order_date' => $orders ? $orders->last_order_date : null,
                    
                    // Calculated fields (with Walmart 80% commission)
                    'w_l30' => $wL30,
                    'cogs' => $cogs,
                    'gpft' => $gpft,
                    'spft' => $spft,
                    'sroi' => $sroi,
                    'groi' => $groi,
                    
                    // Compatibility fields for Temu-style view
                    'profit' => $profit,
                    'profit_percent' => $profitPercent,
                    'roi_percent' => $roiPercent,
                    
                    // Ad metrics (placeholder - can add ad data table later)
                    'ads_percent' => $adsPercent,
                    'spend' => $spend,
                    
                    // Additional useful fields
                    'comparison_price' => $price->comparison_price ?? null,
                    'buy_box_price' => $price->buy_box_price ?? null,
                    'ratings' => $price->ratings ?? $listing->ratings ?? null,
                    'reviews_count' => $price->reviews_count ?? null,
                    'lifecycle_status' => $price->lifecycle_status ?? null,
                    'publish_status' => $price->publish_status ?? null,
                    'brand' => $price->brand ?? null,
                ];

                $data[] = $row;
            }

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error fetching combined Walmart data: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get summary statistics (like Temu)
     */
    public function getSummaryStats()
    {
        try {
            $priceCount = WalmartPriceData::count();
            $listingCount = WalmartListingViewsData::count();
            $orderCount = WalmartOrderData::count();
            
            $totalRevenue = WalmartOrderData::where('status', '!=', 'Canceled')->sum('item_cost');
            $totalQty = \App\Models\ShopifySku::sum('quantity');
            $avgConversionRate = WalmartListingViewsData::avg('conversion_rate');
            $totalPageViews = WalmartListingViewsData::sum('page_views');
            
            // Get total spend from WalmartCampaignReport (L30) - same logic as BGT page
            // Use recently updated records (within last 2 hours) to ensure current data
            $totals = DB::table('walmart_campaign_reports')
                ->where('report_range', 'L30')
                ->where('updated_at', '>=', \Carbon\Carbon::now()->subHours(2))
                ->selectRaw('COALESCE(SUM(COALESCE(spend, 0)), 0) as total_spend')
                ->first();
            
            $totalSpend = round((float)($totals->total_spend ?? 0), 2);
            
            return response()->json([
                'price_records' => $priceCount,
                'listing_records' => $listingCount,
                'order_records' => $orderCount,
                'total_revenue' => number_format($totalRevenue, 2),
                'total_qty' => $totalQty,
                'avg_conversion_rate' => $avgConversionRate ? number_format($avgConversionRate, 2) . '%' : '0%',
                'total_page_views' => number_format($totalPageViews),
                'total_spend' => number_format($totalSpend, 2),
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting summary stats: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
