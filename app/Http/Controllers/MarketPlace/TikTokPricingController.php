<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\TikTokProduct;
use App\Models\ShopifySku;
use App\Models\ChannelMaster;
use App\Models\ReverbViewData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Models\AmazonChannelSummary;

class TikTokPricingController extends Controller
{
    /**
     * Display TikTok Pricing Tabulator View
     */
    public function tiktokTabulatorView(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        $marketplaceData = ChannelMaster::where('channel', 'TikTok')->first();
        $percentage = $marketplaceData ? $marketplaceData->channel_percentage : 80;

        return view("market-places.tiktok_tabulator_view", [
            "mode" => $mode,
            "demo" => $demo,
            "tiktokPercentage" => $percentage,
        ]);
    }

    /**
     * Get TikTok Data JSON for Tabulator
     */
    public function tiktokDataJson(Request $request)
    {
        try {
            $response = $this->getViewTikTokTabularData($request);
            $data = json_decode($response->getContent(), true);

            // Auto-save daily summary in background (non-blocking)
            $this->saveDailySummaryIfNeeded($data['data'] ?? []);

            return response()->json($data['data'] ?? []);
        } catch (\Exception $e) {
            Log::error('Error fetching TikTok data for Tabulator: ' . $e->getMessage());
            
            // Check if it's a table doesn't exist error
            if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Base table or view not found')) {
                return response()->json([
                    'error' => 'TikTok products table not found. Please run: php artisan migrate'
                ], 500);
            }
            
            return response()->json(['error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get TikTok Tabular Data (similar to Reverb)
     */
    public function getViewTikTokTabularData(Request $request)
    {
        // Get percentage from database (80% for TikTok)
        $marketplaceData = ChannelMaster::where('channel', 'TikTok')->first();
        $percentage = $marketplaceData ? $marketplaceData->channel_percentage : 80;
        $percentageValue = $percentage / 100;

        // Fetch all product master records (excluding parent rows)
        $productMasterRows = ProductMaster::all()
            ->filter(function ($item) {
                return stripos($item->sku, 'PARENT') === false;
            })
            ->keyBy("sku");

        // Get all unique SKUs from product master
        $skus = $productMasterRows->pluck("sku")->toArray();
        
        // Create uppercase version for TikTok products lookup
        $skusUpper = array_map('strtoupper', $skus);

        // Fetch shopify data for these SKUs
        $shopifyData = ShopifySku::whereIn("sku", $skus)->get()->keyBy("sku");

        // Fetch TikTok product data - use uppercase SKUs for query and normalize keys
        $tiktokData = TikTokProduct::whereIn("sku", $skusUpper)
            ->get()
            ->keyBy(function($item) {
                return strtoupper($item->sku);
            });

        // Fetch reverb view data for SPRICE
        $reverbViewData = ReverbViewData::whereIn("sku", $skus)->get()->keyBy("sku");

        // Get L30 sold data from ShipHub (last 30 days)
        $latestDate = DB::connection('shiphub')
            ->table('orders')
            ->where('marketplace', '=', 'tiktok')
            ->max('order_date');

        $soldData = [];
        if ($latestDate) {
            // Use California timezone for date calculations
            $latestDateCarbon = \Carbon\Carbon::parse($latestDate, 'America/Los_Angeles');
            $startDate = $latestDateCarbon->copy()->subDays(29); // 30 days total (29 previous days + today)

            $orderItems = DB::connection('shiphub')
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
                ->select([
                    'i.sku',
                    DB::raw('SUM(i.quantity_ordered) as total_sold')
                ])
                ->groupBy('i.sku')
                ->get()
                ->keyBy('sku');

            foreach ($orderItems as $item) {
                $soldData[strtoupper($item->sku)] = intval($item->total_sold);
            }
        }

        // Process data
        $processedData = [];
        $slNo = 1;

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, "PARENT") !== false;

            // Initialize the data structure
            $processedItem = [
                "SL No." => $slNo++,
                "Parent" => $productMaster->parent ?? null,
                "(Child) sku" => $sku,
                "is_parent" => $isParent,
            ];

            // Add values from product_master
            $values = $productMaster->Values ?: [];
            $processedItem["LP_productmaster"] = $values["lp"] ?? 0;
            $processedItem["Ship_productmaster"] = $values["ship"] ?? 0;
            $processedItem["COGS"] = $values["cogs"] ?? 0;
            
            // Image path
            $processedItem["image_path"] = null;

            // Add data from shopify_skus if available
            if (isset($shopifyData[$sku])) {
                $shopifyItem = $shopifyData[$sku];
                $processedItem["INV"] = $shopifyItem->inv ?? 0;
                $processedItem["L30"] = $shopifyItem->quantity ?? 0;
                $processedItem["image_path"] = $shopifyItem->image_src ?? ($values["image_path"] ?? ($productMaster->image_path ?? null));
            } else {
                $processedItem["INV"] = 0;
                $processedItem["L30"] = 0;
                $processedItem["image_path"] = $values["image_path"] ?? ($productMaster->image_path ?? null);
            }

            // Add data from tiktok_products if available
            $skuUpper = strtoupper($sku);
            if (isset($tiktokData[$skuUpper])) {
                $tiktokItem = $tiktokData[$skuUpper];
                $processedItem["TT Price"] = $tiktokItem->price ?? 0;
                $processedItem["TT Stock"] = $tiktokItem->stock ?? 0;
                $processedItem["Missing"] = ''; // SKU exists in TikTok
            } else {
                $processedItem["TT Price"] = 0;
                $processedItem["TT Stock"] = 0;
                $processedItem["Missing"] = 'M'; // SKU NOT in TikTok - mark as Missing
            }

            // Get L30 sold from ShipHub
            $processedItem["TT L30"] = isset($soldData[$skuUpper]) ? $soldData[$skuUpper] : 0;

            // Calculate MAP (INV vs TT Stock comparison)
            $inv = $processedItem["INV"];
            $ttStock = $processedItem["TT Stock"];
            
            if ($inv == $ttStock) {
                $processedItem["MAP"] = 'Map';
            } elseif ($ttStock > $inv) {
                // TT Stock is more than INV - show N Map with qty
                $diff = $ttStock - $inv;
                $processedItem["MAP"] = "N Map|$diff";
            } else {
                // INV is more than TT Stock - show only the difference number
                $diff = $inv - $ttStock;
                $processedItem["MAP"] = "Diff|$diff";
            }

            // Get SPRICE from reverb_view_data (reusing same table)
            $processedItem["SPRICE"] = 0;
            $processedItem["SGPFT"] = 0;
            $processedItem["SPFT"] = 0;
            $processedItem["SROI"] = 0;

            if (isset($reverbViewData[$sku])) {
                $viewData = $reverbViewData[$sku];
                $valuesArr = $viewData->values ?: [];
                
                $processedItem["SPRICE"] = isset($valuesArr["SPRICE"]) ? floatval($valuesArr["SPRICE"]) : 0;
                $processedItem["SGPFT"] = isset($valuesArr["SGPFT"]) ? floatval($valuesArr["SGPFT"]) : 0;
                $processedItem["SPFT"] = isset($valuesArr["SPFT"]) ? floatval(str_replace("%", "", $valuesArr["SPFT"])) : 0;
                $processedItem["SROI"] = isset($valuesArr["SROI"]) ? floatval(str_replace("%", "", $valuesArr["SROI"])) : 0;
            }

            // Calculate profit metrics
            $processedItem["percentage"] = $percentageValue;

            $price = floatval($processedItem["TT Price"]);
            $lp = floatval($processedItem["LP_productmaster"]);
            $ship = floatval($processedItem["Ship_productmaster"]);

            // GPFT%
            if ($price > 0) {
                $gpft_percentage = (($price * $percentageValue - $lp - $ship) / $price) * 100;
                $processedItem["GPFT%"] = round($gpft_percentage, 2);
            } else {
                $processedItem["GPFT%"] = 0;
            }

            // PFT%
            $processedItem["PFT %"] = $processedItem["GPFT%"];

            // ROI%
            if ($lp > 0) {
                $roi_percentage = (($price * $percentageValue - $lp - $ship) / $lp) * 100;
                $processedItem["ROI%"] = round($roi_percentage, 2);
            } else {
                $processedItem["ROI%"] = 0;
            }

            // Profit
            $processedItem["Profit"] = ($price * $percentageValue) - $lp - $ship;

            // Sales L30
            $processedItem["Sales L30"] = $price * $processedItem["TT L30"];

            // Dil%
            $inv = $processedItem["INV"];
            $l30 = $processedItem["L30"];
            $processedItem["TT Dil%"] = $inv > 0 ? round(($l30 / $inv) * 100, 2) : 0;

            $processedData[] = $processedItem;
        }

        return response()->json([
            "message" => "Data fetched successfully",
            "data" => $processedData,
            "status" => 200,
        ]);
    }

    /**
     * Upload TikTok CSV file (truncate and upload)
     */
    public function uploadTikTokCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt'
        ]);

        try {
            // Truncate table (this auto-commits, so don't use transaction)
            TikTokProduct::truncate();

            $file = $request->file('csv_file');
            $handle = fopen($file->getPathname(), 'r');
            
            // Skip header row
            $header = fgetcsv($handle);
            
            $imported = 0;
            $skipped = 0;
            $processedSkus = []; // Track SKUs we've already processed
            
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) >= 2 && !empty($row[0])) {
                    $sku = strtoupper(trim($row[0])); // Normalize to uppercase
                    $price = isset($row[1]) ? floatval($row[1]) : 0;
                    $stock = isset($row[2]) ? intval($row[2]) : 0; // Column 3 = Inv (stock)
                    
                    // Skip if SKU already processed (handle duplicates in CSV)
                    if (isset($processedSkus[$sku])) {
                        $skipped++;
                        continue;
                    }
                    
                    // Use updateOrCreate to handle any database duplicates
                    TikTokProduct::updateOrCreate(
                        ['sku' => $sku],
                        [
                            'price' => $price,
                            'stock' => $stock, // Stock from CSV column 3 (Inv)
                            'sold' => 0,  // Always 0, calculated from ShipHub in real-time
                        ]
                    );
                    
                    $processedSkus[$sku] = true;
                    $imported++;
                }
            }
            
            fclose($handle);

            $message = "Successfully imported $imported TikTok products!";
            if ($skipped > 0) {
                $message .= " ($skipped duplicate SKUs skipped)";
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            Log::error('TikTok CSV Upload Error: ' . $e->getMessage());
            return back()->with('error', 'Error uploading CSV: ' . $e->getMessage());
        }
    }

    /**
     * Download Sample CSV
     */
    public function downloadSampleCsv()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['sku', 'price', 'Inv'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Sample Data (from tiktok file)
        $sampleData = [
            ['20R WoB', '25.99', '6'],
            ['6R', '16.99', '10'],
            ['HW 1 SKY BLU', '14.47', '1'],
            ['SUH-400 1Pc', '50.19', '99'],
            ['HW 1', '14.24', '0'],
        ];

        $sheet->fromArray($sampleData, NULL, 'A2');

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(10);

        // Output Download
        $fileName = 'TikTok_Sample.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Save SPRICE updates (same as Reverb)
     */
    public function saveSpriceUpdates(Request $request)
    {
        try {
            $updates = [];
            
            if ($request->has('updates')) {
                $updates = $request->input('updates', []);
            } elseif ($request->has('sku') && $request->has('sprice')) {
                $updates = [[
                    'sku' => $request->input('sku'),
                    'sprice' => $request->input('sprice')
                ]];
            }

            $updatedCount = 0;
            $errors = [];

            foreach ($updates as $update) {
                $sku = $update['sku'] ?? null;
                $sprice = $update['sprice'] ?? null;

                if (!$sku || $sprice === null) {
                    $errors[] = "Invalid update data for SKU: " . ($sku ?? 'unknown');
                    continue;
                }

                $reverbViewData = ReverbViewData::firstOrNew(['sku' => $sku]);
                
                $values = is_array($reverbViewData->values) 
                    ? $reverbViewData->values 
                    : (json_decode($reverbViewData->values, true) ?: []);

                $values['SPRICE'] = floatval($sprice);

                $productMaster = ProductMaster::where('sku', $sku)->first();
                if ($productMaster) {
                    $pmValues = $productMaster->Values ?: [];
                    $lp = $pmValues['lp'] ?? 0;
                    $ship = $pmValues['ship'] ?? 0;
                    $percentage = 0.80; // 80% margin for TikTok

                    if ($sprice > 0) {
                        $sgpft = (($sprice * $percentage - $lp - $ship) / $sprice) * 100;
                        $values['SGPFT'] = round($sgpft, 2);
                    } else {
                        $values['SGPFT'] = 0;
                    }

                    $values['SPFT'] = $values['SGPFT'] . '%';

                    if ($lp > 0) {
                        $sroi = (($sprice * $percentage - $lp - $ship) / $lp) * 100;
                        $values['SROI'] = round($sroi, 2) . '%';
                    } else {
                        $values['SROI'] = '0%';
                    }
                }

                $reverbViewData->values = $values;
                $reverbViewData->save();

                $updatedCount++;
            }

            if ($request->has('sku') && !$request->has('updates')) {
                if ($updatedCount > 0 && count($updates) > 0) {
                    $update = $updates[0];
                    $sku = $update['sku'];
                    
                    $reverbViewData = ReverbViewData::where('sku', $sku)->first();
                    $values = $reverbViewData ? ($reverbViewData->values ?: []) : [];
                    
                    return response()->json([
                        'success' => true,
                        'sgpft_percent' => $values['SGPFT'] ?? 0,
                        'spft_percent' => floatval(str_replace('%', '', $values['SPFT'] ?? '0')),
                        'sroi_percent' => floatval(str_replace('%', '', $values['SROI'] ?? '0'))
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'error' => 'Failed to save SPRICE'
                    ], 400);
                }
            } else {
                return response()->json([
                    'success' => true,
                    'updated' => $updatedCount,
                    'errors' => $errors
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error saving TikTok SPRICE updates: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get/Set Column Visibility
     */
    public function getColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "tiktok_tabulator_column_visibility_{$userId}";
        
        $visibility = Cache::get($key, []);
        
        return response()->json($visibility);
    }

    public function setColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "tiktok_tabulator_column_visibility_{$userId}";
        
        $visibility = $request->input('visibility', []);
        
        Cache::put($key, $visibility, now()->addDays(365));
        
        return response()->json(['success' => true]);
    }

    /**
     * Auto-save daily TikTok summary snapshot (channel-wise)
     * Matches JavaScript updateSummary() logic exactly
     */
    private function saveDailySummaryIfNeeded($products)
    {
        try {
            $today = now()->toDateString();
            
            // No cache - always update when page loads
            
            // Filter: INV > 0 && not parent (TikTok doesn't have REQ filter)
            $filteredData = collect($products)->filter(function($p) {
                $invCheck = floatval($p['INV'] ?? 0) > 0;
                $notParent = !(isset($p['Parent']) && str_starts_with($p['Parent'], 'PARENT'));
                
                return $invCheck && $notParent;
            });
            
            if ($filteredData->isEmpty()) {
                return; // No valid products
            }
            
            // Initialize counters (EXACT JavaScript variable names)
            $totalSkuCount = $filteredData->count();
            $totalPft = 0;
            $totalSales = 0;
            $totalGpft = 0;
            $totalPrice = 0;
            $priceCount = 0;
            $totalInv = 0;
            $totalL30 = 0;
            $zeroSoldCount = 0;
            $moreSoldCount = 0;
            $totalDil = 0;
            $dilCount = 0;
            $totalCogs = 0;
            $totalRoi = 0;
            $roiCount = 0;
            $missingCount = 0;
            $mapCount = 0;
            $invTTStockCount = 0;
            
            // Loop through each row (EXACT JavaScript forEach logic)
            foreach ($filteredData as $row) {
                $totalPft += floatval($row['Profit'] ?? 0);
                $totalSales += floatval($row['Sales L30'] ?? 0);
                $totalGpft += floatval($row['GPFT%'] ?? 0);
                
                $price = floatval($row['TT Price'] ?? 0);
                if ($price > 0) {
                    $totalPrice += $price;
                    $priceCount++;
                }
                
                $totalInv += floatval($row['INV'] ?? 0);
                $totalL30 += floatval($row['TT L30'] ?? 0);
                
                $l30 = floatval($row['TT L30'] ?? 0);
                if ($l30 == 0) {
                    $zeroSoldCount++;
                } else {
                    $moreSoldCount++;
                }
                
                $dil = floatval($row['TT Dil%'] ?? 0);
                if ($dil > 0) {
                    $totalDil += $dil;
                    $dilCount++;
                }
                
                // COGS = LP × TT L30
                $lp = floatval($row['LP_productmaster'] ?? 0);
                $totalCogs += $lp * $l30;
                
                $roi = floatval($row['ROI%'] ?? 0);
                if ($roi != 0) {
                    $totalRoi += $roi;
                    $roiCount++;
                }
                
                // Count Missing
                if (($row['Missing'] ?? '') === 'M') {
                    $missingCount++;
                }
                
                // Count Map
                $mapValue = $row['MAP'] ?? '';
                if ($mapValue === 'Map') {
                    $mapCount++;
                }
                
                // Count INV > TT Stock (check if MAP starts with 'Diff|')
                if ($mapValue && str_starts_with($mapValue, 'Diff|')) {
                    $invTTStockCount++;
                }
            }
            
            // Calculate averages (EXACT JavaScript logic)
            $avgGpft = $totalSkuCount > 0 ? $totalGpft / $totalSkuCount : 0;
            $avgPrice = $priceCount > 0 ? $totalPrice / $priceCount : 0;
            $avgDil = $dilCount > 0 ? $totalDil / $dilCount : 0;
            $avgRoi = $roiCount > 0 ? $totalRoi / $roiCount : 0;
            
            // Store ALL metrics in JSON (flexible!)
            $summaryData = [
                // Counts
                'total_sku_count' => $totalSkuCount,
                'sold_count' => $moreSoldCount,
                'zero_sold_count' => $zeroSoldCount,
                'missing_count' => $missingCount,
                'map_count' => $mapCount,
                'inv_tt_stock_count' => $invTTStockCount,
                
                // Financial Totals
                'total_pft' => round($totalPft, 2),
                'total_sales' => round($totalSales, 2),
                'total_cogs' => round($totalCogs, 2),
                
                // Inventory
                'total_inv' => round($totalInv, 2),
                'total_l30' => round($totalL30, 2),
                
                // Calculated Percentages & Averages
                'avg_gpft' => round($avgGpft, 2),
                'avg_dil' => round($avgDil, 2),
                'avg_roi' => round($avgRoi, 2),
                'avg_price' => round($avgPrice, 2),
                
                // Metadata
                'total_products_count' => count($products),
                'calculated_at' => now()->toDateTimeString(),
                
                // Active Filters
                'filters_applied' => [
                    'inventory' => 'more',  // INV > 0
                ],
            ];
            
            // Save or update as JSON (channel-wise)
            AmazonChannelSummary::updateOrCreate(
                [
                    'channel' => 'tiktok',
                    'snapshot_date' => $today
                ],
                [
                    'summary_data' => $summaryData,
                    'notes' => 'Auto-saved daily snapshot (INV > 0)',
                ]
            );
            
            Log::info("Daily TikTok summary snapshot saved for {$today}", [
                'sku_count' => $totalSkuCount,
                'sold_count' => $moreSoldCount,
            ]);
            
        } catch (\Exception $e) {
            // Don't break the main response if summary save fails
            Log::error('Error saving daily TikTok summary: ' . $e->getMessage());
        }
    }
}

