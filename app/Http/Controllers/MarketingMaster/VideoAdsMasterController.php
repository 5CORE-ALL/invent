<?php

namespace App\Http\Controllers\MarketingMaster;

use App\Http\Controllers\Controller;
use App\Models\FacebookFeedAd;
use App\Models\FacebookReelAd;
use App\Models\FacebookVideoAd;
use App\Models\FacebookVideoAdGroup;
use App\Models\FacebookVideoAdCategory;
use App\Models\InstagramFeedAd;
use App\Models\InstagramReelAd;
use App\Models\InstagramVideoAd;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\TiktokVideoAd;
use App\Models\YoutubeShortsAd;
use App\Models\YoutubeVideoAd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class VideoAdsMasterController extends Controller
{
    public function tiktokIndex()
    {
        return view('marketing-masters.video-ads-master.tiktok-video-ad');
    }

    public function getTikTokVideoAdsData()
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->keys()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $videoPostedValues = TiktokVideoAd::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = [];
        $slNo = 1;

        foreach ($videoPostedValues as $sku => $videoData) {
            if (!isset($productMasterRows[$sku])) {
                continue;
            }

            $productMaster = $productMasterRows[$sku];
            $isParent = stripos($sku, 'PARENT') !== false;

            $value = is_string($videoData->value) ? json_decode($videoData->value, true) : $videoData->value;

            $processedItem = [
                'SL No.' => $slNo++,
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'is_parent' => $isParent,
                'nine_ratio_link' => $value['nine_ratio_link'] ?? '',
                'posted' => $value['posted'] ?? '',
                'ad_req' => $value['ad_req'] ?? '',
                'ads' => $value['ads'] ?? '',

            ];

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
            'message' => 'Video data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function saveTiktokVideoAds(Request $request){

        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $sku = $request->sku;
        $newValue = $request->value;

        $existing = TiktokVideoAd::where('sku', $sku)->first();
        $mergedValue = $newValue;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $newValue);
        }

        $videoPosted = TiktokVideoAd::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        return response()->json([
            'success' => true,
            'data' => $videoPosted
        ]);
    }

    //facebook ads start
    public function facebookVideoAdView(){
        return view('marketing-masters.video-ads-master.facebook-video-ad');
    }

    public function getFacebookVideoAdsData()
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->keys()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $videoPostedValues = FacebookVideoAd::with(['group', 'category'])->whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = [];
        $slNo = 1;

        foreach ($videoPostedValues as $sku => $videoData) {
            if (!isset($productMasterRows[$sku])) {
                continue;
            }

            $productMaster = $productMasterRows[$sku];
            $isParent = stripos($sku, 'PARENT') !== false;

            $value = is_string($videoData->value) ? json_decode($videoData->value, true) : $videoData->value;

            $processedItem = [
                'id' => $videoData->id,
                'SL No.' => $slNo++,
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'is_parent' => $isParent,
                'group_id' => $videoData->group_id,
                'group' => $videoData->group ? $videoData->group->group_name : null,
                'category_id' => $videoData->category_id,
                'category' => $videoData->category ? $videoData->category->category_name : null,
                'four_ratio_link' => $value['four_ratio_link'] ?? '',
                'posted' => $value['posted'] ?? '',
                'ad_req' => $value['ad_req'] ?? '',
                'ads' => $value['ads'] ?? '',

            ];

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
            'message' => 'Video data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function saveFacebookVideoAds(Request $request){

        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $sku = $request->sku;
        $newValue = $request->value;

        $existing = FacebookVideoAd::where('sku', $sku)->first();
        $mergedValue = $newValue;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $newValue);
        }

        $videoPosted = FacebookVideoAd::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        return response()->json([
            'success' => true,
            'data' => $videoPosted
        ]);
    }

    // Facebook Video Ads Groups and Categories Management
    public function getFacebookVideoAdGroups()
    {
        try {
            $groups = FacebookVideoAdGroup::where('status', 'active')
                ->whereNull('deleted_at')
                ->orderBy('group_name', 'asc')
                ->get(['id', 'group_name', 'description']);

            return response()->json([
                'success' => true,
                'groups' => $groups
            ]);
        } catch (\Exception $e) {
            Log::error('Get Facebook video ad groups error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch groups',
                'groups' => []
            ], 500);
        }
    }

    public function getFacebookVideoAdCategories()
    {
        try {
            $categories = FacebookVideoAdCategory::where('status', 'active')
                ->whereNull('deleted_at')
                ->orderBy('category_name', 'asc')
                ->get(['id', 'category_name', 'code', 'description']);

            return response()->json([
                'success' => true,
                'categories' => $categories
            ]);
        } catch (\Exception $e) {
            Log::error('Get Facebook video ad categories error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories',
                'categories' => []
            ], 500);
        }
    }

    public function storeFacebookVideoAdGroup(Request $request)
    {
        try {
            $request->validate([
                'group_name' => 'required|string|max:191|unique:facebook_video_ad_groups,group_name',
                'description' => 'nullable|string',
                'status' => 'nullable|string|in:active,inactive'
            ]);

            $group = FacebookVideoAdGroup::create([
                'group_name' => trim($request->group_name),
                'description' => $request->description,
                'status' => $request->status ?? 'active'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Group created successfully',
                'group' => $group
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Store Facebook video ad group error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create group: ' . $e->getMessage()
            ], 500);
        }
    }

    public function storeFacebookVideoAdCategory(Request $request)
    {
        try {
            $request->validate([
                'category_name' => 'required|string|max:191|unique:facebook_video_ad_categories,category_name',
                'code' => 'nullable|string|max:191|unique:facebook_video_ad_categories,code',
                'description' => 'nullable|string',
                'status' => 'nullable|string|in:active,inactive'
            ]);

            $category = FacebookVideoAdCategory::create([
                'category_name' => trim($request->category_name),
                'code' => $request->code ? trim($request->code) : null,
                'description' => $request->description,
                'status' => $request->status ?? 'active'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Category created successfully',
                'category' => $category
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Store Facebook video ad category error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create category: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateFacebookVideoAdField(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'sku' => 'required|string',
                'field' => 'required|string|in:group_id,category_id',
                'value' => 'nullable|integer'
            ]);

            $videoAd = FacebookVideoAd::find($request->id);
            
            if (!$videoAd) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facebook video ad not found.'
                ], 404);
            }

            if ($videoAd->sku !== $request->sku) {
                return response()->json([
                    'success' => false,
                    'message' => 'SKU mismatch.'
                ], 400);
            }

            $field = $request->field;
            $value = $request->value ? (int)$request->value : null;

            if ($value !== null) {
                if ($field === 'group_id') {
                    $group = FacebookVideoAdGroup::withTrashed()->find($value);
                    if (!$group || $group->trashed() || $group->status !== 'active') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Selected group does not exist or is inactive.'
                        ], 400);
                    }
                } elseif ($field === 'category_id') {
                    $category = FacebookVideoAdCategory::withTrashed()->find($value);
                    if (!$category || $category->trashed() || $category->status !== 'active') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Selected category does not exist or is inactive.'
                        ], 400);
                    }
                }
            }

            $videoAd->$field = $value;
            $videoAd->save();
            $videoAd->load(['group', 'category']);

            return response()->json([
                'success' => true,
                'message' => ucfirst(str_replace('_id', '', $field)) . ' updated successfully.',
                'data' => [
                    'id' => $videoAd->id,
                    'sku' => $videoAd->sku,
                    $field => $videoAd->$field,
                    'group_name' => $videoAd->group ? $videoAd->group->group_name : null,
                    'category_name' => $videoAd->category ? $videoAd->category->category_name : null,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Update Facebook video ad field error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update field: ' . $e->getMessage()
            ], 500);
        }
    }

    public function uploadFacebookVideoAdsExcel(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|mimes:xlsx,xls|max:10240'
            ]);

            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (count($rows) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Excel file is empty or has no data rows.'
                ], 422);
            }

            $headers = array_map('strtolower', array_map('trim', $rows[0]));
            $columnIndices = [];

            foreach (['sku', 'group_id', 'category_id', 'four_ratio_link', 'posted', 'ad_req', 'ads'] as $field) {
                $index = array_search($field, $headers);
                if ($index !== false) {
                    $columnIndices[$field] = $index;
                }
            }

            if (!isset($columnIndices['sku'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'SKU column not found in the Excel file'
                ], 422);
            }

            $imported = 0;
            $updated = 0;
            $errors = [];

            foreach (array_slice($rows, 1) as $index => $row) {
                $rowNumber = $index + 2;
                
                if (empty($row[$columnIndices['sku']])) {
                    continue;
                }

                $sku = trim($row[$columnIndices['sku']]);
                $videoAd = FacebookVideoAd::where('sku', $sku)->first();
                
                if (!$videoAd) {
                    $errors[] = "Row {$rowNumber}: SKU '{$sku}' not found in database";
                    continue;
                }

                $value = is_array($videoAd->value) ? $videoAd->value : json_decode($videoAd->value, true);
                if (!is_array($value)) {
                    $value = [];
                }

                // Update group_id if provided
                if (isset($columnIndices['group_id']) && isset($row[$columnIndices['group_id']])) {
                    $groupValue = trim($row[$columnIndices['group_id']]);
                    if ($groupValue !== '') {
                        $group = FacebookVideoAdGroup::where('group_name', $groupValue)
                            ->orWhere('id', $groupValue)
                            ->first();
                        if ($group) {
                            $videoAd->group_id = $group->id;
                        } else {
                            $errors[] = "Row {$rowNumber}: Group '{$groupValue}' not found";
                        }
                    }
                }

                // Update category_id if provided
                if (isset($columnIndices['category_id']) && isset($row[$columnIndices['category_id']])) {
                    $categoryValue = trim($row[$columnIndices['category_id']]);
                    if ($categoryValue !== '') {
                        $category = FacebookVideoAdCategory::where('category_name', $categoryValue)
                            ->orWhere('id', $categoryValue)
                            ->first();
                        if ($category) {
                            $videoAd->category_id = $category->id;
                        } else {
                            $errors[] = "Row {$rowNumber}: Category '{$categoryValue}' not found";
                        }
                    }
                }

                // Update value fields
                foreach (['four_ratio_link', 'posted', 'ad_req', 'ads'] as $field) {
                    if (isset($columnIndices[$field]) && isset($row[$columnIndices[$field]])) {
                        $cellValue = trim($row[$columnIndices[$field]]);
                        if ($cellValue !== '') {
                            $value[$field] = $cellValue;
                        }
                    }
                }

                $videoAd->value = json_encode($value);
                $videoAd->save();
                $updated++;
            }

            return response()->json([
                'success' => true,
                'message' => "Excel file processed. Updated: {$updated}, Errors: " . count($errors),
                'updated' => $updated,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            Log::error('Upload Facebook video ads Excel error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload Excel file: ' . $e->getMessage()
            ], 500);
        }
    }

    public function downloadFacebookVideoAdsExcel()
    {
        try {
            $videoAds = FacebookVideoAd::with(['group', 'category'])->get();
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = ['ID', 'SKU', 'Group', 'Category', '4:5 Video', 'Posted', 'Ad Req', 'AD'];
            $sheet->fromArray([$headers], null, 'A1');

            $row = 2;
            foreach ($videoAds as $ad) {
                $value = is_array($ad->value) ? $ad->value : json_decode($ad->value, true);
                $sheet->setCellValue('A' . $row, $ad->id);
                $sheet->setCellValue('B' . $row, $ad->sku);
                $sheet->setCellValue('C' . $row, $ad->group ? $ad->group->group_name : '');
                $sheet->setCellValue('D' . $row, $ad->category ? $ad->category->category_name : '');
                $sheet->setCellValue('E' . $row, $value['four_ratio_link'] ?? '');
                $sheet->setCellValue('F' . $row, $value['posted'] ?? '');
                $sheet->setCellValue('G' . $row, $value['ad_req'] ?? '');
                $sheet->setCellValue('H' . $row, $value['ads'] ?? '');
                $row++;
            }

            $writer = new Xlsx($spreadsheet);
            $filename = 'facebook_video_ads_export_' . date('Y-m-d_His') . '.xlsx';
            $filepath = storage_path('app/temp/' . $filename);
            
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }
            
            $writer->save($filepath);

            return response()->download($filepath, $filename)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Download Facebook video ads Excel error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate Excel file: ' . $e->getMessage()
            ], 500);
        }
    }

    public function facebookFeedAdView(){
        return view('marketing-masters.video-ads-master.facebook-feed');
    }

    public function getFacebookFeedAdsData()
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->keys()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $videoPostedValues = FacebookFeedAd::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = [];
        $slNo = 1;

        foreach ($videoPostedValues as $sku => $videoData) {
            if (!isset($productMasterRows[$sku])) {
                continue;
            }

            $productMaster = $productMasterRows[$sku];
            $isParent = stripos($sku, 'PARENT') !== false;

            $value = is_string($videoData->value) ? json_decode($videoData->value, true) : $videoData->value;

            $processedItem = [
                'SL No.' => $slNo++,
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'is_parent' => $isParent,
                'oneratio_link' => $value['oneratio_link'] ?? '',
                'posted' => $value['posted'] ?? '',
                'ad_req' => $value['ad_req'] ?? '',
                'ads' => $value['ads'] ?? '',

            ];

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
            'message' => 'Video data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function saveFacebookFeedAds(Request $request){

        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $sku = $request->sku;
        $newValue = $request->value;

        $existing = FacebookFeedAd::where('sku', $sku)->first();
        $mergedValue = $newValue;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $newValue);
        }

        $videoPosted = FacebookFeedAd::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        return response()->json([
            'success' => true,
            'data' => $videoPosted
        ]);
    }
    
    public function facebookReelAdView(){
        return view('marketing-masters.video-ads-master.facebook-reel');
    }

    public function getFacebookReelAdsData()
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->keys()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $videoPostedValues = FacebookReelAd::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = [];
        $slNo = 1;

        foreach ($videoPostedValues as $sku => $videoData) {
            if (!isset($productMasterRows[$sku])) {
                continue;
            }

            $productMaster = $productMasterRows[$sku];
            $isParent = stripos($sku, 'PARENT') !== false;

            $value = is_string($videoData->value) ? json_decode($videoData->value, true) : $videoData->value;

            $processedItem = [
                'SL No.' => $slNo++,
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'is_parent' => $isParent,
                'nine_ratio_link' => $value['nine_ratio_link'] ?? '',
                'posted' => $value['posted'] ?? '',
                'ad_req' => $value['ad_req'] ?? '',
                'ads' => $value['ads'] ?? '',

            ];

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
            'message' => 'Video data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function saveFacebookReelAds(Request $request){

        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $sku = $request->sku;
        $newValue = $request->value;

        $existing = FacebookReelAd::where('sku', $sku)->first();
        $mergedValue = $newValue;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $newValue);
        }

        $videoPosted = FacebookReelAd::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        return response()->json([
            'success' => true,
            'data' => $videoPosted
        ]);
    }


    //facebook ads end

    //instagram ads start
    public function instagramVideoAdView(){
        return view('marketing-masters.video-ads-master.instagram-video-ad');
    }

    public function getInstagramVideoAdsData()
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->keys()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $videoPostedValues = InstagramVideoAd::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = [];
        $slNo = 1;

        foreach ($videoPostedValues as $sku => $videoData) {
            if (!isset($productMasterRows[$sku])) {
                continue;
            }

            $productMaster = $productMasterRows[$sku];
            $isParent = stripos($sku, 'PARENT') !== false;

            $value = is_string($videoData->value) ? json_decode($videoData->value, true) : $videoData->value;

            $processedItem = [
                'SL No.' => $slNo++,
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'is_parent' => $isParent,
                'four_ratio_link' => $value['four_ratio_link'] ?? '',
                'posted' => $value['posted'] ?? '',
                'ad_req' => $value['ad_req'] ?? '',
                'ads' => $value['ads'] ?? '',

            ];

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
            'message' => 'Video data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function saveInstagramVideoAds(Request $request){

        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $sku = $request->sku;
        $newValue = $request->value;

        $existing = InstagramVideoAd::where('sku', $sku)->first();
        $mergedValue = $newValue;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $newValue);
        }

        $videoPosted = InstagramVideoAd::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        return response()->json([
            'success' => true,
            'data' => $videoPosted
        ]);
    }

    public function instagramFeedAdView(){
        return view('marketing-masters.video-ads-master.instagram-feed');
    }

    public function getInstagramFeedAdsData()
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->keys()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $videoPostedValues = InstagramFeedAd::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = [];
        $slNo = 1;

        foreach ($videoPostedValues as $sku => $videoData) {
            if (!isset($productMasterRows[$sku])) {
                continue;
            }

            $productMaster = $productMasterRows[$sku];
            $isParent = stripos($sku, 'PARENT') !== false;

            $value = is_string($videoData->value) ? json_decode($videoData->value, true) : $videoData->value;

            $processedItem = [
                'SL No.' => $slNo++,
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'is_parent' => $isParent,
                'oneratio_link' => $value['oneratio_link'] ?? '',
                'posted' => $value['posted'] ?? '',
                'ad_req' => $value['ad_req'] ?? '',
                'ads' => $value['ads'] ?? '',

            ];

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
            'message' => 'Video data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function saveInstagramFeedAds(Request $request){

        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $sku = $request->sku;
        $newValue = $request->value;

        $existing = InstagramFeedAd::where('sku', $sku)->first();
        $mergedValue = $newValue;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $newValue);
        }

        $videoPosted = InstagramFeedAd::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        return response()->json([
            'success' => true,
            'data' => $videoPosted
        ]);
    }

    public function instagramReelAdView(){
        return view('marketing-masters.video-ads-master.instagram-reel');
    }

    public function getInstagramReelAdsData()
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->keys()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $videoPostedValues = InstagramReelAd::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = [];
        $slNo = 1;

        foreach ($videoPostedValues as $sku => $videoData) {
            if (!isset($productMasterRows[$sku])) {
                continue;
            }

            $productMaster = $productMasterRows[$sku];
            $isParent = stripos($sku, 'PARENT') !== false;

            $value = is_string($videoData->value) ? json_decode($videoData->value, true) : $videoData->value;

            $processedItem = [
                'SL No.' => $slNo++,
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'is_parent' => $isParent,
                'nine_ratio_link' => $value['nine_ratio_link'] ?? '',
                'posted' => $value['posted'] ?? '',
                'ad_req' => $value['ad_req'] ?? '',
                'ads' => $value['ads'] ?? '',

            ];

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
            'message' => 'Video data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function saveInstagramReelAds(Request $request){

        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $sku = $request->sku;
        $newValue = $request->value;

        $existing = InstagramReelAd::where('sku', $sku)->first();
        $mergedValue = $newValue;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $newValue);
        }

        $videoPosted = InstagramReelAd::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        return response()->json([
            'success' => true,
            'data' => $videoPosted
        ]);
    }


    //instagram ads end

    //youtube ads start
    public function youtubeVideoAdView(){
        return view('marketing-masters.video-ads-master.youtube-video-ad');
    }

    public function getYoutubeVideoAdsData()
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->keys()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $videoPostedValues = YoutubeVideoAd::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = [];
        $slNo = 1;

        foreach ($videoPostedValues as $sku => $videoData) {
            if (!isset($productMasterRows[$sku])) {
                continue;
            }

            $productMaster = $productMasterRows[$sku];
            $isParent = stripos($sku, 'PARENT') !== false;

            $value = is_string($videoData->value) ? json_decode($videoData->value, true) : $videoData->value;

            $processedItem = [
                'SL No.' => $slNo++,
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'is_parent' => $isParent,
                'sixteen_ratio_link' => $value['sixteen_ratio_link'] ?? '',
                'posted' => $value['posted'] ?? '',
                'ad_req' => $value['ad_req'] ?? '',
                'ads' => $value['ads'] ?? '',

            ];

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
            'message' => 'Video data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function saveYoutubeVideoAds(Request $request){

        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $sku = $request->sku;
        $newValue = $request->value;

        $existing = YoutubeVideoAd::where('sku', $sku)->first();
        $mergedValue = $newValue;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $newValue);
        }

        $videoPosted = YoutubeVideoAd::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        return response()->json([
            'success' => true,
            'data' => $videoPosted
        ]);
    }

    public function youtubeShortsAdView(){
        return view('marketing-masters.video-ads-master.youtube-shorts');
    }

    public function getYoutubeShortsAdsData()
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->keys()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $videoPostedValues = YoutubeShortsAd::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = [];
        $slNo = 1;

        foreach ($videoPostedValues as $sku => $videoData) {
            if (!isset($productMasterRows[$sku])) {
                continue;
            }

            $productMaster = $productMasterRows[$sku];
            $isParent = stripos($sku, 'PARENT') !== false;

            $value = is_string($videoData->value) ? json_decode($videoData->value, true) : $videoData->value;

            $processedItem = [
                'SL No.' => $slNo++,
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'is_parent' => $isParent,
                'nine_ratio_link' => $value['nine_ratio_link'] ?? '',
                'posted' => $value['posted'] ?? '',
                'ad_req' => $value['ad_req'] ?? '',
                'ads' => $value['ads'] ?? '',

            ];

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
            'message' => 'Video data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function saveYoutubeShortsAds(Request $request){

        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $sku = $request->sku;
        $newValue = $request->value;

        $existing = YoutubeShortsAd::where('sku', $sku)->first();
        $mergedValue = $newValue;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $newValue);
        }

        $videoPosted = YoutubeShortsAd::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        return response()->json([
            'success' => true,
            'data' => $videoPosted
        ]);
    }


    //youtube ads end

    // traffic start

    public function getTrafficDropship(Request $request)
    {
        return view('marketing-masters.traffic_to_webpages.dropship');
    }

    public function getTrafficCaraudio(Request $request)
    {
        return view('marketing-masters.traffic_to_webpages.caraudio');
    }

    public function getTrafficMusicInst(Request $request)
    {
        return view('marketing-masters.traffic_to_webpages.musicinst');
    }

    public function getTrafficRepaire(Request $request)
    {
        return view('marketing-masters.traffic_to_webpages.repaire');
    }

    public function getTrafficMusicSchool(Request $request)
    {
        return view('marketing-masters.traffic_to_webpages.musicschool');
    }


    // traffic ends

}
