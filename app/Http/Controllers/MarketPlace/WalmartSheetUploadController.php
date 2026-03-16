<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WalmartPriceData;
use App\Models\WalmartListingViewsData;
use App\Models\WalmartOrderData;
use App\Models\WalmartDailyData;
use App\Models\WalmartPricingSales;
use App\Models\ProductMaster;
use App\Models\WalmartCampaignReport;
use App\Models\ShopifySku;
use App\Models\AmazonDatasheet;
use App\Models\WalmartDataView;
use App\Models\ProductStockMapping;
use App\Models\WalmartListingStatus;
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
            // Note: Not using walmart_price_data (manual uploads) - using API data only
            $listingData = WalmartListingViewsData::whereIn('sku', $skus)->get()->keyBy('sku');
            
            // Fetch Walmart Pricing data from walmart_pricing table (from API)
            $walmartPricing = WalmartPricingSales::whereIn('sku', $skus)->get()->keyBy('sku');
            
            // Aggregate orders by SKU (sum quantities and count orders)
            // Using walmart_daily_data table with L30 period filter
            $orderData = WalmartDailyData::selectRaw('
                sku,
                COUNT(*) as total_orders,
                SUM(quantity) as total_qty,
                SUM(unit_price * quantity) as total_revenue,
                MAX(order_date) as last_order_date,
                SUM(shipping_charge) as total_shipping,
                SUM(tax_amount) as total_tax
            ')
            ->where('period', 'l30') // Filter for L30 period
            ->where('status', '!=', 'Cancelled') // Exclude cancelled orders
            ->whereIn('sku', $skus)
            ->groupBy('sku')
            ->get()
            ->keyBy('sku');
            
            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
            
            // Fetch Amazon pricing data
            $amazonData = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy('sku');
            
            // Fetch Walmart data view for sprice and other saved data
            $walmartDataView = WalmartDataView::whereIn('sku', $skus)->get()->keyBy('sku');
            
            // Fetch product stock mappings for inventory_walmart
            $productStockMappings = ProductStockMapping::whereIn('sku', $skus)->get()->keyBy('sku');
            
            // Fetch Walmart listing status for RL/NRL data
            $walmartListingStatus = WalmartListingStatus::whereIn('sku', $skus)
                ->orderBy('updated_at', 'desc')
                ->get()
                ->keyBy('sku');
            
            // Fetch spend data from WalmartCampaignReport (L30, L7, L1) - campaign name matches SKU
            $normalizeSku = fn($sku) => strtoupper(trim(preg_replace('/\s+/', ' ', str_replace("\xc2\xa0", ' ', $sku))));
            $normalizedSkus = collect($skus)->map($normalizeSku)->values()->all();
            
            $walmartCampaignReportsL30 = WalmartCampaignReport::where('report_range', 'L30')
                ->whereIn('campaignName', $normalizedSkus)
                ->get()
                ->keyBy(fn($item) => $normalizeSku($item->campaignName));
            
            $walmartCampaignReportsL7 = WalmartCampaignReport::where('report_range', 'L7')
                ->whereIn('campaignName', $normalizedSkus)
                ->get()
                ->keyBy(fn($item) => $normalizeSku($item->campaignName));
            
            $walmartCampaignReportsL1 = WalmartCampaignReport::where('report_range', 'L1')
                ->whereIn('campaignName', $normalizedSkus)
                ->get()
                ->keyBy(fn($item) => $normalizeSku($item->campaignName));

            $data = [];

            // 4. Build Result - Loop through ProductMaster (same as BestBuy)
            foreach ($productMasters as $pm) {
                $sku = strtoupper($pm->sku);
                $parent = $pm->parent;
                
                $listing = $listingData->get($sku);
                $orders = $orderData->get($sku);
                $shopify = $shopifyData->get($sku);
                $amazon = $amazonData->get($sku);
                $dataView = $walmartDataView->get($sku);
                $stockMapping = $productStockMappings->get($sku);
                $listingStatus = $walmartListingStatus->get($sku);
                $pricingApi = $walmartPricing->get($sku); // Walmart API pricing data
                
                // Get RL/NRL from listing status
                $rlNrl = null;
                if ($listingStatus) {
                    $statusValue = is_array($listingStatus->value) ? $listingStatus->value : (json_decode($listingStatus->value, true) ?? []);
                    $rlNrl = $statusValue['rl_nrl'] ?? null;
                }
                
                // Get NRA from WalmartDataView
                $nra = null;
                if ($dataView) {
                    $dataValue = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?? []);
                    $nra = $dataValue['ra_nra'] ?? null;
                }
                
                // Get campaign data
                $normalizedSku = $normalizeSku($sku);
                $campaignL30 = $walmartCampaignReportsL30->get($normalizedSku);
                $campaignL7 = $walmartCampaignReportsL7->get($normalizedSku);
                $campaignL1 = $walmartCampaignReportsL1->get($normalizedSku);
                
                // Check if campaign exists (hasCampaign)
                $hasCampaign = $campaignL30 && !empty($campaignL30->campaignName);
                
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

                // Get API Price from walmart_pricing table (current_price from Walmart API)
                $apiPrice = $pricingApi ? floatval($pricingApi->current_price ?? 0) : 0;
                
                // Get Buybox Price from walmart_pricing table
                $buyboxPrice = $pricingApi ? floatval($pricingApi->buy_box_base_price ?? $pricingApi->buy_box_total_price ?? 0) : 0;
                
                // Get SAVED price (sprice) from walmart_data_view
                // If cleared or not set, show 0 (don't fall back to API price)
                $sprice = 0;
                if ($dataView && isset($dataView->value['sprice'])) {
                    $sprice = floatval($dataView->value['sprice']);
                }
                // Note: If sprice not saved, shows as 0 (cleared state)
                
                // Note: w_price removed - using api_price instead (from Walmart API, not manual upload)
                
                // Get quantities (from Walmart orders)
                $totalQty = $orders ? intval($orders->total_qty) : 0;
                $totalOrders = $orders ? intval($orders->total_orders) : 0;
                $totalRevenue = $orders ? floatval($orders->total_revenue) : 0;
                
                // Get page views for CVR calculation
                $pageViews = $listing->page_views ?? 0;
                
                // Calculate CVR using same formula as blade: (W L30 / Views) * 100
                $cvrPercent = $pageViews > 0 ? round(($totalQty / $pageViews) * 100, 2) : 0;
                
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
                $adClicks = $campaignL30 ? intval($campaignL30->clicks ?? 0) : 0;
                $adSales = $campaignL30 ? floatval($campaignL30->sales ?? 0) : 0;
                $adSold = $campaignL30 ? intval($campaignL30->sold ?? 0) : 0;
                $adImpressions = $campaignL30 ? intval($campaignL30->impression ?? 0) : 0;
                $adCpc = $campaignL30 ? floatval($campaignL30->cpc ?? 0) : 0;
                $campaignBudget = $campaignL30 ? floatval($campaignL30->budget ?? 0) : 0;
                $campaignStatus = $campaignL30 ? ($campaignL30->status ?? '') : '';
                $campaignName = $campaignL30 ? ($campaignL30->campaignName ?? '') : '';
                
                // L7 and L1 metrics
                $l7Spend = $campaignL7 ? floatval($campaignL7->spend ?? 0) : 0;
                $l1Spend = $campaignL1 ? floatval($campaignL1->spend ?? 0) : 0;
                $l7Cpc = $campaignL7 ? floatval($campaignL7->cpc ?? 0) : 0;
                $l1Cpc = $campaignL1 ? floatval($campaignL1->cpc ?? 0) : 0;
                
                // Calculate ACOS and CVR
                $adAcos = 0;
                if ($adSales > 0) {
                    $adAcos = ($spend / $adSales) * 100;
                } elseif ($spend > 0) {
                    $adAcos = 100; // Spend but no sales
                }
                
                $adCvr = 0;
                if ($adClicks > 0) {
                    $adCvr = ($adSold / $adClicks) * 100;
                }
                
                // Calculate ALD BGT from ACOS
                $aldBgt = 0;
                if ($adAcos > 25) {
                    $aldBgt = 1;
                } elseif ($adAcos >= 20 && $adAcos <= 25) {
                    $aldBgt = 10;
                } elseif ($adAcos >= 15 && $adAcos < 20) {
                    $aldBgt = 15;
                } elseif ($adAcos >= 10 && $adAcos < 15) {
                    $aldBgt = 20;
                } elseif ($adAcos >= 5 && $adAcos < 10) {
                    $aldBgt = 25;
                } elseif ($adAcos >= 0.01 && $adAcos < 5) {
                    $aldBgt = 30;
                }

                // S BGT from ACOS (10, 15, 20, 25, 30 only; no rule for ACOS > 25)
                $sbgt = 0;
                if ($adAcos >= 20 && $adAcos <= 25) {
                    $sbgt = 10;
                } elseif ($adAcos >= 15 && $adAcos < 20) {
                    $sbgt = 15;
                } elseif ($adAcos >= 10 && $adAcos < 15) {
                    $sbgt = 20;
                } elseif ($adAcos >= 5 && $adAcos < 10) {
                    $sbgt = 25;
                } elseif ($adAcos >= 0.01 && $adAcos < 5) {
                    $sbgt = 30;
                }
                
                // Calculate 7UB and 1UB using ALD BGT
                $ub7 = ($aldBgt > 0 && ($aldBgt * 7) > 0) ? (($l7Spend / ($aldBgt * 7)) * 100) : 0;
                $ub1 = ($aldBgt > 0) ? (($l1Spend / $aldBgt) * 100) : 0;
                
                // Calculate SBID only when campaign exists and status is Live; otherwise no SBID
                $sbid = null;
                $statusUpper = strtoupper(trim($campaignStatus ?? ''));
                $campaignExists = $hasCampaign && !empty(trim($campaignName ?? ''));
                $isLive = ($statusUpper === 'LIVE' || $statusUpper === 'ENABLED');
                if ($campaignExists && $isLive) {
                    // UB zone (same as view: red < 66, green 66-99, pink > 99)
                    $zone7 = $ub7 < 66 ? 'red' : ($ub7 <= 99 ? 'green' : 'pink');
                    $zone1 = $ub1 < 66 ? 'red' : ($ub1 <= 99 ? 'green' : 'pink');
                    if ($zone7 !== $zone1) {
                        $sbid = null; // different 7UB% and 1UB% colours → no SBID
                    } else {
                        $sbid = 0;
                        $l1Zero = (round((float) $l1Cpc, 2) == 0);
                        $l7Zero = (round((float) $l7Cpc, 2) == 0);
                        if ($zone7 === 'red') {
                            // Both 7UB% and 1UB% red: L1*1.1; if L1=0 then L7*1.1; if both 0 then 0.60
                            if ($l1Zero && $l7Zero) {
                                $sbid = 0.60;
                            } elseif ($l1Zero) {
                                $sbid = round($l7Cpc * 1.1, 2);
                            } else {
                                $sbid = round($l1Cpc * 1.1, 2);
                            }
                        } elseif ($l1Zero && $l7Zero) {
                            $sbid = 0.60;
                        } elseif ($ub7 > 99) {
                            $sbid = round($l1Cpc * 0.90, 2);
                        } elseif ($ub7 < 66) {
                            $sbid = round($l7Cpc * 1.1, 2);
                        } else {
                            $sbid = round($l7Cpc * 1.0, 2);
                        }
                    }
                }
                
                // Get inventory
                $inv = $shopify ? intval($shopify->inv) : 0;
                
                // Get Walmart inventory (treat "Not Listed" as 0)
                $wInv = $stockMapping ? ($stockMapping->inventory_walmart ?? 'Not Listed') : 'Not Listed';
                $wInvNumeric = ($wInv === 'Not Listed' || $wInv === null || $wInv === '') ? 0 : intval($wInv);
                
                // Check if SKU is missing in Walmart
                // M is only shown when: INV > 0 AND product is not in Walmart API
                $isMissing = !$pricingApi && $inv > 0;
                
                // Map status logic (only when INV > 0)
                $mapStatus = '';
                if ($inv > 0) {
                    if ($isMissing) {
                        // If product is missing in Walmart, don't show Nmap
                        $mapStatus = '';
                    } else {
                        // Compare INV with W INV
                        if ($inv == $wInvNumeric) {
                            $mapStatus = 'Map';
                        } else {
                            $mapStatus = 'Nmap';
                        }
                    }
                }

                $row = [
                    'sku' => $pm->sku, // Use original SKU from ProductMaster
                    'parent' => $parent, // Add parent field (same as BestBuy)
                    'missing' => $isMissing ? 'M' : '', // M if missing in Walmart AND has inventory
                    'map_status' => $mapStatus, // Map/Nmap status
                    'rl_nrl' => $rlNrl ?? '', // RL/NRL from listing status
                    'nra' => $nra ?? '', // NRA from data view
                    'INV' => $inv,
                    'L30' => $shopify ? intval($shopify->quantity) : 0,
                    'inventory_walmart' => $wInv,
                    'product_name' => $pricingApi->item_name ?? $listing->product_name ?? null,
                    'lp' => $lp,
                    'ship' => $ship,
                    'api_price' => $apiPrice, // Current price from Walmart API (walmart_pricing table)
                    'buybox_price' => $buyboxPrice, // Buy box price from Walmart API
                    'sprice' => $sprice,  // Saved/editable price from walmart_data_view or fallback to API price
                    'a_price' => $amazon ? floatval($amazon->price ?? 0) : null,
                    
                    // Listing metrics
                    'page_views' => $pageViews,
                    'conversion_rate' => $listing->conversion_rate ?? 0, // Original conversion rate from listing data
                    'cvr_percent' => floatval($cvrPercent), // Calculated CVR using W L30 / Views (matches blade formula) - ensure numeric
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
                    
                    // Ad metrics
                    'ads_percent' => $adsPercent,
                    'spend' => $spend,
                    'ad_clicks' => $adClicks,
                    'ad_sales' => $adSales,
                    'ad_sold' => $adSold,
                    'ad_acos' => $adAcos,
                    'ad_cvr' => $adCvr,
                    'ad_impressions' => $adImpressions,
                    'ad_cpc' => $adCpc,
                    'campaign_budget' => $campaignBudget,
                    'campaign_status' => $campaignStatus,
                    'campaign_name' => $campaignName,
                    'l7_spend' => $l7Spend,
                    'l1_spend' => $l1Spend,
                    'l7_cpc' => $l7Cpc,
                    'l1_cpc' => $l1Cpc,
                    'ald_bgt' => $aldBgt,
                    'sbgt' => $sbgt,
                    'ub7' => $ub7,
                    'ub1' => $ub1,
                    'sbid' => $sbid,
                    'hasCampaign' => $hasCampaign,
                    
                    // Image from ProductMaster Values (same as BestBuy)
                    'image_path' => $shopify->image_src ?? ($values["image_path"] ?? ($pm->image_path ?? null)),
                ];

                $data[] = $row;
            }

            // Auto-save daily summary in background (non-blocking)
            $this->saveDailySummaryIfNeeded($data);

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error fetching combined Walmart data: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Auto-save daily Walmart summary snapshot (channel-wise)
     * Matches Amazon/Temu logic exactly
     */
    private function saveDailySummaryIfNeeded($products)
    {
        try {
            $today = now('America/Los_Angeles')->toDateString();
            
            // Filter: inventory > 0 && rl_nrl === 'RL' (matching frontend logic)
            $filteredData = collect($products)->filter(function($p) {
                $invCheck = floatval($p['INV'] ?? 0) > 0;
                $rlCheck = ($p['rl_nrl'] ?? '') === 'RL';
                
                return $invCheck && $rlCheck;
            });
            
            if ($filteredData->isEmpty()) {
                return; // No valid products
            }
            
            // Initialize counters
            $totalProducts = $filteredData->count();
            $totalQuantity = 0;
            $totalPriceWeighted = 0;
            $totalQty = 0;
            $totalRevenue = 0;
            $totalGpftAmt = 0; // Gross profit before ads
            $totalPftAmt = 0; // Net profit after ads
            $totalCogs = 0;
            $totalGpft = 0;
            $totalGroi = 0;
            $totalAds = 0;
            $totalPft = 0;
            $totalRoi = 0;
            $totalCvr = 0;
            $totalDil = 0;
            $totalSpend = 0;
            $totalViews = 0;
            $totalWalmartL30 = 0;
            $cvrCount = 0;
            $dilCount = 0;
            $zeroSoldCount = 0;
            $missingCount = 0;
            $mappedCount = 0;
            $notMappedCount = 0;
            $lessAmzCount = 0;
            $moreAmzCount = 0;
            $bbIssueCount = 0;
            
            // Loop through each row
            foreach ($filteredData as $row) {
                $qty = intval($row['total_qty'] ?? 0);
                $price = floatval($row['sprice'] ?? 0);
                $totalQuantity += $qty;
                $totalPriceWeighted += $price * $qty;
                $totalQty += $qty;
                
                // Revenue from orders
                $revenue = floatval($row['total_revenue'] ?? 0);
                $totalRevenue += $revenue;
                
                // Profit amounts
                $gpftAmt = floatval($row['gpft_amount'] ?? 0);
                $pftAmt = floatval($row['pft_amount'] ?? 0);
                $totalGpftAmt += $gpftAmt;
                $totalPftAmt += $pftAmt;
                
                // COGS
                $cogs = floatval($row['cogs'] ?? 0);
                $totalCogs += $cogs;
                
                // Percentage metrics (for averaging)
                $totalGpft += floatval($row['gpft'] ?? 0);
                $totalGroi += floatval($row['groi'] ?? 0);
                $totalAds += floatval($row['ads_percent'] ?? 0);
                $totalPft += floatval($row['pft'] ?? 0);
                $totalRoi += floatval($row['roi'] ?? 0);
                
                // CVR% (only count non-zero values)
                $cvr = floatval($row['cvr_percent'] ?? 0);
                if ($cvr > 0) {
                    $totalCvr += $cvr;
                    $cvrCount++;
                }
                
                // DIL% (only count non-zero values)
                $inv = floatval($row['INV'] ?? 0);
                $ovl30 = floatval($row['L30'] ?? 0);
                if ($inv > 0) {
                    $dil = ($ovl30 / $inv) * 100;
                    $totalDil += $dil;
                    $dilCount++;
                }
                
                // Ad spend and views
                $totalSpend += floatval($row['spend'] ?? 0);
                $totalViews += intval($row['page_views'] ?? 0);
                $totalWalmartL30 += $qty;
                
                // Zero sold count
                if ($qty == 0) {
                    $zeroSoldCount++;
                }
                
                // Missing
                if (($row['missing'] ?? '') === 'M') {
                    $missingCount++;
                }
                
                // Mapped/Not Mapped
                $mapStatus = $row['map_status'] ?? '';
                if ($mapStatus === 'Map') {
                    $mappedCount++;
                } elseif ($mapStatus === 'Nmap') {
                    $notMappedCount++;
                }
                
                // Compare Walmart API Price with Amazon Price
                $apiPrice = floatval($row['api_price'] ?? 0);
                $aPrice = floatval($row['a_price'] ?? 0);
                if ($aPrice > 0 && $apiPrice > 0) {
                    if ($apiPrice < $aPrice) {
                        $lessAmzCount++;
                        $bbIssueCount++; // BB Issue = API Price < A Price
                    } elseif ($apiPrice > $aPrice) {
                        $moreAmzCount++;
                    }
                }
            }
            
            // Calculate averages
            $avgPrice = $totalQty > 0 ? $totalPriceWeighted / $totalQty : 0;
            $avgGpft = $totalProducts > 0 ? $totalGpft / $totalProducts : 0;
            $avgGroi = $totalProducts > 0 ? $totalGroi / $totalProducts : 0;
            $avgAds = $totalProducts > 0 ? $totalAds / $totalProducts : 0;
            $avgPft = $totalProducts > 0 ? $totalPft / $totalProducts : 0;
            $avgRoi = $totalProducts > 0 ? $totalRoi / $totalProducts : 0;
            $avgCvr = $cvrCount > 0 ? $totalCvr / $cvrCount : 0;
            $avgDil = $dilCount > 0 ? $totalDil / $dilCount : 0;
            
            // Store ALL metrics in JSON (flexible!)
            $summaryData = [
                // Counts
                'total_products' => $totalProducts,
                'zero_sold_count' => $zeroSoldCount,
                'missing_count' => $missingCount,
                'mapped_count' => $mappedCount, // Use 'mapped_count' for consistency with Temu
                'not_mapped_count' => $notMappedCount,
                'less_amz_count' => $lessAmzCount,
                'more_amz_count' => $moreAmzCount,
                'bb_issue_count' => $bbIssueCount,
                
                // Totals
                'total_quantity' => $totalQuantity,
                'total_revenue' => round($totalRevenue, 2),
                'total_gpft_amt' => round($totalGpftAmt, 2),
                'total_pft_amt' => round($totalPftAmt, 2),
                'total_cogs' => round($totalCogs, 2),
                'total_spend' => round($totalSpend, 2),
                'total_views' => $totalViews,
                'total_walmart_l30' => $totalWalmartL30,
                
                // Averages
                'avg_price' => round($avgPrice, 2),
                'avg_gpft' => round($avgGpft, 2),
                'avg_groi' => round($avgGroi, 2),
                'avg_ads' => round($avgAds, 2),
                'avg_pft' => round($avgPft, 2),
                'avg_roi' => round($avgRoi, 2),
                'avg_cvr' => round($avgCvr, 2),
                'avg_dil' => round($avgDil, 2),
                
                // Metadata
                'total_products_count' => count($products),
                'calculated_at' => now()->toDateTimeString(),
                
                // Active Filters
                'filters_applied' => [
                    'inventory' => 'gt0',  // INV > 0
                    'rl_nrl' => 'RL',      // RL only
                ],
            ];
            
            // Save or update as JSON (channel-wise)
            \App\Models\AmazonChannelSummary::updateOrCreate(
                [
                    'channel' => 'walmart',
                    'snapshot_date' => $today
                ],
                [
                    'summary_data' => $summaryData,
                    'notes' => 'Auto-saved daily snapshot (INV > 0, RL only)',
                ]
            );
            
            Log::info("Daily Walmart summary snapshot saved for {$today}", [
                'product_count' => $totalProducts,
                'zero_sold_count' => $zeroSoldCount,
                'mapped_count' => $mappedCount,
            ]);
            
        } catch (\Exception $e) {
            // Don't break the main response if summary save fails
            Log::error('Error saving daily Walmart summary: ' . $e->getMessage());
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
            $orderCount = WalmartDailyData::where('period', 'l30')->count();
            
            $totalRevenue = WalmartDailyData::where('period', 'l30')
                ->where('status', '!=', 'Cancelled')
                ->selectRaw('SUM(unit_price * quantity) as revenue')
                ->value('revenue');
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

    /**
     * Get historical metrics data for chart display
     */
    public function getMetricsHistory(Request $request)
    {
        try {
            $days = $request->input('days', 7);
            $sku = $request->input('sku', null);
            
            // Use California timezone (Pacific Time) to match data collection
            $startDate = \Carbon\Carbon::today('America/Los_Angeles')->subDays($days - 1);
            $endDate = \Carbon\Carbon::today('America/Los_Angeles');
            
            $query = DB::table('walmart_sku_daily_data')
                ->where('record_date', '>=', $startDate)
                ->where('record_date', '<=', $endDate)
                ->orderBy('record_date', 'asc');
            
            if ($sku) {
                // For SKU-specific chart
                $query->where('sku', strtoupper(trim($sku)));
                
                $metricsData = $query->get();
                
                $data = $metricsData->map(function ($row) {
                    $dailyData = json_decode($row->daily_data, true);
                    
                    return [
                        'date' => $row->record_date,
                        'date_formatted' => date('M d', strtotime($row->record_date)),
                        'price' => round((float) ($dailyData['price'] ?? 0), 2),
                        'views' => (int) ($dailyData['views'] ?? 0),
                        'cvr_percent' => round((float) ($dailyData['cvr_percent'] ?? 0), 2),
                        'ads_percent' => round((float) ($dailyData['ad_percent'] ?? 0), 2)
                    ];
                });
            } else {
                // For overall metrics chart - aggregate all SKUs by date
                $metricsData = $query->get();
                
                // Group by date and calculate averages
                $dataByDate = [];
                foreach ($metricsData as $row) {
                    $dailyData = json_decode($row->daily_data, true);
                    $dateKey = $row->record_date;
                    
                    if (!isset($dataByDate[$dateKey])) {
                        $dataByDate[$dateKey] = [
                            'date' => $dateKey,
                            'prices' => [],
                            'views' => 0,
                            'gpft_values' => [],
                            'groi_values' => [],
                            'cvr_values' => []
                        ];
                    }
                    
                    $price = (float) ($dailyData['price'] ?? 0);
                    $views = (int) ($dailyData['views'] ?? 0);
                    $cvr = (float) ($dailyData['cvr_percent'] ?? 0);
                    $gpft = (float) ($dailyData['gpft_percent'] ?? 0);
                    $groi = (float) ($dailyData['groi_percent'] ?? 0);
                    
                    if ($price > 0) {
                        $dataByDate[$dateKey]['prices'][] = $price;
                        if ($gpft != 0) {
                            $dataByDate[$dateKey]['gpft_values'][] = $gpft;
                        }
                        if ($groi != 0) {
                            $dataByDate[$dateKey]['groi_values'][] = $groi;
                        }
                    }
                    
                    $dataByDate[$dateKey]['views'] += $views;
                    if ($cvr > 0) {
                        $dataByDate[$dateKey]['cvr_values'][] = $cvr;
                    }
                }
                
                // Calculate final averages
                $data = collect($dataByDate)->map(function ($item, $dateKey) {
                    return [
                        'date' => $dateKey,
                        'date_formatted' => date('M d', strtotime($dateKey)),
                        'avg_price' => count($item['prices']) > 0 ? round(array_sum($item['prices']) / count($item['prices']), 2) : 0,
                        'total_views' => $item['views'],
                        'avg_gpft' => count($item['gpft_values']) > 0 ? round(array_sum($item['gpft_values']) / count($item['gpft_values']), 2) : 0,
                        'avg_groi' => count($item['groi_values']) > 0 ? round(array_sum($item['groi_values']) / count($item['groi_values']), 2) : 0,
                        'avg_cvr' => count($item['cvr_values']) > 0 ? round(array_sum($item['cvr_values']) / count($item['cvr_values']), 2) : 0
                    ];
                })->values();
            }
            
            return response()->json($data);
            
        } catch (\Exception $e) {
            Log::error('Error fetching Walmart metrics history: ' . $e->getMessage());
            return response()->json(['error' => 'Error fetching metrics data'], 500);
        }
    }
    
    /**
     * Save/update cell data (like sprice) to walmart_data_view
     */
    public function updateCellData(Request $request)
    {
        try {
            $sku = $request->input('sku');
            $field = $request->input('field');
            $value = $request->input('value');
            
            if (!$sku || !$field) {
                return response()->json(['error' => 'SKU and field are required'], 400);
            }
            
            // Allowed fields to update
            $allowedFields = ['sprice', 'rl_nrl'];
            
            if (!in_array($field, $allowedFields)) {
                return response()->json(['error' => 'Field not allowed for update'], 400);
            }
            
            // Handle rl_nrl field separately (save to walmart_listing_status)
            if ($field === 'rl_nrl') {
                Log::info('Updating RL/NRL', ['sku' => $sku, 'value' => $value]);
                
                // Get or create walmart_listing_status record
                $listingStatus = WalmartListingStatus::where('sku', $sku)
                    ->orderBy('updated_at', 'desc')
                    ->first();
                
                if ($listingStatus) {
                    $existingValue = is_array($listingStatus->value) ? $listingStatus->value : (json_decode($listingStatus->value, true) ?? []);
                    Log::info('Existing listing status found', ['existing_value' => $existingValue]);
                } else {
                    $existingValue = [];
                    Log::info('No existing listing status, creating new');
                }
                
                // Update rl_nrl field
                $existingValue['rl_nrl'] = $value;
                
                Log::info('Updated value', ['updated_value' => $existingValue]);
                
                // Delete any duplicates and create fresh record
                WalmartListingStatus::where('sku', $sku)->delete();
                $created = WalmartListingStatus::create([
                    'sku' => $sku,
                    'value' => $existingValue
                ]);
                
                Log::info('RL/NRL saved successfully', ['id' => $created->id, 'sku' => $sku, 'value' => $existingValue]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'RL/NRL updated successfully',
                    'data' => [
                        'sku' => $sku,
                        'rl_nrl' => $value
                    ]
                ]);
            }
            
            // Handle other fields (like sprice) - save to walmart_data_view
            $dataView = WalmartDataView::firstOrNew(['sku' => $sku]);
            
            // Get existing value array or create new one
            $valueArray = is_array($dataView->value) ? $dataView->value : [];
            
            // If value is 0 or empty, DELETE the field (clear it)
            if (empty($value) || floatval($value) == 0) {
                unset($valueArray[$field]);  // Remove the key entirely
                Log::info("Cleared {$field} for SKU: {$sku}");
            } else {
                // Update the specific field
                $valueArray[$field] = floatval($value);
                Log::info("Updated {$field} for SKU: {$sku} to {$value}");
            }
            
            // Save (or delete if array is now empty)
            if (empty($valueArray)) {
                // If no fields left, delete the entire record
                $dataView->delete();
                Log::info("Deleted walmart_data_view record for SKU: {$sku} (no fields left)");
            } else {
                $dataView->value = $valueArray;
                $dataView->save();
            }
            
            return response()->json([
                'success' => true,
                'message' => ucfirst($field) . ($value == 0 ? ' cleared' : ' updated') . ' successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error updating cell data: ' . $e->getMessage());
            return response()->json(['error' => 'Error saving data'], 500);
        }
    }

    /**
     * Get campaign data by SKU for KW campaigns (similar to eBay implementation)
     * Used by the info icon modal in walmart_sheet_upload_view
     */
    public function getCampaignDataBySku(Request $request)
    {
        $sku = $request->input('sku');
        if (!$sku) {
            return response()->json(['error' => 'SKU is required'], 400);
        }

        // Normalize SKU function (matching WalmartUtilisationController)
        $normalizeSku = function ($sku) {
            if (empty($sku)) return '';
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
            return trim($sku);
        };

        $cleanSku = $normalizeSku($sku);

        // Get KW campaigns - campaignName matches SKU (Walmart uses campaignName, not campaign_name)
        $kwL30 = WalmartCampaignReport::where('report_range', 'L30')
            ->where(function($q) use ($cleanSku, $normalizeSku) {
                // Match by normalized campaignName
                $q->whereRaw('UPPER(TRIM(campaignName)) = ?', [$cleanSku]);
            })
            ->get();

        $kwL7 = WalmartCampaignReport::where('report_range', 'L7')
            ->where(function($q) use ($cleanSku) {
                $q->whereRaw('UPPER(TRIM(campaignName)) = ?', [$cleanSku]);
            })
            ->get()
            ->keyBy('campaign_id');

        $kwL1 = WalmartCampaignReport::where('report_range', 'L1')
            ->where(function($q) use ($cleanSku) {
                $q->whereRaw('UPPER(TRIM(campaignName)) = ?', [$cleanSku]);
            })
            ->get()
            ->keyBy('campaign_id');

        $kwCampaigns = [];
        foreach ($kwL30 as $r) {
            $campaignId = $r->campaign_id ?? null;
            $cid = $campaignId !== null ? (string) $campaignId : null;
            
            // Skip if no valid campaign_id
            if (empty($cid) || $cid === '' || $cid === '0') {
                continue;
            }
            
            $rL7 = $cid ? $kwL7->get($cid) : null;
            $rL1 = $cid ? $kwL1->get($cid) : null;
            if (!$rL7 && $cid) {
                $rL7 = $kwL7->first(fn ($x) => (string) ($x->campaign_id ?? '') === $cid);
            }
            if (!$rL1 && $cid) {
                $rL1 = $kwL1->first(fn ($x) => (string) ($x->campaign_id ?? '') === $cid);
            }

            $spend = (float) ($r->spend ?? 0);
            $sales = (float) ($r->sales ?? 0);
            $clicks = (int) ($r->clicks ?? 0);
            $sold = (int) ($r->sold ?? 0);
            $acos = ($sales > 0) ? (($spend / $sales) * 100) : (($spend > 0) ? 100 : 0);
            $adCvr = $clicks > 0 ? (($sold / $clicks) * 100) : 0;
            $bgt = (float) ($r->budget ?? 0);

            $l7Spend = $rL7 ? (float) ($rL7->spend ?? 0) : 0;
            $l1Spend = $rL1 ? (float) ($rL1->spend ?? 0) : 0;
            $l7Cpc = $rL7 ? (float) ($rL7->cpc ?? 0) : 0;
            $l1Cpc = $rL1 ? (float) ($rL1->cpc ?? 0) : 0;

            // Calculate ALD BGT from ACOS (Walmart-specific logic)
            $aldBgt = 0;
            if ($acos > 25) {
                $aldBgt = 1;
            } elseif ($acos >= 20 && $acos <= 25) {
                $aldBgt = 10;
            } elseif ($acos >= 15 && $acos < 20) {
                $aldBgt = 15;
            } elseif ($acos >= 10 && $acos < 15) {
                $aldBgt = 20;
            } elseif ($acos >= 5 && $acos < 10) {
                $aldBgt = 25;
            } elseif ($acos >= 0.01 && $acos < 5) {
                $aldBgt = 30;
            }

            // S BGT from ACOS (10, 15, 20, 25, 30 only; no rule for ACOS > 25)
            $sbgt = 0;
            if ($acos >= 20 && $acos <= 25) {
                $sbgt = 10;
            } elseif ($acos >= 15 && $acos < 20) {
                $sbgt = 15;
            } elseif ($acos >= 10 && $acos < 15) {
                $sbgt = 20;
            } elseif ($acos >= 5 && $acos < 10) {
                $sbgt = 25;
            } elseif ($acos >= 0.01 && $acos < 5) {
                $sbgt = 30;
            }

            // Calculate 7UB and UB1 using ALD BGT (not actual budget)
            // 7UB = (L7 spend / (ALD BGT * 7)) * 100
            $ub7 = ($aldBgt > 0 && ($aldBgt * 7) > 0) ? (($l7Spend / ($aldBgt * 7)) * 100) : 0;
            // UB1 = (L1 spend / ALD BGT) * 100
            $ub1 = ($aldBgt > 0) ? (($l1Spend / $aldBgt) * 100) : 0;

            // Calculate SBID (Walmart-specific logic); show no SBID if 7UB% and 1UB% have different colours
            $zone7 = $ub7 < 66 ? 'red' : ($ub7 <= 99 ? 'green' : 'pink');
            $zone1 = $ub1 < 66 ? 'red' : ($ub1 <= 99 ? 'green' : 'pink');
            $sbid = null;
            if ($zone7 === $zone1) {
                $sbid = 0;
                $l1Zero = (round((float) $l1Cpc, 2) == 0);
                $l7Zero = (round((float) $l7Cpc, 2) == 0);
                if ($zone7 === 'red') {
                    if ($l1Zero && $l7Zero) {
                        $sbid = 0.60;
                    } elseif ($l1Zero) {
                        $sbid = round($l7Cpc * 1.1, 2);
                    } else {
                        $sbid = round($l1Cpc * 1.1, 2);
                    }
                } elseif ($l1Zero && $l7Zero) {
                    $sbid = 0.60;
                } elseif ($ub7 > 99) {
                    $sbid = round($l1Cpc * 0.90, 2);
                } elseif ($ub7 < 66) {
                    $sbid = round($l7Cpc * 1.1, 2);
                } else {
                    $sbid = round($l7Cpc * 1.0, 2);
                }
            }

            // Only include campaigns that have activity
            if ($spend > 0 || $clicks > 0 || $sales > 0 || $bgt > 0) {
                $kwCampaigns[] = [
                    'campaign_name' => $r->campaignName ?? 'N/A',
                    'bgt' => $bgt,
                    'ald_bgt' => $aldBgt,
                    'sbgt' => $sbgt,
                    'acos' => $acos,
                    'clicks' => $clicks,
                    'ad_spend' => $spend,
                    'ad_sales' => $sales,
                    'ad_sold' => $sold,
                    'ad_cvr' => $adCvr,
                    '7ub' => $ub7,
                    '1ub' => $ub1,
                    'l7cpc' => $l7Cpc,
                    'l1cpc' => $l1Cpc,
                    'sbid' => $sbid,
                ];
            }
        }

        return response()->json([
            'kw_campaigns' => $kwCampaigns,
            'pt_campaigns' => [], // Walmart doesn't have PT campaigns like eBay
        ]);
    }
}
