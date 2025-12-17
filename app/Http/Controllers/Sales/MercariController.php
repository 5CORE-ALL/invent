<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MercariDailyData;
use App\Models\ProductMaster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Carbon\Carbon;

class MercariController extends Controller
{
    /**
     * Upload Mercari daily data file in chunks
     */
    public function uploadDailyDataChunk(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv,txt',
                'chunk' => 'required|integer|min:0',
                'totalChunks' => 'required|integer|min:1',
            ]);

            $file = $request->file('file');
            $chunk = $request->input('chunk');
            $totalChunks = $request->input('totalChunks');
            $uploadId = $request->input('uploadId', uniqid('mercari_upload_'));

            // Store the file temporarily
            $tempPath = storage_path('app/temp');
            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
            }

            $fileName = $uploadId . '_' . $file->getClientOriginalName();
            $filePath = $tempPath . '/' . $fileName;

            // Move uploaded file on first chunk
            if ($chunk == 0) {
                $file->move($tempPath, $fileName);
                
                // Truncate the table on first chunk
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                MercariDailyData::truncate();
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                
                Log::info('Mercari daily data table truncated before import');
            }

            // Load and process the spreadsheet
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Skip first row (headers)
            unset($rows[0]);

            $totalRows = count($rows);
            $chunkSize = ceil($totalRows / $totalChunks);
            $startRow = $chunk * $chunkSize;
            $endRow = min(($chunk + 1) * $chunkSize, $totalRows);

            // Process only this chunk's rows
            $chunkRows = array_slice($rows, $startRow, $endRow - $startRow, true);
            
            $imported = 0;
            $skipped = 0;

            DB::beginTransaction();
            try {
                foreach ($chunkRows as $index => $row) {
                    // Skip if Item Id is empty or if it's a totals/report row
                    if (empty($row[0]) || 
                        (isset($row[0]) && (stripos($row[0], 'Totals:') !== false || stripos($row[0], 'Report generated on:') !== false))) {
                        $skipped++;
                        continue;
                    }

                    // Map row data to database columns
                    // Column mapping: Item Id, Sold Date, Canceled Date, Completed Date, Item Title, Order Status, 
                    // Shipped to State, Shipped from State, Item Price, Buyer Shipping Fee, Seller Shipping Fee,
                    // Mercari Selling Fee, Payment Processing Fee Charged To Seller, Shipping Adjustment Fee,
                    // Penalty Fee, Net Seller Proceeds, Sales Tax Charged to Buyer, Merchant Fees Charged to Buyer,
                    // Service Fee Charged to Buyer, Buyer Protection Charged to Buyer, Payment Processing Fee Charged to Buyer
                    $insertData = [
                        'item_id' => isset($row[0]) && $row[0] !== '' ? trim($row[0]) : null,
                        'sold_date' => isset($row[1]) && $row[1] !== '' ? $this->parseDate($row[1]) : null,
                        'canceled_date' => isset($row[2]) && $row[2] !== '' ? $this->parseDate($row[2]) : null,
                        'completed_date' => isset($row[3]) && $row[3] !== '' ? $this->parseDate($row[3]) : null,
                        'item_title' => isset($row[4]) && $row[4] !== '' ? trim($row[4]) : null,
                        'order_status' => isset($row[5]) && $row[5] !== '' ? trim($row[5]) : null,
                        'shipped_to_state' => isset($row[6]) && $row[6] !== '' ? trim($row[6]) : null,
                        'shipped_from_state' => isset($row[7]) && $row[7] !== '' ? trim($row[7]) : null,
                        'item_price' => isset($row[8]) ? $this->sanitizePrice($row[8]) : null,
                        'buyer_shipping_fee' => isset($row[9]) ? $this->sanitizePrice($row[9]) : null,
                        'seller_shipping_fee' => isset($row[10]) ? $this->sanitizePrice($row[10]) : null,
                        'mercari_selling_fee' => isset($row[11]) ? $this->sanitizePrice($row[11]) : null,
                        'payment_processing_fee_charged_to_seller' => isset($row[12]) ? $this->sanitizePrice($row[12]) : null,
                        'shipping_adjustment_fee' => isset($row[13]) ? $this->sanitizePrice($row[13]) : null,
                        'penalty_fee' => isset($row[14]) ? $this->sanitizePrice($row[14]) : null,
                        'net_seller_proceeds' => isset($row[15]) ? $this->sanitizePrice($row[15]) : null,
                        'sales_tax_charged_to_buyer' => isset($row[16]) ? $this->sanitizePrice($row[16]) : null,
                        'merchant_fees_charged_to_buyer' => isset($row[17]) ? $this->sanitizePrice($row[17]) : null,
                        'service_fee_charged_to_buyer' => isset($row[18]) ? $this->sanitizePrice($row[18]) : null,
                        'buyer_protection_charged_to_buyer' => isset($row[19]) ? $this->sanitizePrice($row[19]) : null,
                        'payment_processing_fee_charged_to_buyer' => isset($row[20]) ? $this->sanitizePrice($row[20]) : null,
                    ];

                    MercariDailyData::create($insertData);
                    $imported++;
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            // Clean up temp file on last chunk
            if ($chunk == $totalChunks - 1) {
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Chunk $chunk processed successfully",
                'chunk' => $chunk,
                'totalChunks' => $totalChunks,
                'imported' => $imported,
                'skipped' => $skipped,
                'progress' => round((($chunk + 1) / $totalChunks) * 100, 2)
            ]);
        } catch (\Exception $e) {
            Log::error('Error uploading Mercari daily data chunk: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sanitize price values
     */
    private function sanitizePrice($value)
    {
        if (empty($value) || $value === '?') {
            return null;
        }

        // Remove currency symbols, commas, and whitespace
        $cleaned = preg_replace('/[USD$,\s]/', '', $value);
        
        return is_numeric($cleaned) ? (float)$cleaned : null;
    }

    /**
     * Parse date string to Carbon instance
     */
    private function parseDate($dateString)
    {
        if (empty($dateString) || $dateString === null || $dateString === '') {
            return null;
        }

        try {
            // Handle Excel numeric dates
            if (is_numeric($dateString)) {
                $baseDate = Carbon::create(1899, 12, 30);
                return $baseDate->addDays((int)$dateString);
            }

            // Try common date formats
            $formats = [
                'm/d/Y',
                'd/m/Y',
                'Y-m-d H:i:s',
                'Y-m-d',
                'Y-F-d H:i',
                'Y-M-d H:i',
                'm/d/Y H:i',
                'd/m/Y H:i',
            ];

            foreach ($formats as $format) {
                try {
                    $parsed = Carbon::createFromFormat($format, trim($dateString));
                    if ($parsed) {
                        return $parsed;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            // Try general parsing as last resort
            return Carbon::parse($dateString);
        } catch (\Exception $e) {
            Log::warning("Failed to parse date: {$dateString}");
            return null;
        }
    }

    /**
     * Extract potential SKUs from item title and match with ProductMaster
     * Returns the matched ProductMaster SKU or null
     * Examples: 
     * - "Speaker Stand Tripod... SS ECO 2PK BLK WoB" -> matches "ECO 2PK BLK WoB"
     * - "... GRack 3N1" -> matches "GRack 3N1" or "GRACK 3N1"
     * - "... 20R WoB" -> matches "20R WoB"
     */
    private function extractAndMatchSkuFromTitle($itemTitle, $productMastersBySku)
    {
        if (empty($itemTitle)) {
            return null;
        }

        $variations = [];
        
        // Pattern 1: Extract last sequence (highest priority)
        // Match last sequence that contains letters/numbers/dashes/spaces (3+ chars)
        // This handles: "ECO 2PK BLK WoB", "SS ECO 2PK BLK WoB", "GRack 3N1", "20R WoB"
        if (preg_match('/\b([A-Za-z0-9\s\-]{3,})\s*$/', $itemTitle, $matches)) {
            $lastPart = trim($matches[1]);
            
            // Try the full last part first (preserve original case)
            $variations[] = $lastPart; // "GRack 3N1", "20R WoB", "SS ECO 2PK BLK WoB"
            $variations[] = strtoupper($lastPart); // "GRACK 3N1", "20R WOB"
            $variations[] = str_replace(' ', '', $lastPart); // "GRack3N1"
            $variations[] = str_replace(' ', '', strtoupper($lastPart)); // "GRACK3N1"
            $variations[] = str_replace([' ', '-'], '', strtoupper($lastPart)); // "GRACK3N1"
            
            // If last part has multiple words, try without the first word (in case of prefix like "SS")
            $words = explode(' ', $lastPart);
            if (count($words) > 1) {
                // Remove first word if it's short (likely a prefix like "SS")
                if (strlen($words[0]) <= 3) {
                    $withoutPrefix = trim(implode(' ', array_slice($words, 1)));
                    if (strlen($withoutPrefix) >= 3) {
                        $variations[] = $withoutPrefix; // "ECO 2PK BLK WoB", "3N1"
                        $variations[] = strtoupper($withoutPrefix); // "ECO 2PK BLK WOB"
                        $variations[] = str_replace(' ', '', $withoutPrefix);
                        $variations[] = str_replace(' ', '', strtoupper($withoutPrefix));
                        $variations[] = str_replace([' ', '-'], '', strtoupper($withoutPrefix));
                    }
                }
            }
        }

        // Pattern 2: Extract mixed case patterns (e.g., "GRack 3N1", "20R WoB")
        // Match sequences with letters (mixed case) and numbers
        if (preg_match_all('/\b([A-Za-z]{1,}[a-z]*\s*[A-Z0-9]{1,}(?:\s+[A-Za-z0-9]+){0,3})\b/', $itemTitle, $allMatches)) {
            foreach ($allMatches[1] as $match) {
                $trimmed = trim($match);
                if (strlen($trimmed) >= 3) {
                    $variations[] = $trimmed; // "GRack 3N1"
                    $variations[] = strtoupper($trimmed); // "GRACK 3N1"
                    $variations[] = str_replace(' ', '', $trimmed); // "GRack3N1"
                    $variations[] = str_replace(' ', '', strtoupper($trimmed)); // "GRACK3N1"
                }
            }
        }

        // Pattern 3: Extract patterns starting with numbers (e.g., "20R WoB")
        // Match sequences starting with digits followed by letters and words
        if (preg_match_all('/\b(\d+[A-Za-z]+\s+[A-Za-z0-9]+(?:\s+[A-Za-z0-9]+){0,2})\b/', $itemTitle, $allMatches)) {
            foreach ($allMatches[1] as $match) {
                $trimmed = trim($match);
                if (strlen($trimmed) >= 3) {
                    $variations[] = $trimmed; // "20R WoB"
                    $variations[] = strtoupper($trimmed); // "20R WOB"
                    $variations[] = str_replace(' ', '', $trimmed); // "20RWoB"
                    $variations[] = str_replace(' ', '', strtoupper($trimmed)); // "20RWOB"
                }
            }
        }

        // Pattern 4: Extract product code patterns (e.g., "SS ECO 2PK BLK", "HW 405 WH")
        // Match sequences like "XX XXX" or "XXX XX" (2+ uppercase letters followed by alphanumeric)
        if (preg_match_all('/\b([A-Z]{2,}\s+[A-Z0-9]{1,}(?:\s+[A-Z0-9]+){0,4})\b/', $itemTitle, $allMatches)) {
            foreach ($allMatches[1] as $match) {
                $trimmed = trim($match);
                if (strlen($trimmed) >= 4) {
                    $variations[] = $trimmed; // "SS ECO 2PK BLK"
                    $variations[] = str_replace(' ', '', $trimmed); // "SSECO2PKBLK"
                }
            }
        }

        // Pattern 5: Extract all alphanumeric sequences (potential SKUs)
        if (preg_match_all('/\b([A-Za-z0-9\-]{4,})\b/', $itemTitle, $allMatches)) {
            foreach ($allMatches[1] as $match) {
                $trimmed = trim($match);
                $variations[] = $trimmed;
                $variations[] = strtoupper($trimmed);
            }
        }

        // Remove duplicates and empty values
        $variations = array_values(array_unique(array_filter($variations)));

        // Try to match each variation with ProductMaster SKUs
        foreach ($variations as $variation) {
            $normalized = strtoupper(trim($variation));
            $normalizedNoSpaces = str_replace([' ', '-', '_'], '', $normalized);

            // Try exact match first
            if (isset($productMastersBySku[$normalized])) {
                return $productMastersBySku[$normalized]->sku;
            }
            if (isset($productMastersBySku[$normalizedNoSpaces])) {
                return $productMastersBySku[$normalizedNoSpaces]->sku;
            }

            // Try partial match with ProductMaster SKUs
            foreach ($productMastersBySku as $pmSku => $pm) {
                $pmSkuUpper = strtoupper(trim($pmSku));
                $pmSkuNoSpaces = str_replace([' ', '-', '_'], '', $pmSkuUpper);
                
                // Exact match
                if ($normalized === $pmSkuUpper || $normalizedNoSpaces === $pmSkuNoSpaces) {
                    return $pm->sku;
                }
                
                // Partial match (if variation contains or is contained in SKU)
                if (strlen($normalized) >= 3) {
                    if (stripos($pmSkuUpper, $normalized) !== false || 
                        stripos($normalized, $pmSkuUpper) !== false ||
                        stripos($pmSkuNoSpaces, $normalizedNoSpaces) !== false ||
                        stripos($normalizedNoSpaces, $pmSkuNoSpaces) !== false) {
                        return $pm->sku;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get daily data for Mercari tabulator view
     */
    public function getDailyData(Request $request)
    {
        try {
            // Get all Mercari daily data, excluding cancelled orders
            $data = MercariDailyData::whereNull('canceled_date')
                ->where(function($query) {
                    $query->whereNull('order_status')
                          ->orWhere('order_status', 'not like', '%cancelled%')
                          ->orWhere('order_status', 'not like', '%canceled%');
                })
                ->orderBy('sold_date', 'desc')
                ->get();
            
            // Fetch all ProductMaster records and create lookup maps
            $productMastersBySku = ProductMaster::all()->mapWithKeys(function($pm) {
                $sku = strtoupper(trim($pm->sku));
                $skuNoSpaces = str_replace([' ', '-', '_'], '', $sku);
                return [
                    $sku => $pm,
                    $skuNoSpaces => $pm, // Also index by SKU without spaces/dashes
                ];
            });
            
            // Enhance data with LP, Ship, and matched SKU from ProductMaster
            $data = $data->map(function($item) use ($productMastersBySku) {
                // Extract and match SKU from title
                $matchedSku = $this->extractAndMatchSkuFromTitle($item->item_title, $productMastersBySku);
                
                // Build the response array with all fields explicitly included
                $responseItem = [
                    'id' => $item->id,
                    'item_id' => $item->item_id,
                    'sold_date' => $item->sold_date ? $item->sold_date->toISOString() : null,
                    'canceled_date' => $item->canceled_date ? $item->canceled_date->toISOString() : null,
                    'completed_date' => $item->completed_date ? $item->completed_date->toISOString() : null,
                    'item_title' => $item->item_title,
                    'order_status' => $item->order_status,
                    'shipped_to_state' => $item->shipped_to_state,
                    'shipped_from_state' => $item->shipped_from_state,
                    'item_price' => $item->item_price !== null ? (float)$item->item_price : null,
                    'buyer_shipping_fee' => $item->buyer_shipping_fee !== null ? (float)$item->buyer_shipping_fee : null,
                    'seller_shipping_fee' => $item->seller_shipping_fee !== null ? (float)$item->seller_shipping_fee : null,
                    'mercari_selling_fee' => $item->mercari_selling_fee !== null ? (float)$item->mercari_selling_fee : null,
                    'payment_processing_fee_charged_to_seller' => $item->payment_processing_fee_charged_to_seller !== null ? (float)$item->payment_processing_fee_charged_to_seller : null,
                    'shipping_adjustment_fee' => $item->shipping_adjustment_fee !== null ? (float)$item->shipping_adjustment_fee : null,
                    'penalty_fee' => $item->penalty_fee !== null ? (float)$item->penalty_fee : null,
                    'net_seller_proceeds' => $item->net_seller_proceeds !== null ? (float)$item->net_seller_proceeds : null,
                    'sales_tax_charged_to_buyer' => $item->sales_tax_charged_to_buyer !== null ? (float)$item->sales_tax_charged_to_buyer : null,
                    'merchant_fees_charged_to_buyer' => $item->merchant_fees_charged_to_buyer !== null ? (float)$item->merchant_fees_charged_to_buyer : null,
                    'service_fee_charged_to_buyer' => $item->service_fee_charged_to_buyer !== null ? (float)$item->service_fee_charged_to_buyer : null,
                    'buyer_protection_charged_to_buyer' => $item->buyer_protection_charged_to_buyer !== null ? (float)$item->buyer_protection_charged_to_buyer : null,
                    'payment_processing_fee_charged_to_buyer' => $item->payment_processing_fee_charged_to_buyer !== null ? (float)$item->payment_processing_fee_charged_to_buyer : null,
                    'sku' => $matchedSku,
                    'lp' => 0,
                    'ship' => 0,
                ];
                
                // Fetch from ProductMaster if SKU was matched
                if ($matchedSku) {
                    // Find the ProductMaster record by the matched SKU
                    $pm = null;
                    foreach ($productMastersBySku as $pmSku => $pmRecord) {
                        if (strtoupper(trim($pmRecord->sku)) === strtoupper(trim($matchedSku))) {
                            $pm = $pmRecord;
                            break;
                        }
                    }
                    
                    if ($pm) {
                        $values = is_array($pm->Values) 
                            ? $pm->Values 
                            : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                        
                        // Get LP
                        $lp = 0;
                        foreach ($values as $k => $v) {
                            if (strtolower($k) === "lp") {
                                $lp = floatval($v);
                                break;
                            }
                        }
                        if ($lp === 0 && isset($pm->lp)) {
                            $lp = floatval($pm->lp);
                        }
                        $responseItem['lp'] = $lp;
                        
                        // Get Ship
                        $ship = isset($values["ship"]) 
                            ? floatval($values["ship"]) 
                            : (isset($pm->ship) ? floatval($pm->ship) : 0);
                        $responseItem['ship'] = $ship;
                    }
                }
                
                return $responseItem;
            });
            
            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error fetching Mercari daily data: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Show Mercari tabulator view (With Ship - buyer_shipping_fee = 0 or null)
     */
    public function mercariTabulatorView()
    {
        return view('sales.mercari_with_ship_daily_sales_data');
    }

    /**
     * Show Mercari Without Ship view (buyer_shipping_fee > 0)
     */
    public function mercariWithoutShipView()
    {
        return view('sales.mercari_without_ship_daily_sales_data');
    }

    /**
     * Get daily data for Mercari Without Ship (buyer_shipping_fee > 0)
     */
    public function getDailyDataWithoutShip(Request $request)
    {
        try {
            // Get Mercari daily data where buyer_shipping_fee > 0
            $data = MercariDailyData::whereNull('canceled_date')
                ->where(function($query) {
                    $query->whereNull('order_status')
                          ->orWhere('order_status', 'not like', '%cancelled%')
                          ->orWhere('order_status', 'not like', '%canceled%');
                })
                ->where('buyer_shipping_fee', '>', 0)
                ->orderBy('sold_date', 'desc')
                ->get();
            
            return $this->formatMercariData($data);
        } catch (\Exception $e) {
            Log::error('Error fetching Mercari Without Ship data: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get daily data for Mercari With Ship (buyer_shipping_fee = 0 or null)
     */
    public function getDailyDataWithShip(Request $request)
    {
        try {
            // Get Mercari daily data where buyer_shipping_fee = 0 or null
            $data = MercariDailyData::whereNull('canceled_date')
                ->where(function($query) {
                    $query->whereNull('order_status')
                          ->orWhere('order_status', 'not like', '%cancelled%')
                          ->orWhere('order_status', 'not like', '%canceled%');
                })
                ->where(function($query) {
                    $query->whereNull('buyer_shipping_fee')
                          ->orWhere('buyer_shipping_fee', '=', 0);
                })
                ->orderBy('sold_date', 'desc')
                ->get();
            
            return $this->formatMercariData($data);
        } catch (\Exception $e) {
            Log::error('Error fetching Mercari With Ship data: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Format Mercari data with LP and Ship from ProductMaster
     */
    private function formatMercariData($data)
    {
        // Fetch all ProductMaster records and create lookup maps
        $productMastersBySku = ProductMaster::all()->mapWithKeys(function($pm) {
            $sku = strtoupper(trim($pm->sku));
            $skuNoSpaces = str_replace([' ', '-', '_'], '', $sku);
            return [
                $sku => $pm,
                $skuNoSpaces => $pm,
            ];
        });
        
        // Enhance data with LP, Ship, and matched SKU from ProductMaster
        $data = $data->map(function($item) use ($productMastersBySku) {
            $matchedSku = $this->extractAndMatchSkuFromTitle($item->item_title, $productMastersBySku);
            
            $responseItem = [
                'id' => $item->id,
                'item_id' => $item->item_id,
                'sold_date' => $item->sold_date ? $item->sold_date->toISOString() : null,
                'canceled_date' => $item->canceled_date ? $item->canceled_date->toISOString() : null,
                'completed_date' => $item->completed_date ? $item->completed_date->toISOString() : null,
                'item_title' => $item->item_title,
                'order_status' => $item->order_status,
                'shipped_to_state' => $item->shipped_to_state,
                'shipped_from_state' => $item->shipped_from_state,
                'item_price' => $item->item_price !== null ? (float)$item->item_price : null,
                'buyer_shipping_fee' => $item->buyer_shipping_fee !== null ? (float)$item->buyer_shipping_fee : null,
                'seller_shipping_fee' => $item->seller_shipping_fee !== null ? (float)$item->seller_shipping_fee : null,
                'mercari_selling_fee' => $item->mercari_selling_fee !== null ? (float)$item->mercari_selling_fee : null,
                'payment_processing_fee_charged_to_seller' => $item->payment_processing_fee_charged_to_seller !== null ? (float)$item->payment_processing_fee_charged_to_seller : null,
                'shipping_adjustment_fee' => $item->shipping_adjustment_fee !== null ? (float)$item->shipping_adjustment_fee : null,
                'penalty_fee' => $item->penalty_fee !== null ? (float)$item->penalty_fee : null,
                'net_seller_proceeds' => $item->net_seller_proceeds !== null ? (float)$item->net_seller_proceeds : null,
                'sales_tax_charged_to_buyer' => $item->sales_tax_charged_to_buyer !== null ? (float)$item->sales_tax_charged_to_buyer : null,
                'merchant_fees_charged_to_buyer' => $item->merchant_fees_charged_to_buyer !== null ? (float)$item->merchant_fees_charged_to_buyer : null,
                'service_fee_charged_to_buyer' => $item->service_fee_charged_to_buyer !== null ? (float)$item->service_fee_charged_to_buyer : null,
                'buyer_protection_charged_to_buyer' => $item->buyer_protection_charged_to_buyer !== null ? (float)$item->buyer_protection_charged_to_buyer : null,
                'payment_processing_fee_charged_to_buyer' => $item->payment_processing_fee_charged_to_buyer !== null ? (float)$item->payment_processing_fee_charged_to_buyer : null,
                'sku' => $matchedSku,
                'lp' => 0,
                'ship' => 0,
            ];
            
            if ($matchedSku) {
                $pm = null;
                foreach ($productMastersBySku as $pmSku => $pmRecord) {
                    if (strtoupper(trim($pmRecord->sku)) === strtoupper(trim($matchedSku))) {
                        $pm = $pmRecord;
                        break;
                    }
                }
                
                if ($pm) {
                    $values = is_array($pm->Values) 
                        ? $pm->Values 
                        : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    
                    $lp = 0;
                    foreach ($values as $k => $v) {
                        if (strtolower($k) === "lp") {
                            $lp = floatval($v);
                            break;
                        }
                    }
                    if ($lp === 0 && isset($pm->lp)) {
                        $lp = floatval($pm->lp);
                    }
                    $responseItem['lp'] = $lp;
                    
                    $ship = isset($values["ship"]) 
                        ? floatval($values["ship"]) 
                        : (isset($pm->ship) ? floatval($pm->ship) : 0);
                    $responseItem['ship'] = $ship;
                }
            }
            
            return $responseItem;
        });
        
        return response()->json($data);
    }

    /**
     * Save column visibility preferences
     */
    public function saveMercariColumnVisibility(Request $request)
    {
        try {
            $visibility = $request->input('visibility', []);
            $userId = auth()->id() ?? 'guest';
            
            cache()->put("mercari_column_visibility_{$userId}", $visibility, now()->addYear());
            
            return response()->json([
                'success' => true,
                'message' => 'Column visibility saved'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get column visibility preferences
     */
    public function getMercariColumnVisibility()
    {
        $userId = auth()->id() ?? 'guest';
        $visibility = cache()->get("mercari_column_visibility_{$userId}", []);
        
        return response()->json($visibility);
    }

    /**
     * Save column visibility preferences for Without Ship page
     */
    public function saveMercariWithoutShipColumnVisibility(Request $request)
    {
        try {
            $visibility = $request->input('visibility', []);
            $userId = auth()->id() ?? 'guest';
            
            cache()->put("mercari_without_ship_column_visibility_{$userId}", $visibility, now()->addYear());
            
            return response()->json([
                'success' => true,
                'message' => 'Column visibility saved'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get column visibility preferences for Without Ship page
     */
    public function getMercariWithoutShipColumnVisibility()
    {
        $userId = auth()->id() ?? 'guest';
        $visibility = cache()->get("mercari_without_ship_column_visibility_{$userId}", []);
        
        return response()->json($visibility);
    }
}

