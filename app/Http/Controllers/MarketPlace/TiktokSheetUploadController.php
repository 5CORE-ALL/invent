<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TiktokSheet;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class TiktokSheetUploadController extends Controller
{
    /**
     * Display the TikTok Sheet Upload view
     */
    public function index()
    {
        return view('market-places.tiktok_sheet_upload_view');
    }

    /**
     * Upload TikTok Sheet Data - UPDATE prices based on SKU match
     */
    public function uploadSheetData(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file'
        ]);

        try {
            $file = $request->file('excel_file');
            $rows = $this->parseFile($file);

            if (empty($rows)) {
                return response()->json(['error' => 'File is empty'], 400);
            }

            $headers = array_shift($rows);
            $headers = array_map('trim', $headers);
            
            Log::info('TikTok Sheet Upload - Headers: ' . json_encode($headers));

            $updated = 0;
            $skipped = 0;
            $notFound = 0;

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
                    
                    // Get SKU from "Seller SKU" column
                    $sku = strtoupper($rowData['Seller SKU'] ?? '');
                    if (empty($sku)) {
                        $skipped++;
                        continue;
                    }

                    // Get data to update
                    $price = !empty($rowData['Retail Price (Local Currency)']) ? floatval($rowData['Retail Price (Local Currency)']) : null;
                    $quantity = !empty($rowData['Quantity in Main Warehouse']) ? intval($rowData['Quantity in Main Warehouse']) : null;

                    // Find existing record by SKU
                    $tiktokSheet = TiktokSheet::where('sku', $sku)->first();
                    
                    if ($tiktokSheet) {
                        // UPDATE existing record
                        $updateData = [];
                        
                        if ($price !== null) {
                            $updateData['price'] = $price;
                        }
                        
                        // Note: We're storing quantity but you can decide if you want to update l30/l60 or separate field
                        // For now, I'll just update the price as that's the main use case
                        
                        if (!empty($updateData)) {
                            $tiktokSheet->update($updateData);
                            $updated++;
                        } else {
                            $skipped++;
                        }
                    } else {
                        // SKU not found in database
                        $notFound++;
                        Log::warning("TikTok Sheet Upload - SKU not found: {$sku}");
                    }
                }

                DB::commit();
                
                return response()->json([
                    'success' => "Successfully updated $updated records (skipped $skipped, not found $notFound)",
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'not_found' => $notFound
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error uploading TikTok sheet data: ' . $e->getMessage());
            return response()->json(['error' => 'Error uploading file: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get TikTok data combined with ProductMaster for display
     */
    public function getDataJson()
    {
        try {
            set_time_limit(300);
            ini_set('memory_limit', '512M');
            
            // Get TikTok sheet data
            $tiktokData = TiktokSheet::all()->keyBy('sku');
            
            // Get ProductMaster data
            $productMasters = ProductMaster::orderBy("parent", "asc")
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy("sku", "asc")
                ->get();

            // Filter out PARENT rows
            $productMasters = $productMasters->filter(function ($item) {
                return stripos($item->sku, 'PARENT') === false;
            })->values();

            // Get SKU list from ProductMaster
            $skus = $productMasters->pluck("sku")
                ->filter()
                ->unique()
                ->values()
                ->all();
            
            // Get Shopify inventory data
            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

            $data = [];

            // Build result - only show SKUs that exist in tiktok_sheet_data
            foreach ($productMasters as $pm) {
                $sku = strtoupper($pm->sku);
                
                // Only include if exists in TikTok sheet data
                if (!$tiktokData->has($sku)) {
                    continue;
                }
                
                $tiktok = $tiktokData->get($sku);
                $shopify = $shopifyData->get($sku);
                
                // Get LP and Ship from ProductMaster
                $lp = 0;
                $ship = 0;
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                // Get LP
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") {
                        $lp = floatval($v);
                        break;
                    }
                }

                // Get Ship
                if (isset($values['ship']) && $values['ship'] !== null && $values['ship'] !== '') {
                    $ship = floatval($values['ship']);
                }

                // Fallback to direct columns
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
                if ($ship === 0 && isset($pm->ship)) {
                    $ship = floatval($pm->ship);
                }

                $row = [
                    'sku' => $pm->sku,
                    'parent' => $pm->parent,
                    'price' => floatval($tiktok->price ?? 0),
                    'quantity_warehouse' => 0, // This will be updated from sheet upload
                    'INV' => $shopify ? intval($shopify->inv) : 0,
                    'L30' => $shopify ? intval($shopify->quantity) : 0,
                    'lp' => $lp,
                    'ship' => $ship,
                    'image_path' => $shopify->image_src ?? ($values["image_path"] ?? ($pm->image_path ?? null)),
                ];

                $data[] = $row;
            }

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error fetching TikTok data: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => $e->getMessage()], 500);
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
}

