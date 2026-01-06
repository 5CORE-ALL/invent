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
use App\Models\AmazonDatasheet;
use App\Models\WalmartDataView;
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
     * Get combined data with summary statistics (Like BestBuy) - Start with ProductMaster SKUs
     */
    public function getCombinedDataJson()
    {
        try {
            set_time_limit(300);
            ini_set('memory_limit', '512M');
            
            // 1. Start with ProductMaster (same as BestBuy controller)
            $productMasters = ProductMaster::orderBy("parent", "asc")
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy("sku", "asc")
                ->get();

            // Filter out PARENT rows (same as BestBuy)
            $productMasters = $productMasters->filter(function ($item) {
                return stripos($item->sku, 'PARENT') === false;
            })->values();

            // 2. Get SKU list from ProductMaster
            $skus = $productMasters->pluck("sku")
                ->filter()
                ->unique()
                ->values()
                ->all();
            
            // 3. Fetch related data using ProductMaster SKUs
            $priceData = WalmartPriceData::whereIn('sku', $skus)->get()->keyBy('sku');
            $listingData = WalmartListingViewsData::whereIn('sku', $skus)->get()->keyBy('sku');
            
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
            ->whereIn('sku', $skus)
            ->groupBy('sku')
            ->get()
            ->keyBy('sku');
            
            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
            
            // Fetch Amazon pricing data
            $amazonData = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy('sku');
            
            // Fetch Walmart data view for sprice and other saved data
            $walmartDataView = WalmartDataView::whereIn('sku', $skus)->get()->keyBy('sku');
            
            // Fetch spend data from WalmartCampaignReport (L30) - campaign name matches SKU
            $normalizeSku = fn($sku) => strtoupper(trim(preg_replace('/\s+/', ' ', str_replace("\xc2\xa0", ' ', $sku))));
            $normalizedSkus = collect($skus)->map($normalizeSku)->values()->all();
            
            $walmartCampaignReportsL30 = WalmartCampaignReport::where('report_range', 'L30')
                ->whereIn('campaignName', $normalizedSkus)
                ->get()
                ->keyBy(fn($item) => $normalizeSku($item->campaignName));

            $data = [];

            // 4. Build Result - Loop through ProductMaster (same as BestBuy)
            foreach ($productMasters as $pm) {
                $sku = strtoupper($pm->sku);
                $parent = $pm->parent;
                
                $price = $priceData->get($sku);
                $listing = $listingData->get($sku);
                $orders = $orderData->get($sku);
                $shopify = $shopifyData->get($sku);
                $amazon = $amazonData->get($sku);
                $dataView = $walmartDataView->get($sku);
                
                // Get campaign data
                $normalizedSku = $normalizeSku($sku);
                $campaignL30 = $walmartCampaignReportsL30->get($normalizedSku);
                
                // Get LP and Ship from ProductMaster - use same logic as BestBuyPricingController
                $lp = 0;
                $ship = 0;
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                // Get LP using foreach loop (same as BestBuy controller)
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") {
                        $lp = floatval($v);
                        break;
                    }
                }

                // Get Ship from Values JSON field
                if (isset($values['ship']) && $values['ship'] !== null && $values['ship'] !== '') {
                    $ship = floatval($values['ship']);
                }

                // Fallback: try direct columns if they exist
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
                if ($ship === 0 && isset($pm->ship)) {
                    $ship = floatval($pm->ship);
                }

                // Get price (SPRICE) - check walmart_data_view first, then fallback to price data
                $sprice = 0;
                if ($dataView && isset($dataView->value['sprice'])) {
                    // Use sprice from walmart_data_view if available
                    $sprice = floatval($dataView->value['sprice']);
                } else {
                    // Fallback to original price data
                    $sprice = floatval($price->price ?? $price->comparison_price ?? 0);
                }
                
                // Get quantities (from Walmart orders)
                $totalQty = $orders ? intval($orders->total_qty) : 0;
                $totalOrders = $orders ? intval($orders->total_orders) : 0;
                $totalRevenue = $orders ? floatval($orders->total_revenue) : 0;
                
                // Calculate W L30 (Walmart L30 Sales) = SPRICE × Total Qty
                $wL30 = $sprice * $totalQty;
                
                // Calculate COGS = (LP + Ship) × Total Qty
                $cogs = ($lp + $ship) * $totalQty;
                
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
                
                // Convert AD% to decimal for calculations
                $adDecimal = $adsPercent / 100;
                
                // ===== MATCH AMAZON FORMULAS EXACTLY (FBM 80% commission) =====
                
                // GPFT% = ((price × 0.80 - ship - lp) / price) × 100
                // This is Gross Profit % BEFORE ads
                $gpft = $sprice > 0 ? ((($sprice * 0.80 - $ship - $lp) / $sprice) * 100) : 0;
                
                // GROI% = ((price × 0.80 - lp - ship) / lp) × 100
                // This is Gross ROI BEFORE ads
                $groi = $lp > 0 ? ((($sprice * 0.80 - $lp - $ship) / $lp) * 100) : 0;
                
                // PFT% = GPFT% - AD%
                // This is Net Profit % AFTER ads
                $pft = $gpft - $adsPercent;
                
                // ROI% = ((price × (0.80 - AD%/100) - ship - lp) / lp) × 100
                // This is Net ROI AFTER ads
                $roi = 0;
                if ($lp > 0 && $sprice > 0) {
                    $roi = (($sprice * (0.80 - $adDecimal) - $ship - $lp) / $lp) * 100;
                }
                
                // ===== AMOUNT CALCULATIONS =====
                
                // GPFT Amount (Gross Profit Total) = (SPRICE × 0.80 - LP - Ship) × Total Qty
                $gpftAmount = ($sprice * 0.80 - $lp - $ship) * $totalQty;
                
                // PFT Amount (Net Profit Total After Ads) = GPFT Amount - Ad Spend
                $pftAmount = $gpftAmount - $adSpendL30;
                
                // For compatibility with frontend
                $profit = $pftAmount; // Net profit after ads
                $profitPercent = $pft; // PFT% (net profit % after ads)
                $roiPercent = $roi; // ROI% (net roi % after ads)
                
                // Ad metrics (from WalmartCampaignReport L30)
                $spend = $adSpendL30;

                $row = [
                    'sku' => $pm->sku, // Use original SKU from ProductMaster
                    'parent' => $parent, // Add parent field (same as BestBuy)
                    'INV' => $shopify ? intval($shopify->inv) : 0,
                    'L30' => $shopify ? intval($shopify->quantity) : 0,
                    'product_name' => $price->product_name ?? $listing->product_name ?? null,
                    'lp' => $lp,
                    'ship' => $ship,
                    'w_price' => $sprice,
                    'sprice' => $sprice,
                    'a_price' => $amazon ? floatval($amazon->price ?? 0) : null,
                    
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
                    'gpft' => $gpft, // GPFT% - Gross Profit % (before ads)
                    'groi' => $groi, // GROI% - Gross ROI % (before ads)
                    'pft' => $pft, // PFT% - Net Profit % (after ads) = GPFT% - AD%
                    'roi' => $roi, // ROI% - Net ROI % (after ads)
                    'gpft_amount' => $gpftAmount, // GPFT Amount - Gross Profit Total
                    'pft_amount' => $pftAmount, // PFT Amount - Net Profit Total (after ads)
                    
                    // Compatibility fields for Temu-style view
                    'profit' => $profit, // Net profit amount after ads
                    'profit_percent' => $profitPercent, // PFT% (net profit % after ads)
                    'roi_percent' => $roiPercent, // ROI% (net roi % after ads)
                    
                    // Ad metrics (placeholder - can add ad data table later)
                    'ads_percent' => $adsPercent,
                    'spend' => $spend,
                    
                    // Image from ProductMaster Values (same as BestBuy)
                    'image_path' => $shopify->image_src ?? ($values["image_path"] ?? ($pm->image_path ?? null)),
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

    /**
     * Save Amazon price updates to Walmart pricing data (with 12-hour expiration)
     */
    public function saveAmazonPriceUpdates(Request $request)
    {
        try {
            $updates = $request->input('updates', []);
            
            if (empty($updates)) {
                return response()->json(['error' => 'No updates provided'], 400);
            }

            $updated = 0;
            $errors = [];

            DB::beginTransaction();
            
            foreach ($updates as $update) {
                $sku = strtoupper(trim($update['sku'] ?? ''));
                $amazonPrice = floatval($update['amazon_price'] ?? 0);
                
                if (empty($sku) || $amazonPrice <= 0) {
                    $errors[] = "Invalid data for SKU: {$sku}";
                    continue;
                }

                // Update or create record in WalmartDataView table
                $dataViewRecord = WalmartDataView::where('sku', $sku)->first();
                
                if ($dataViewRecord) {
                    // Get existing value array and update it
                    $existingValue = $dataViewRecord->value ?? [];
                    $existingValue['amazon_suggested_price'] = $amazonPrice;
                    $existingValue['amazon_price_applied_at'] = now()->toDateTimeString();
                    $existingValue['sprice'] = $amazonPrice; // Store the suggested price
                    
                    $dataViewRecord->update([
                        'value' => $existingValue,
                        'updated_at' => now()
                    ]);
                } else {
                    // Create new record
                    WalmartDataView::create([
                        'sku' => $sku,
                        'value' => [
                            'amazon_suggested_price' => $amazonPrice,
                            'amazon_price_applied_at' => now()->toDateTimeString(),
                            'sprice' => $amazonPrice
                        ]
                    ]);
                }
                $updated++;
            }

            DB::commit();

            // Store in session/cache for 12 hours as backup
            $sessionKey = 'walmart_amazon_price_updates_' . session()->getId();
            session()->put($sessionKey, [
                'updates' => $updates,
                'applied_at' => now(),
                'expires_at' => now()->addHours(12)
            ]);

            $response = [
                'success' => true,
                'updated' => $updated,
                'message' => "Successfully saved {$updated} price update(s)"
            ];

            if (!empty($errors)) {
                $response['errors'] = $errors;
                $response['message'] .= ' with ' . count($errors) . ' error(s)';
            }

            Log::info("Walmart Amazon price updates saved to walmart_data_view: {$updated} records updated");

            return response()->json($response);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving Amazon price updates: ' . $e->getMessage());
            return response()->json(['error' => 'Error saving updates: ' . $e->getMessage()], 500);
        }
    }
}
