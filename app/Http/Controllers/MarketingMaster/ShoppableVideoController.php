<?php

namespace App\Http\Controllers\MarketingMaster;

use App\Http\Controllers\Controller;
use App\Models\FacebookFeedAd;
use App\Models\FacebookReelAd;
use App\Models\FacebookVideoAd;
use App\Models\FourRationVideo;
use App\Models\InstagramFeedAd;
use App\Models\InstagramReelAd;
use App\Models\InstagramVideoAd;
use App\Models\NineRationVideo;
use App\Models\OneRationVideo;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\SixteenRationVideo;
use App\Models\TiktokVideoAd;
use App\Models\YoutubeShortsAd;
use App\Models\YoutubeVideoAd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ShoppableVideoController extends Controller
{
    public function oneRation(){
        return view('marketing-masters.video-required.shoppable-video.one-ration');
    }

    public function getOneRatioVideoData(Request $request)
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->pluck('sku')->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $videoPostedValues = OneRationVideo::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = [];
        $slNo = 1;

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, 'PARENT') !== false;

            // Default social links
            $oneratio_link = '';

            // Get social links from video_posted_values table if available
            if (isset($videoPostedValues[$sku])) {
                $value = $videoPostedValues[$sku]->value;
                if (is_string($value)) {
                    $value = json_decode($value, true);
                }
                $oneratio_link = $value['oneratio_link'] ?? '';
            }

            // Initialize the data structure
            $processedItem = [
                'SL No.' => $slNo++,
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'is_parent' => $isParent,
            ];

            $processedItem['oneratio_link'] = $oneratio_link;


            // Add data from shopify_skus if available
            if (isset($shopifyData[$sku])) {
                $shopifyItem = $shopifyData[$sku];
                $processedItem['INV'] = $shopifyItem->inv ?? 0;
                $processedItem['L30'] = $shopifyItem->quantity ?? 0;
            } else {
                $processedItem['INV'] = 0;
                $processedItem['L30'] = 0;
            }

            $processedData[] = $processedItem;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function saveOneRationVideo(Request $request){

        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $sku = $request->sku;
        $newValue = $request->value;

        $existing = OneRationVideo::where('sku', $sku)->first();
        $mergedValue = $newValue;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $newValue);
        }

        $videoPosted = OneRationVideo::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        // === Save to FacebookFeedAd ===
        FacebookFeedAd::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        // === Save to InstagramFeedAd ===
        InstagramFeedAd::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        return response()->json([
            'success' => true,
            'data' => $videoPosted
        ]);
    }

    public function fourRation(){
        return view('marketing-masters.video-required.shoppable-video.four-ration');
    }

    public function getFourRatioVideoData(Request $request)
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->pluck('sku')->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $videoPostedValues = FourRationVideo::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = [];
        $slNo = 1;

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, 'PARENT') !== false;

            // Default social links
            $four_ratio_link = '';

            // Get social links from video_posted_values table if available
            if (isset($videoPostedValues[$sku])) {
                $value = $videoPostedValues[$sku]->value;
                if (is_string($value)) {
                    $value = json_decode($value, true);
                }
                $four_ratio_link = $value['four_ratio_link'] ?? '';
            }

            // Initialize the data structure
            $processedItem = [
                'SL No.' => $slNo++,
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'is_parent' => $isParent,
            ];

            $processedItem['four_ratio_link'] = $four_ratio_link;


            // Add data from shopify_skus if available
            if (isset($shopifyData[$sku])) {
                $shopifyItem = $shopifyData[$sku];
                $processedItem['INV'] = $shopifyItem->inv ?? 0;
                $processedItem['L30'] = $shopifyItem->quantity ?? 0;
            } else {
                $processedItem['INV'] = 0;
                $processedItem['L30'] = 0;
            }

            $processedData[] = $processedItem;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function saveFourRationVideo(Request $request){

        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $sku = $request->sku;
        $newValue = $request->value;

        $existing = FourRationVideo::where('sku', $sku)->first();
        $mergedValue = $newValue;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $newValue);
        }

        $videoPosted = FourRationVideo::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        // === Save to FacebookFeedAd ===
        FacebookVideoAd::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        // === Save to InstagramFeedAd ===
        InstagramVideoAd::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        return response()->json([
            'success' => true,
            'data' => $videoPosted
        ]);
    }

    public function nineRation(){
        return view('marketing-masters.video-required.shoppable-video.nine-ration');
    }

    public function getNineRatioVideoData(Request $request)
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->pluck('sku')->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $videoPostedValues = NineRationVideo::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = [];
        $slNo = 1;

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, 'PARENT') !== false;

            // Default social links
            $nine_ratio_link = '';
            $remark = '';

            // Get social links from video_posted_values table if available
            if (isset($videoPostedValues[$sku])) {
                $value = $videoPostedValues[$sku]->value;
                if (is_string($value)) {
                    $value = json_decode($value, true);
                }
                $nine_ratio_link = $value['nine_ratio_link'] ?? '';
                $remark = $value['remark'] ?? '';
            }

            // Initialize the data structure
            $processedItem = [
                'SL No.' => $slNo++,
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'is_parent' => $isParent,
            ];

            $processedItem['nine_ratio_link'] = $nine_ratio_link;
            $processedItem['remark'] = $remark;


            // Add data from shopify_skus if available
            if (isset($shopifyData[$sku])) {
                $shopifyItem = $shopifyData[$sku];
                $processedItem['INV'] = $shopifyItem->inv ?? 0;
                $processedItem['L30'] = $shopifyItem->quantity ?? 0;
            } else {
                $processedItem['INV'] = 0;
                $processedItem['L30'] = 0;
            }

            $processedData[] = $processedItem;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function saveNineRationVideo(Request $request){

        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $sku = $request->sku;
        $newValue = $request->value;

        $existing = NineRationVideo::where('sku', $sku)->first();
        $mergedValue = $newValue;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $newValue);
        }

        $videoPosted = NineRationVideo::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        // === Save to FacebookFeedAd ===
        FacebookReelAd::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        // === Save to InstagramFeedAd ===
        InstagramReelAd::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        // == Save to Tiktok Video Ad
        TiktokVideoAd::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        // == Save to Youtube Video Ad
        YoutubeShortsAd::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        return response()->json([
            'success' => true,
            'data' => $videoPosted
        ]);
    }

    public function importNineRationVideo(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt',
        ]);

        try {
            $file = $request->file('file');
            $fileExtension = strtolower($file->getClientOriginalExtension());
            
            $rows = [];
            
            // Handle CSV files
            if ($fileExtension === 'csv' || $fileExtension === 'txt') {
                $fileContent = file($file->getRealPath());
                
                if (empty($fileContent)) {
                    return response()->json(['success' => false, 'error' => 'CSV file is empty or invalid'], 400);
                }
                
                // Detect delimiter (comma or tab)
                $firstLine = $fileContent[0];
                $delimiter = (strpos($firstLine, "\t") !== false) ? "\t" : ",";
                
                // Parse CSV with detected delimiter
                $rows = array_map(function($line) use ($delimiter) {
                    return str_getcsv($line, $delimiter);
                }, $fileContent);
                
            } else {
                // Handle Excel files
                $spreadsheet = IOFactory::load($file->getPathName());
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();
                
                // Filter out completely empty rows and re-index
                $rows = array_values(array_filter($rows, function($row) {
                    return !empty(array_filter($row, function($cell) {
                        return !empty(trim($cell ?? ''));
                    }));
                }));
            }

            if (empty($rows)) {
                return response()->json(['success' => false, 'error' => 'File is empty'], 400);
            }

            // Clean headers (remove BOM if present)
            $header = array_map(function ($h) {
                return trim(preg_replace('/^\xEF\xBB\xBF/', '', $h ?? ''));
            }, $rows[0]);

            unset($rows[0]);
            $rows = array_values($rows); // Re-index after removing header

            // Normalize header keys to lowercase
            $headerMap = [];
            foreach ($header as $index => $h) {
                $normalized = strtolower(trim($h));
                // Support multiple possible column names
                if ($normalized === 'sku') {
                    $headerMap['sku'] = $index;
                } elseif (in_array($normalized, ['nine_ratio_link', '9:16_video_link', '9:16 video link', '9_16_video_link']) || 
                         strpos($normalized, '9:16') !== false || strpos($normalized, 'video_link') !== false) {
                    $headerMap['nine_ratio_link'] = $index;
                } elseif (in_array($normalized, ['remark', 'remarks', 'note', 'notes'])) {
                    $headerMap['remark'] = $index;
                }
            }

            if (!isset($headerMap['sku'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'SKU column is required. Please ensure your file has a "sku" column header. Found headers: ' . implode(', ', $header)
                ], 422);
            }

            $importedCount = 0;
            $skippedCount = 0;
            $errors = [];

            foreach ($rows as $rowIndex => $row) {
                // Skip completely empty rows
                if (empty(array_filter($row, function($cell) {
                    return !empty(trim($cell ?? ''));
                }))) {
                    continue;
                }

                // Ensure row has enough columns
                $sku = '';
                $nine_ratio_link = '';
                $remark = '';
                
                if (isset($headerMap['sku']) && isset($row[$headerMap['sku']])) {
                    $sku = trim($row[$headerMap['sku']] ?? '');
                }
                
                if (isset($headerMap['nine_ratio_link']) && isset($row[$headerMap['nine_ratio_link']])) {
                    $nine_ratio_link = trim($row[$headerMap['nine_ratio_link']] ?? '');
                }
                
                if (isset($headerMap['remark']) && isset($row[$headerMap['remark']])) {
                    $remark = trim($row[$headerMap['remark']] ?? '');
                }

                if (empty($sku)) {
                    $skippedCount++;
                    continue;
                }

                // Check if SKU exists in product_masters
                $productMaster = ProductMaster::where('sku', $sku)->first();
                if (!$productMaster) {
                    $skippedCount++;
                    $errors[] = "Row " . ($rowIndex + 2) . ": SKU '$sku' not found in product master";
                    continue;
                }

                // Get existing record
                $existing = NineRationVideo::where('sku', $sku)->first();
                $existingValue = [];
                
                if ($existing) {
                    $existingValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
                    $existingValue = $existingValue ?? [];
                }

                // Update nine_ratio_link and remark (even if empty, we still want to process the row)
                $existingValue['nine_ratio_link'] = $nine_ratio_link;
                $existingValue['remark'] = $remark;

                // Save to NineRationVideo
                NineRationVideo::updateOrCreate(
                    ['sku' => $sku],
                    ['value' => json_encode($existingValue)]
                );

                // Also save to related models (same as saveNineRationVideo)
                $mergedValue = $existingValue;
                
                FacebookReelAd::updateOrCreate(
                    ['sku' => $sku],
                    ['value' => json_encode($mergedValue)]
                );

                InstagramReelAd::updateOrCreate(
                    ['sku' => $sku],
                    ['value' => json_encode($mergedValue)]
                );

                TiktokVideoAd::updateOrCreate(
                    ['sku' => $sku],
                    ['value' => json_encode($mergedValue)]
                );

                YoutubeShortsAd::updateOrCreate(
                    ['sku' => $sku],
                    ['value' => json_encode($mergedValue)]
                );

                $importedCount++;
            }

            $message = "Import completed successfully. Imported: $importedCount, Skipped: $skippedCount";
            if (!empty($errors) && count($errors) <= 10) {
                $message .= "\n\nErrors:\n" . implode("\n", array_slice($errors, 0, 10));
                if (count($errors) > 10) {
                    $message .= "\n... and " . (count($errors) - 10) . " more errors";
                }
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'imported' => $importedCount,
                'skipped' => $skippedCount,
                'errors' => array_slice($errors, 0, 10)
            ]);
        } catch (\Exception $e) {
            Log::error('Import Nine Ration Video Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Import failed: ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')'
            ], 500);
        }
    }

    public function exportNineRationVideo(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="nine_ration_video_' . date('Y-m-d_H-i-s') . '.csv"',
        ];

        $columns = ['sku', 'inv', 'ov_l30', 'dil', '9:16_video_link', 'remark'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');

            // Write header row
            fputcsv($file, $columns);

            // Fetch all product masters with their shopify data
            $productMasters = ProductMaster::all();
            $skus = $productMasters->pluck('sku')->toArray();
            
            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
            $videoPostedValues = NineRationVideo::whereIn('sku', $skus)->get()->keyBy('sku');

            foreach ($productMasters as $productMaster) {
                $sku = $productMaster->sku;
                
                // Get INV and L30 from shopify data
                $inv = 0;
                $l30 = 0;
                if (isset($shopifyData[$sku])) {
                    $inv = $shopifyData[$sku]->inv ?? 0;
                    $l30 = $shopifyData[$sku]->quantity ?? 0;
                }
                
                // Calculate Dil percentage
                $dil = 0;
                if ($inv > 0 && $l30 > 0) {
                    $dil = round(($l30 / $inv) * 100, 2);
                }
                
                // Get video link and remark from nine_ration_video
                $nineRationVideo = isset($videoPostedValues[$sku]) ? $videoPostedValues[$sku] : null;
                $value = [];
                if ($nineRationVideo) {
                    $value = is_array($nineRationVideo->value) 
                        ? $nineRationVideo->value 
                        : json_decode($nineRationVideo->value, true);
                    $value = $value ?? [];
                }

                $row = [
                    'sku' => $sku,
                    'inv' => $inv,
                    'ov_l30' => $l30,
                    'dil' => $dil,
                    '9:16_video_link' => $value['nine_ratio_link'] ?? '',
                    'remark' => $value['remark'] ?? '',
                ];

                fputcsv($file, $row);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }


    public function sixteenRation(){
        return view('marketing-masters.video-required.shoppable-video.sixteen-ration');
    }

    public function getSixteenRatioVideoData(Request $request)
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->pluck('sku')->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $videoPostedValues = SixteenRationVideo::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = [];
        $slNo = 1;

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, 'PARENT') !== false;

            // Default social links
            $sixteen_ratio_link = '';

            // Get social links from video_posted_values table if available
            if (isset($videoPostedValues[$sku])) {
                $value = $videoPostedValues[$sku]->value;
                if (is_string($value)) {
                    $value = json_decode($value, true);
                }
                $sixteen_ratio_link = $value['sixteen_ratio_link'] ?? '';
            }

            // Initialize the data structure
            $processedItem = [
                'SL No.' => $slNo++,
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'is_parent' => $isParent,
            ];

            $processedItem['sixteen_ratio_link'] = $sixteen_ratio_link;


            // Add data from shopify_skus if available
            if (isset($shopifyData[$sku])) {
                $shopifyItem = $shopifyData[$sku];
                $processedItem['INV'] = $shopifyItem->inv ?? 0;
                $processedItem['L30'] = $shopifyItem->quantity ?? 0;
            } else {
                $processedItem['INV'] = 0;
                $processedItem['L30'] = 0;
            }

            $processedData[] = $processedItem;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function saveSixteenRationVideo(Request $request){

        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $sku = $request->sku;
        $newValue = $request->value;

        $existing = SixteenRationVideo::where('sku', $sku)->first();
        $mergedValue = $newValue;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $newValue);
        }

        $videoPosted = SixteenRationVideo::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        // == Save to Youtube Video Ad
        YoutubeVideoAd::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        return response()->json([
            'success' => true,
            'data' => $videoPosted
        ]);
    }

    public function importSixteenRationVideo(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt',
        ]);

        try {
            $file = $request->file('file');
            $fileExtension = strtolower($file->getClientOriginalExtension());
            
            $rows = [];
            
            // Handle CSV files
            if ($fileExtension === 'csv' || $fileExtension === 'txt') {
                $fileContent = file($file->getRealPath());
                
                if (empty($fileContent)) {
                    return response()->json(['success' => false, 'error' => 'CSV file is empty or invalid'], 400);
                }
                
                // Detect delimiter (comma or tab)
                $firstLine = $fileContent[0];
                $delimiter = (strpos($firstLine, "\t") !== false) ? "\t" : ",";
                
                // Parse CSV with detected delimiter
                $rows = array_map(function($line) use ($delimiter) {
                    return str_getcsv($line, $delimiter);
                }, $fileContent);
                
            } else {
                // Handle Excel files
                $spreadsheet = IOFactory::load($file->getPathName());
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();
                
                // Filter out completely empty rows and re-index
                $rows = array_values(array_filter($rows, function($row) {
                    return !empty(array_filter($row, function($cell) {
                        return !empty(trim($cell ?? ''));
                    }));
                }));
            }

            if (empty($rows)) {
                return response()->json(['success' => false, 'error' => 'File is empty'], 400);
            }

            // Clean headers (remove BOM if present)
            $header = array_map(function ($h) {
                return trim(preg_replace('/^\xEF\xBB\xBF/', '', $h ?? ''));
            }, $rows[0]);

            unset($rows[0]);
            $rows = array_values($rows); // Re-index after removing header

            // Normalize header keys to lowercase
            $headerMap = [];
            foreach ($header as $index => $h) {
                $normalized = strtolower(trim($h));
                if (in_array($normalized, ['sku', 'sixteen_ratio_link'])) {
                    $headerMap[$normalized] = $index;
                }
            }

            if (!isset($headerMap['sku'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'SKU column is required. Please ensure your file has a "sku" column header. Found headers: ' . implode(', ', $header)
                ], 422);
            }

            $importedCount = 0;
            $skippedCount = 0;
            $errors = [];

            foreach ($rows as $rowIndex => $row) {
                // Skip completely empty rows
                if (empty(array_filter($row, function($cell) {
                    return !empty(trim($cell ?? ''));
                }))) {
                    continue;
                }

                // Ensure row has enough columns
                $sku = '';
                $sixteen_ratio_link = '';
                
                if (isset($headerMap['sku']) && isset($row[$headerMap['sku']])) {
                    $sku = trim($row[$headerMap['sku']] ?? '');
                }
                
                if (isset($headerMap['sixteen_ratio_link']) && isset($row[$headerMap['sixteen_ratio_link']])) {
                    $sixteen_ratio_link = trim($row[$headerMap['sixteen_ratio_link']] ?? '');
                }

                if (empty($sku)) {
                    $skippedCount++;
                    continue;
                }

                // Check if SKU exists in product_masters
                $productMaster = ProductMaster::where('sku', $sku)->first();
                if (!$productMaster) {
                    $skippedCount++;
                    $errors[] = "Row " . ($rowIndex + 2) . ": SKU '$sku' not found in product master";
                    continue;
                }

                // Get existing record
                $existing = SixteenRationVideo::where('sku', $sku)->first();
                $existingValue = [];
                
                if ($existing) {
                    $existingValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
                    $existingValue = $existingValue ?? [];
                }

                // Update sixteen_ratio_link (even if empty, we still want to process the row)
                $existingValue['sixteen_ratio_link'] = $sixteen_ratio_link;

                // Save to SixteenRationVideo
                SixteenRationVideo::updateOrCreate(
                    ['sku' => $sku],
                    ['value' => json_encode($existingValue)]
                );

                // Also save to related models (same as saveSixteenRationVideo)
                $mergedValue = $existingValue;
                
                YoutubeVideoAd::updateOrCreate(
                    ['sku' => $sku],
                    ['value' => json_encode($mergedValue)]
                );

                $importedCount++;
            }

            $message = "Import completed successfully. Imported: $importedCount, Skipped: $skippedCount";
            if (!empty($errors) && count($errors) <= 10) {
                $message .= "\n\nErrors:\n" . implode("\n", array_slice($errors, 0, 10));
                if (count($errors) > 10) {
                    $message .= "\n... and " . (count($errors) - 10) . " more errors";
                }
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'imported' => $importedCount,
                'skipped' => $skippedCount,
                'errors' => array_slice($errors, 0, 10)
            ]);
        } catch (\Exception $e) {
            Log::error('Import Sixteen Ration Video Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Import failed: ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')'
            ], 500);
        }
    }

    public function exportSixteenRationVideo(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sixteen_ration_video_' . date('Y-m-d_H-i-s') . '.csv"',
        ];

        $columns = ['sku', 'sixteen_ratio_link'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');

            // Write header row
            fputcsv($file, $columns);

            // Fetch all SKUs from product master
            $productMasters = ProductMaster::pluck('sku');

            foreach ($productMasters as $sku) {
                $sixteenRationVideo = SixteenRationVideo::where('sku', $sku)->first();
                
                $value = [];
                if ($sixteenRationVideo) {
                    $value = is_array($sixteenRationVideo->value) 
                        ? $sixteenRationVideo->value 
                        : json_decode($sixteenRationVideo->value, true);
                    $value = $value ?? [];
                }

                $row = [
                    'sku' => $sku,
                    'sixteen_ratio_link' => $value['sixteen_ratio_link'] ?? '',
                ];

                fputcsv($file, $row);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
