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

            // Get social links from video_posted_values table if available
            if (isset($videoPostedValues[$sku])) {
                $value = $videoPostedValues[$sku]->value;
                if (is_string($value)) {
                    $value = json_decode($value, true);
                }
                $nine_ratio_link = $value['nine_ratio_link'] ?? '';
            }

            // Initialize the data structure
            $processedItem = [
                'SL No.' => $slNo++,
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'is_parent' => $isParent,
            ];

            $processedItem['nine_ratio_link'] = $nine_ratio_link;


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
}
