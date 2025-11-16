<?php

namespace App\Http\Controllers\MarketingMaster;

use App\Http\Controllers\Controller;
use App\Models\FacebookFeedAd;
use App\Models\FacebookReelAd;
use App\Models\FacebookVideoAd;
use App\Models\InstagramFeedAd;
use App\Models\InstagramReelAd;
use App\Models\InstagramVideoAd;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\TiktokVideoAd;
use App\Models\YoutubeShortsAd;
use App\Models\YoutubeVideoAd;
use Illuminate\Http\Request;

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
        $videoPostedValues = FacebookVideoAd::whereIn('sku', $skus)->get()->keyBy('sku');

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
