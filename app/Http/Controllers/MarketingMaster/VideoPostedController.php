<?php

namespace App\Http\Controllers\MarketingMaster;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\VideoPosted;
use App\Models\AssemblyVideo;
use App\Models\ThreeDVideo;
use App\Models\Video360;
use App\Models\BenefitVideo;
use App\Models\DiyVideo;
use App\Models\ShoppableVideo;
use App\Models\ProductVideoUpload;
use App\Models\AssemblyVideoUpload;
use App\Models\ThreeDVideoUpload;
use App\Models\Video360Upload;
use App\Models\BenefitVideoUpload;
use App\Models\DiyVideoUpload;
use App\Models\TiktokVideoAd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class VideoPostedController extends Controller
{
    //product video list
    public function videoPostedView(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        return view('marketing-masters.video-required.product-video', [
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getViewVideoPostedData(Request $request)
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->pluck('sku')->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $videoPostedValues = VideoPosted::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = [];
        $slNo = 1;

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, 'PARENT') !== false;

            // Default social links
            $category = '';
            $new = false;
            $bdc = false;
            $nr = false;    
            $avl = false;
            $approved = false;
            $assigned = '';
            $productVideo = '';
            $remarks = '';

            // Get social links from video_posted_values table if available
            if (isset($videoPostedValues[$sku])) {
                $value = $videoPostedValues[$sku]->value;
                if (is_string($value)) {
                    $value = json_decode($value, true);
                }
                $category = $value['Category'] ?? '';
                $new = $value['New'] ?? false;
                $bdc = $value['2BDC'] ?? false;
                $nr = $value['NR'] ?? false;
                $avl = $value['Avl'] ?? false;
                $approved = $value['Approved'] ?? false;
                $assigned = $value['Assigned'] ?? '';
                $productVideo = $value['ProductVideo'] ?? '';
                $remarks = $value['Remarks'] ?? '';
            }

            // Initialize the data structure
            $processedItem = [
                'SL No.' => $slNo++,
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'is_parent' => $isParent,
                'raw_data' => [
                    'parent' => $productMaster->parent,
                    'sku' => $sku,
                    'Values' => $productMaster->Values
                ]
            ];

            $processedItem['Category'] = $category;
            $processedItem['New'] = $new;
            $processedItem['2BDC'] = $bdc;
            $processedItem['NR'] = $nr;
            $processedItem['Avl'] = $avl;
            $processedItem['Approved'] = $approved;
            $processedItem['Assigned'] = $assigned;
            $processedItem['ProductVideo'] = $productVideo;
            $processedItem['Remarks'] = $remarks;


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

    public function storeOrUpdate(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $sku = $request->sku;
        $newValue = $request->value;

        $existing = VideoPosted::where('sku', $sku)->first();
        $mergedValue = $newValue;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $newValue);
        }

        $videoPosted = VideoPosted::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        if (
            isset($mergedValue['Approved']) && $mergedValue['Approved'] == true &&
            isset($mergedValue['ProductVideo']) && !empty($mergedValue['ProductVideo'])
        ) {
            ProductVideoUpload::updateOrCreate(
                ['sku' => $sku],
                [
                    'sku' => $sku,
                    'value' => json_encode([
                        'VideoLink' => $mergedValue['ProductVideo']
                    ])
                ]
            );
        }

        return response()->json([
            'success' => true,
            'data' => $videoPosted,
            'message' => 'Video posted value saved successfully.'
        ]);
    }

    public function productVideoUploadView(){
        return view('marketing-masters.video-required.product-video-upload');
    }

    public function getProductVideoUploadData()
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->keys()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $videoPostedValues = ProductVideoUpload::whereIn('sku', $skus)->get()->keyBy('sku');

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
                'VideoLink' => $value['VideoLink'] ?? '',
                'Amazon' => $value['Amazon'] ?? '',
                'Doba' => $value['Doba'] ?? '',
                'eBay' => $value['eBay'] ?? '',
                'Temu' => $value['Temu'] ?? '',
                'Macys' => $value['Macys'] ?? '',
                'Wayfair' => $value['Wayfair'] ?? '',
                'Reverb' => $value['Reverb'] ?? '',
                'ShopifyB2C' => $value['ShopifyB2C'] ?? '',
                'Aliexpress' => $value['Aliexpress'] ?? '',
                'eBayVariation' => $value['eBayVariation'] ?? '',
                'ShopifyWholesale' => $value['ShopifyWholesale'] ?? '',
                'eBay2' => $value['eBay2'] ?? '',
                'Faire' => $value['Faire'] ?? '',
                'TiktokShop' => $value['TiktokShop'] ?? '',
                'MercariWShip' => $value['MercariWShip'] ?? '',
                'FBMarketplace' => $value['FBMarketplace'] ?? '',
                'Business5Core' => $value['Business5Core'] ?? '',
                'NeweggB2C' => $value['NeweggB2C'] ?? '',
                'PLS' => $value['PLS'] ?? '',
                'AutoDS' => $value['AutoDS'] ?? '',
                'MercariWOShip' => $value['MercariWOShip'] ?? '',
                'Poshmark' => $value['Poshmark'] ?? '',
                'Tiendamia' => $value['Tiendamia'] ?? '',
                'Shein' => $value['Shein'] ?? '',
                'Spocket' => $value['Spocket'] ?? '',
                'Zendrop' => $value['Zendrop'] ?? '',
                'Syncee' => $value['Syncee'] ?? '',
                'NeweggB2B' => $value['NeweggB2B'] ?? '',
                'Appscenic' => $value['Appscenic'] ?? '',
                'FBShop' => $value['FBShop'] ?? '',
                'InstagramShop' => $value['InstagramShop'] ?? '',
                'AmazonFBA' => $value['AmazonFBA'] ?? '',
                'Walmart' => $value['Walmart'] ?? '',
                'DHGate' => $value['DHGate'] ?? '',
                'BestbuyUSA' => $value['BestbuyUSA'] ?? '',
                'YouTube' => $value['YouTube'] ?? '',
                'FacebookPage' => $value['FacebookPage'] ?? '',
                'InstagramPage' => $value['InstagramPage'] ?? '',
                'Tiktok' => $value['Tiktok'] ?? '',
            ];

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
            'message' => 'Filtered Product Video Upload data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function productVideoUploadUpdate(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $existing = ProductVideoUpload::where('sku', $request->sku)->first();
        $mergedValue = $request->value;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $mergedValue);
        }

        $videoPosted = ProductVideoUpload::updateOrCreate(
            ['sku' => $request->sku],
            ['value' => json_encode($mergedValue)]
        );

        return response()->json([
            'success' => true,
            'data' => $videoPosted,
            'message' => 'Video posted value saved successfully.'
        ]);
    }

    // product video list end

    //Assembly Video start
    public function assemblyVideoReq(){
        return view('marketing-masters.video-required.assembly-video');
    }

    public function getAssemblyVideoPostedData(Request $request)
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->pluck('sku')->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $videoPostedValues = AssemblyVideo::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = [];

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, 'PARENT') !== false;

            // Default social links
            $videoLink = '';
            $assigned = '';
            $avl = false;
            $new = false;
            $bdc = false;
            $nr = false;
            $approved = false;

            // Get social links from video_posted_values table if available
            if (isset($videoPostedValues[$sku])) {
                $value = $videoPostedValues[$sku]->value;
                if (is_string($value)) {
                    $value = json_decode($value, true);
                }
                $assigned = $value['Assigned'] ?? '';
                $videoLink = $value['VideoLink'] ?? '';
                $avl = $value['Avl'] ?? false;
                $new = $value['New'] ?? false;
                $bdc = $value['2BDC'] ?? false;
                $nr = $value['NR'] ?? false;
                $approved = $value['Approved'] ?? false;
            }

            // Initialize the data structure
            $processedItem = [
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'is_parent' => $isParent,
                'raw_data' => [
                    'parent' => $productMaster->parent,
                    'sku' => $sku,
                    'Values' => $productMaster->Values
                ]
            ];

            $processedItem['Avl'] = $avl;
            $processedItem['NR'] = $nr;
            $processedItem['Approved'] = $approved;
            $processedItem['New'] = $new;
            $processedItem['2BDC'] = $bdc;
            $processedItem['Assigned'] = $assigned;
            $processedItem['VideoLink'] = $videoLink;


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

    public function asseblyStoreOrUpdate(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $sku = $request->sku;
        $newValue = $request->value;

        $existing = AssemblyVideo::where('sku', $sku)->first();
        $mergedValue = $newValue;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $newValue);
        }

        $videoPosted = AssemblyVideo::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        if (
            isset($mergedValue['Approved']) && $mergedValue['Approved'] == true &&
            isset($mergedValue['VideoLink']) && !empty($mergedValue['VideoLink'])
        ) {
            AssemblyVideoUpload::updateOrCreate(
                ['sku' => $sku],
                [
                    'sku' => $sku,
                    'value' => json_encode([
                        'VideoLink' => $mergedValue['VideoLink']
                    ])
                ]
            );
        }

        return response()->json([
            'success' => true,
            'data' => $videoPosted,
            'message' => 'Assembly video value saved successfully.'
        ]);
    }

    public function assemblyVideoUploadView(){
        return view('marketing-masters.video-required.assembly-video-upload');
    }

    public function getAssemblyVideoUploadData()
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->keys()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $videoPostedValues = AssemblyVideoUpload::whereIn('sku', $skus)->get()->keyBy('sku');

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
                'VideoLink' => $value['VideoLink'] ?? '',
                'Amazon' => $value['Amazon'] ?? '',
                'Doba' => $value['Doba'] ?? '',
                'eBay' => $value['eBay'] ?? '',
                'Temu' => $value['Temu'] ?? '',
                'Macys' => $value['Macys'] ?? '',
                'Wayfair' => $value['Wayfair'] ?? '',
                'Reverb' => $value['Reverb'] ?? '',
                'ShopifyB2C' => $value['ShopifyB2C'] ?? '',
                'Aliexpress' => $value['Aliexpress'] ?? '',
                'eBayVariation' => $value['eBayVariation'] ?? '',
                'ShopifyWholesale' => $value['ShopifyWholesale'] ?? '',
                'eBay2' => $value['eBay2'] ?? '',
                'Faire' => $value['Faire'] ?? '',
                'TiktokShop' => $value['TiktokShop'] ?? '',
                'MercariWShip' => $value['MercariWShip'] ?? '',
                'FBMarketplace' => $value['FBMarketplace'] ?? '',
                'Business5Core' => $value['Business5Core'] ?? '',
                'NeweggB2C' => $value['NeweggB2C'] ?? '',
                'PLS' => $value['PLS'] ?? '',
                'AutoDS' => $value['AutoDS'] ?? '',
                'MercariWOShip' => $value['MercariWOShip'] ?? '',
                'Poshmark' => $value['Poshmark'] ?? '',
                'Tiendamia' => $value['Tiendamia'] ?? '',
                'Shein' => $value['Shein'] ?? '',
                'Spocket' => $value['Spocket'] ?? '',
                'Zendrop' => $value['Zendrop'] ?? '',
                'Syncee' => $value['Syncee'] ?? '',
                'NeweggB2B' => $value['NeweggB2B'] ?? '',
                'Appscenic' => $value['Appscenic'] ?? '',
                'FBShop' => $value['FBShop'] ?? '',
                'InstagramShop' => $value['InstagramShop'] ?? '',
                'AmazonFBA' => $value['AmazonFBA'] ?? '',
                'Walmart' => $value['Walmart'] ?? '',
                'DHGate' => $value['DHGate'] ?? '',
                'BestbuyUSA' => $value['BestbuyUSA'] ?? '',
                'YouTube' => $value['YouTube'] ?? '',
                'FacebookPage' => $value['FacebookPage'] ?? '',
                'InstagramPage' => $value['InstagramPage'] ?? '',
                'Tiktok' => $value['Tiktok'] ?? '',
            ];

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
            'message' => 'Assembly Video Upload data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function assemblyVideoUploadUpdate(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $existing = AssemblyVideoUpload::where('sku', $request->sku)->first();
        $mergedValue = $request->value;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $mergedValue);
        }

        $videoPosted = AssemblyVideoUpload::updateOrCreate(
            ['sku' => $request->sku],
            ['value' => json_encode($mergedValue)]
        );

        return response()->json([
            'success' => true,
            'data' => $videoPosted,
            'message' => 'Assembly video value saved successfully.'
        ]);
    }
    //Assembly Video end


    //3d video start
    public function threeDVideoReq(){
        return view('marketing-masters.video-required.3d-video');
    }

    public function getThreeDVideoPostedData(Request $request)
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->pluck('sku')->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $videoPostedValues = ThreeDVideo::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = [];

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, 'PARENT') !== false;

            // Default social links
            $new = false;
            $bdc = false;
            $nr = false;  
            $avlFootageLink = '';
            $avlVideoLink = '';
            $picturesLink = '';
            $assigned = '';

            // Get social links from video_posted_values table if available
            if (isset($videoPostedValues[$sku])) {
                $value = $videoPostedValues[$sku]->value;
                if (is_string($value)) {
                    $value = json_decode($value, true);
                }
                $new = $value['New'] ?? false;
                $bdc = $value['2BDC'] ?? false;
                $nr = $value['NR'] ?? false;
                $avlFootageLink = $value['AvlFootageLink'] ?? '';
                $avlVideoLink = $value['AvlVideoLink'] ?? '';
                $picturesLink = $value['PicturesLink'] ?? '';
                $assigned = $value['Assigned'] ?? '';
            }

            // Initialize the data structure
            $processedItem = [
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'is_parent' => $isParent,
                'raw_data' => [
                    'parent' => $productMaster->parent,
                    'sku' => $sku,
                    'Values' => $productMaster->Values
                ]
            ];

            $processedItem['New'] = $new;
            $processedItem['2BDC'] = $bdc;
            $processedItem['NR'] = $nr;
            $processedItem['AvlFootageLink'] = $avlFootageLink;
            $processedItem['AvlVideoLink'] = $avlVideoLink;
            $processedItem['PicturesLink'] = $picturesLink;
            $processedItem['Assigned'] = $assigned;

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

    public function threeDStoreOrUpdate(Request $request)
    {
       $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $sku = $request->sku;
        $newValue = $request->value;

        $existing = ThreeDVideo::where('sku', $sku)->first();
        $mergedValue = $newValue;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $newValue);
        }

        $videoPosted = ThreeDVideo::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        if (
            isset($mergedValue['Approved']) && $mergedValue['Approved'] == true &&
            isset($mergedValue['AvlVideoLink']) && !empty($mergedValue['AvlVideoLink'])
        ) {
            ThreeDVideoUpload::updateOrCreate(
                ['sku' => $sku],
                [
                    'sku' => $sku,
                    'value' => json_encode([
                        'VideoLink' => $mergedValue['AvlVideoLink']
                    ])
                ]
            );
        }

        return response()->json([
            'success' => true,
            'data' => $videoPosted,
            'message' => 'Assembly video value saved successfully.'
        ]);
    }

    public function threeDVideoUploadView(){
        return view('marketing-masters.video-required.3d-video-upload');
    }

    public function getThreeDVideoUploadData()
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->keys()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $videoPostedValues = ThreeDVideoUpload::whereIn('sku', $skus)->get()->keyBy('sku');

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
                'VideoLink' => $value['VideoLink'] ?? '',
                'Amazon' => $value['Amazon'] ?? '',
                'Doba' => $value['Doba'] ?? '',
                'eBay' => $value['eBay'] ?? '',
                'Temu' => $value['Temu'] ?? '',
                'Macys' => $value['Macys'] ?? '',
                'Wayfair' => $value['Wayfair'] ?? '',
                'Reverb' => $value['Reverb'] ?? '',
                'ShopifyB2C' => $value['ShopifyB2C'] ?? '',
                'Aliexpress' => $value['Aliexpress'] ?? '',
                'eBayVariation' => $value['eBayVariation'] ?? '',
                'ShopifyWholesale' => $value['ShopifyWholesale'] ?? '',
                'eBay2' => $value['eBay2'] ?? '',
                'Faire' => $value['Faire'] ?? '',
                'TiktokShop' => $value['TiktokShop'] ?? '',
                'MercariWShip' => $value['MercariWShip'] ?? '',
                'FBMarketplace' => $value['FBMarketplace'] ?? '',
                'Business5Core' => $value['Business5Core'] ?? '',
                'NeweggB2C' => $value['NeweggB2C'] ?? '',
                'PLS' => $value['PLS'] ?? '',
                'AutoDS' => $value['AutoDS'] ?? '',
                'MercariWOShip' => $value['MercariWOShip'] ?? '',
                'Poshmark' => $value['Poshmark'] ?? '',
                'Tiendamia' => $value['Tiendamia'] ?? '',
                'Shein' => $value['Shein'] ?? '',
                'Spocket' => $value['Spocket'] ?? '',
                'Zendrop' => $value['Zendrop'] ?? '',
                'Syncee' => $value['Syncee'] ?? '',
                'NeweggB2B' => $value['NeweggB2B'] ?? '',
                'Appscenic' => $value['Appscenic'] ?? '',
                'FBShop' => $value['FBShop'] ?? '',
                'InstagramShop' => $value['InstagramShop'] ?? '',
                'AmazonFBA' => $value['AmazonFBA'] ?? '',
                'Walmart' => $value['Walmart'] ?? '',
                'DHGate' => $value['DHGate'] ?? '',
                'BestbuyUSA' => $value['BestbuyUSA'] ?? '',
                'YouTube' => $value['YouTube'] ?? '',   
                'FacebookPage' => $value['FacebookPage'] ?? '',
                'InstagramPage' => $value['InstagramPage'] ?? '',
                'Tiktok' => $value['Tiktok'] ?? '',
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
            'message' => '3D Video Upload data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function threeDVideoUploadUpdate(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $existing = ThreeDVideoUpload::where('sku', $request->sku)->first();
        $mergedValue = $request->value;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $mergedValue);
        }

        $videoPosted = ThreeDVideoUpload::updateOrCreate(
            ['sku' => $request->sku],
            ['value' => json_encode($mergedValue)]
        );

        return response()->json([
            'success' => true,
            'data' => $videoPosted,
            'message' => '3D video value saved successfully.'
        ]);
    }

    //3d video end


    //360 video start
    public function three60VideoReq(){
        return view('marketing-masters.video-required.360-video');
    }

    public function getThree60VideoPostedData(Request $request)
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->pluck('sku')->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $videoPostedValues = Video360::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = [];

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, 'PARENT') !== false;

            // Default social links
            $new = false;
            $bdc = false;
            $nr = false;  
            $approved = false;
            $avlFootageLink = '';
            $avlVideoLink = '';
            $refreshLink = '';

            // Get social links from video_posted_values table if available
            if (isset($videoPostedValues[$sku])) {
                $value = $videoPostedValues[$sku]->value;
                if (is_string($value)) {
                    $value = json_decode($value, true);
                }
                $new = $value['New'] ?? false;
                $bdc = $value['2BDC'] ?? false;
                $nr = $value['NR'] ?? false;
                $approved = $value['Approved'] ?? false;
                $avlFootageLink = $value['AvlFootageLink'] ?? '';
                $avlVideoLink = $value['AvlVideoLink'] ?? '';
                $refreshLink = $value['RefreshLink'] ?? '';
            }

            // Initialize the data structure
            $processedItem = [
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'is_parent' => $isParent,
                'raw_data' => [
                    'parent' => $productMaster->parent,
                    'sku' => $sku,
                    'Values' => $productMaster->Values
                ]
            ];

            $processedItem['New'] = $new;
            $processedItem['2BDC'] = $bdc;
            $processedItem['NR'] = $nr;
            $processedItem['Approved'] = $approved;
            $processedItem['AvlFootageLink'] = $avlFootageLink;
            $processedItem['AvlVideoLink'] = $avlVideoLink;
            $processedItem['RefreshLink'] = $refreshLink;

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

    public function three60StoreOrUpdate(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $sku = $request->sku;
        $newValue = $request->value;

        $existing = Video360::where('sku', $sku)->first();
        $mergedValue = $newValue;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $newValue);
        }

        $videoPosted = Video360::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        if (
            isset($mergedValue['Approved']) && $mergedValue['Approved'] == true &&
            isset($mergedValue['AvlVideoLink']) && !empty($mergedValue['AvlVideoLink'])
        ) {
            Video360Upload::updateOrCreate(
                ['sku' => $sku],
                [
                    'sku' => $sku,
                    'value' => json_encode([
                        'VideoLink' => $mergedValue['AvlVideoLink']
                    ])
                ]
            );
        }

        return response()->json([
            'success' => true,
            'data' => $videoPosted,
            'message' => 'Assembly video value saved successfully.'
        ]);
    }

    public function three60VideoUploadView(){
        return view('marketing-masters.video-required.360-video-upload');
    }

    public function getThree60VideoUploadData()
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->keys()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $videoPostedValues = Video360Upload::whereIn('sku', $skus)->get()->keyBy('sku');

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
                'VideoLink' => $value['VideoLink'] ?? '',
                'Amazon' => $value['Amazon'] ?? '',
                'Doba' => $value['Doba'] ?? '',
                'eBay' => $value['eBay'] ?? '',
                'Temu' => $value['Temu'] ?? '',
                'Macys' => $value['Macys'] ?? '',
                'Wayfair' => $value['Wayfair'] ?? '',
                'Reverb' => $value['Reverb'] ?? '',
                'ShopifyB2C' => $value['ShopifyB2C'] ?? '',
                'Aliexpress' => $value['Aliexpress'] ?? '',
                'eBayVariation' => $value['eBayVariation'] ?? '',
                'ShopifyWholesale' => $value['ShopifyWholesale'] ?? '',
                'eBay2' => $value['eBay2'] ?? '',
                'Faire' => $value['Faire'] ?? '',
                'TiktokShop' => $value['TiktokShop'] ?? '',
                'MercariWShip' => $value['MercariWShip'] ?? '',
                'FBMarketplace' => $value['FBMarketplace'] ?? '',
                'Business5Core' => $value['Business5Core'] ?? '',
                'NeweggB2C' => $value['NeweggB2C'] ?? '',
                'PLS' => $value['PLS'] ?? '',
                'AutoDS' => $value['AutoDS'] ?? '',
                'MercariWOShip' => $value['MercariWOShip'] ?? '',
                'Poshmark' => $value['Poshmark'] ?? '',
                'Tiendamia' => $value['Tiendamia'] ?? '',
                'Shein' => $value['Shein'] ?? '',
                'Spocket' => $value['Spocket'] ?? '',
                'Zendrop' => $value['Zendrop'] ?? '',
                'Syncee' => $value['Syncee'] ?? '',
                'NeweggB2B' => $value['NeweggB2B'] ?? '',
                'Appscenic' => $value['Appscenic'] ?? '',
                'FBShop' => $value['FBShop'] ?? '',
                'InstagramShop' => $value['InstagramShop'] ?? '',
                'AmazonFBA' => $value['AmazonFBA'] ?? '',
                'Walmart' => $value['Walmart'] ?? '',
                'DHGate' => $value['DHGate'] ?? '',
                'BestbuyUSA' => $value['BestbuyUSA'] ?? '',
                'YouTube' => $value['YouTube'] ?? '',
                'FacebookPage' => $value['FacebookPage'] ?? '',
                'InstagramPage' => $value['InstagramPage'] ?? '',
                'Tiktok' => $value['Tiktok'] ?? '',
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
            'message' => '360 Video Upload data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function three60VideoUploadUpdate(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $existing = Video360Upload::where('sku', $request->sku)->first();
        $mergedValue = $request->value;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $mergedValue);
        }

        $videoPosted = Video360Upload::updateOrCreate(
            ['sku' => $request->sku],
            ['value' => json_encode($mergedValue)]
        );

        return response()->json([
            'success' => true,
            'data' => $videoPosted,
            'message' => '360 video value saved successfully.'
        ]);
    }

    //360 video end


    //Benefits video start
    public function benefitsVideoReq(){
        return view('marketing-masters.video-required.benefits-video');
    }

    public function getBenefitsVideoPostedData(Request $request)
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->pluck('sku')->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $videoPostedValues = BenefitVideo::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = [];

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, 'PARENT') !== false;

            // Default social links
            $new = false;
            $bdc = false;
            $nr = false;  
            $approved = false;
            $videoLink = '';
            $assigned = '';

            // Get social links from video_posted_values table if available
            if (isset($videoPostedValues[$sku])) {
                $value = $videoPostedValues[$sku]->value;
                if (is_string($value)) {
                    $value = json_decode($value, true);
                }
                $new = $value['New'] ?? false;
                $bdc = $value['2BDC'] ?? false;
                $nr = $value['NR'] ?? false;
                $approved = $value['Approved'] ?? false;
                $videoLink = $value['VideoLink'] ?? '';
                $assigned = $value['Assigned'] ?? '';
            }

            // Initialize the data structure
            $processedItem = [
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'is_parent' => $isParent,
                'raw_data' => [
                    'parent' => $productMaster->parent,
                    'sku' => $sku,
                    'Values' => $productMaster->Values
                ]
            ];

            $processedItem['New'] = $new;
            $processedItem['2BDC'] = $bdc;
            $processedItem['NR'] = $nr;
            $processedItem['Approved'] = $approved;
            $processedItem['VideoLink'] = $videoLink;
            $processedItem['Assigned'] = $assigned;

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

    public function benefitsStoreOrUpdate(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $sku = $request->sku;
        $newValue = $request->value;

        $existing = BenefitVideo::where('sku', $sku)->first();
        $mergedValue = $newValue;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $newValue);
        }

        $videoPosted = BenefitVideo::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        if (
            isset($mergedValue['Approved']) && $mergedValue['Approved'] == true &&
            isset($mergedValue['VideoLink']) && !empty($mergedValue['VideoLink'])
        ) {
            BenefitVideoUpload::updateOrCreate(
                ['sku' => $sku],
                [
                    'sku' => $sku,
                    'value' => json_encode([
                        'VideoLink' => $mergedValue['VideoLink']
                    ])
                ]
            );
        }

        return response()->json([
            'success' => true,
            'data' => $videoPosted,
            'message' => 'Assembly video value saved successfully.'
        ]);
    }

    public function benefitsVideoUploadView(){
        return view('marketing-masters.video-required.benefits-video-upload');
    }

    public function getBenefitsVideoUploadData()
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->keys()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $videoPostedValues = BenefitVideoUpload::whereIn('sku', $skus)->get()->keyBy('sku');

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
                'VideoLink' => $value['VideoLink'] ?? '',
                'Amazon' => $value['Amazon'] ?? '',
                'Doba' => $value['Doba'] ?? '',
                'eBay' => $value['eBay'] ?? '',
                'Temu' => $value['Temu'] ?? '',
                'Macys' => $value['Macys'] ?? '',
                'Wayfair' => $value['Wayfair'] ?? '',
                'Reverb' => $value['Reverb'] ?? '',
                'ShopifyB2C' => $value['ShopifyB2C'] ?? '',
                'Aliexpress' => $value['Aliexpress'] ?? '',
                'eBayVariation' => $value['eBayVariation'] ?? '',
                'ShopifyWholesale' => $value['ShopifyWholesale'] ?? '',
                'eBay2' => $value['eBay2'] ?? '',
                'Faire' => $value['Faire'] ?? '',
                'TiktokShop' => $value['TiktokShop'] ?? '',
                'MercariWShip' => $value['MercariWShip'] ?? '',
                'FBMarketplace' => $value['FBMarketplace'] ?? '',
                'Business5Core' => $value['Business5Core'] ?? '',
                'NeweggB2C' => $value['NeweggB2C'] ?? '',
                'PLS' => $value['PLS'] ?? '',
                'AutoDS' => $value['AutoDS'] ?? '',
                'MercariWOShip' => $value['MercariWOShip'] ?? '',
                'Poshmark' => $value['Poshmark'] ?? '',
                'Tiendamia' => $value['Tiendamia'] ?? '',
                'Shein' => $value['Shein'] ?? '',
                'Spocket' => $value['Spocket'] ?? '',
                'Zendrop' => $value['Zendrop'] ?? '',
                'Syncee' => $value['Syncee'] ?? '',
                'NeweggB2B' => $value['NeweggB2B'] ?? '',
                'Appscenic' => $value['Appscenic'] ?? '',
                'FBShop' => $value['FBShop'] ?? '',
                'InstagramShop' => $value['InstagramShop'] ?? '',
                'AmazonFBA' => $value['AmazonFBA'] ?? '',
                'Walmart' => $value['Walmart'] ?? '',
                'DHGate' => $value['DHGate'] ?? '',
                'BestbuyUSA' => $value['BestbuyUSA'] ?? '',
                'YouTube' => $value['YouTube'] ?? '',
                'FacebookPage' => $value['FacebookPage'] ?? '',
                'InstagramPage' => $value['InstagramPage'] ?? '',
                'Tiktok' => $value['Tiktok'] ?? '',
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
            'message' => 'Benefits Video Upload data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function benefitsVideoUploadUpdate(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $existing = BenefitVideoUpload::where('sku', $request->sku)->first();
        $mergedValue = $request->value;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $mergedValue);
        }

        $videoPosted = BenefitVideoUpload::updateOrCreate(
            ['sku' => $request->sku],
            ['value' => json_encode($mergedValue)]
        );

        return response()->json([
            'success' => true,
            'data' => $videoPosted,
            'message' => 'Benefits video value saved successfully.'
        ]);
    }

    //Benefits video end

    //diy video start
    public function diyVideoReq(){
        return view('marketing-masters.video-required.diy-video');
    }

    public function getDiyVideoPostedData(Request $request)
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->pluck('sku')->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $videoPostedValues = DiyVideo::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = [];

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, 'PARENT') !== false;

            // Default social links
            $assigned = '';
            $avlVideoLink = '';
            $shootRequired = false;
            $nr = false;
            $approved = false;
            $new = false;
            $bdc = false;
            $avl = false;

            // Get social links from video_posted_values table if available
            if (isset($videoPostedValues[$sku])) {
                $value = $videoPostedValues[$sku]->value;
                if (is_string($value)) {
                    $value = json_decode($value, true);
                }
                $nr = $value['NR'] ?? false;
                $new = $value['New'] ?? false;
                $bdc = $value['2BDC'] ?? false;
                $avl = $value['Avl'] ?? false;
                $approved = $value['Approved'] ?? false;
                $shootRequired = $value['ShootRequired'] ?? false;
                $assigned = $value['Assigned'] ?? '';
                $avlVideoLink = $value['AvlVideoLink'] ?? '';
            }

            // Initialize the data structure
            $processedItem = [
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'is_parent' => $isParent,
                'raw_data' => [
                    'parent' => $productMaster->parent,
                    'sku' => $sku,
                    'Values' => $productMaster->Values
                ]
            ];

            $processedItem['NR'] = $nr;
            $processedItem['New'] = $new;
            $processedItem['2BDC'] = $bdc;
            $processedItem['Avl'] = $avl;
            $processedItem['Approved'] = $approved;
            $processedItem['ShootRequired'] = $shootRequired;
            $processedItem['Assigned'] = $assigned;
            $processedItem['AvlVideoLink'] = $avlVideoLink;

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

    public function diyStoreOrUpdate(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $sku = $request->sku;
        $newValue = $request->value;

        $existing = DiyVideo::where('sku', $sku)->first();
        $mergedValue = $newValue;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $newValue);
        }

        $videoPosted = DiyVideo::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        if (
            isset($mergedValue['Approved']) && $mergedValue['Approved'] == true &&
            isset($mergedValue['AvlVideoLink']) && !empty($mergedValue['AvlVideoLink'])
        ) {
            DiyVideoUpload::updateOrCreate(
                ['sku' => $sku],
                [
                    'sku' => $sku,
                    'value' => json_encode([
                        'VideoLink' => $mergedValue['AvlVideoLink']
                    ])
                ]
            );
        }

        return response()->json([
            'success' => true,
            'data' => $videoPosted,
            'message' => 'Assembly video value saved successfully.'
        ]);
    }

    public function diyVideoUploadView(){
        return view('marketing-masters.video-required.diy-video-upload');
    }

    public function getDiyVideoUploadData()
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->keys()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $videoPostedValues = DiyVideoUpload::whereIn('sku', $skus)->get()->keyBy('sku');

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
                'VideoLink' => $value['VideoLink'] ?? '',
                'Amazon' => $value['Amazon'] ?? '',
                'Doba' => $value['Doba'] ?? '',
                'eBay' => $value['eBay'] ?? '',
                'Temu' => $value['Temu'] ?? '',
                'Macys' => $value['Macys'] ?? '',
                'Wayfair' => $value['Wayfair'] ?? '',
                'Reverb' => $value['Reverb'] ?? '',
                'ShopifyB2C' => $value['ShopifyB2C'] ?? '',
                'Aliexpress' => $value['Aliexpress'] ?? '',
                'eBayVariation' => $value['eBayVariation'] ?? '',
                'ShopifyWholesale' => $value['ShopifyWholesale'] ?? '',
                'eBay2' => $value['eBay2'] ?? '',
                'Faire' => $value['Faire'] ?? '',
                'TiktokShop' => $value['TiktokShop'] ?? '',
                'MercariWShip' => $value['MercariWShip'] ?? '',
                'FBMarketplace' => $value['FBMarketplace'] ?? '',
                'Business5Core' => $value['Business5Core'] ?? '',
                'NeweggB2C' => $value['NeweggB2C'] ?? '',
                'PLS' => $value['PLS'] ?? '',
                'AutoDS' => $value['AutoDS'] ?? '',
                'MercariWOShip' => $value['MercariWOShip'] ?? '',
                'Poshmark' => $value['Poshmark'] ?? '',
                'Tiendamia' => $value['Tiendamia'] ?? '',
                'Shein' => $value['Shein'] ?? '',
                'Spocket' => $value['Spocket'] ?? '',
                'Zendrop' => $value['Zendrop'] ?? '',
                'Syncee' => $value['Syncee'] ?? '',
                'NeweggB2B' => $value['NeweggB2B'] ?? '',
                'Appscenic' => $value['Appscenic'] ?? '',
                'FBShop' => $value['FBShop'] ?? '',
                'InstagramShop' => $value['InstagramShop'] ?? '',
                'AmazonFBA' => $value['AmazonFBA'] ?? '',
                'Walmart' => $value['Walmart'] ?? '',
                'DHGate' => $value['DHGate'] ?? '',
                'BestbuyUSA' => $value['BestbuyUSA'] ?? '',
                'YouTube' => $value['YouTube'] ?? '',
                'FacebookPage' => $value['FacebookPage'] ?? '',
                'InstagramPage' => $value['InstagramPage'] ?? '',
                'Tiktok' => $value['Tiktok'] ?? '',
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
            'message' => 'Diy Video Upload data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }

    public function diyVideoUploadUpdate(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $existing = DiyVideoUpload::where('sku', $request->sku)->first();
        $mergedValue = $request->value;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $mergedValue);
        }

        $videoPosted = DiyVideoUpload::updateOrCreate(
            ['sku' => $request->sku],
            ['value' => json_encode($mergedValue)]
        );

        return response()->json([
            'success' => true,
            'data' => $videoPosted,
            'message' => 'Diy video value saved successfully.'
        ]);
    }

    //diy video end

    //Shoppable video start
    public function shoppableVideoReq(){
        return view('marketing-masters.video-required.shoppable-video');
    }

    public function getShoppableVideoPostedData(Request $request)
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        $skus = $productMasterRows->pluck('sku')->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $videoPostedValues = ShoppableVideo::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = [];

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, 'PARENT') !== false;

            // Default social links
            $tiktokVideoLink = '';
            $ads = false;

            // Get social links from video_posted_values table if available
            if (isset($videoPostedValues[$sku])) {
                $value = $videoPostedValues[$sku]->value;
                if (is_string($value)) {
                    $value = json_decode($value, true);
                }
                $tiktokVideoLink = $value['TikTokVideoLink'] ?? '';
                $ads = $value['Ads'] ?? false;
            }

            // Initialize the data structure
            $processedItem = [
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'is_parent' => $isParent,
                'raw_data' => [
                    'parent' => $productMaster->parent,
                    'sku' => $sku,
                    'Values' => $productMaster->Values
                ]
            ];

            $processedItem['TikTokVideoLink'] = $tiktokVideoLink;
            $processedItem['Ads'] = $ads;

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

    public function shoppableStoreOrUpdate(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'value' => 'required|array',
        ]);

        $sku = $request->sku;
        $newValue = $request->value;

        $existing = ShoppableVideo::where('sku', $sku)->first();
        $mergedValue = $newValue;

        if ($existing) {
            $oldValue = is_array($existing->value) ? $existing->value : json_decode($existing->value, true);
            $mergedValue = array_merge($oldValue ?? [], $newValue);
        }

        $videoPosted = ShoppableVideo::updateOrCreate(
            ['sku' => $sku],
            ['value' => json_encode($mergedValue)]
        );

        if (
            isset($mergedValue['Ads']) && $mergedValue['Ads'] == "REQ" &&
            isset($mergedValue['TikTokVideoLink']) && !empty($mergedValue['TikTokVideoLink'])
        ) {
            TiktokVideoAd::updateOrCreate(
                ['sku' => $sku],
                [
                    'sku' => $sku,
                    'value' => json_encode([
                        'VideoLink' => $mergedValue['TikTokVideoLink']
                    ])
                ]
            );
        }

        return response()->json([
            'success' => true,
            'data' => $videoPosted,
            'message' => 'Shoppable video value saved successfully.'
        ]);
    }
    //Shoppable video end


    //import section

    public function import(Request $request)
    {
        $file = $request->file('excel_file');

        if (!$file) {
            return back()->with('error', 'No file uploaded.');
        }

        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        $headers = array_map('trim', $rows[1]);
        $headerMap = [];
        foreach ($headers as $col => $name) {
            $headerMap[$col] = $name;
        }

        for ($i = 2; $i <= count($rows); $i++) {
            $row = $rows[$i];
            $sku = $row['A'] ?? null;
            if (!$sku) continue;

            $valueData = [];

            // Handle checkboxes
            $checkboxFields = ['New', '2BDC'];
            foreach ($checkboxFields as $field) {
                $colKey = array_search($field, $headerMap);
                if (!$colKey) continue;

                $isChecked = $row[$colKey] === true || strtolower(trim($row[$colKey])) == 'true' || $row[$colKey] == 1;
                if ($isChecked) {
                    $valueData[$field] = true;
                }
            }

            // Handle NR and Require logic
            $nrKey = array_search('NR', $headerMap);
            $reqKey = array_search('Require', $headerMap);

            $nrChecked = $nrKey && (
                $row[$nrKey] === true || strtolower(trim($row[$nrKey])) == 'true' || $row[$nrKey] == 1
            );
            $reqChecked = $reqKey && (
                $row[$reqKey] === true || strtolower(trim($row[$reqKey])) == 'true' || $row[$reqKey] == 1
            );

            if ($reqChecked) {
                $valueData['NR'] = 'REQ'; // Require overrides NR
            } elseif ($nrChecked) {
                $valueData['NR'] = 'NR';
            }

            // Other fields
            $AvlFootageLink = $row[array_search('VideoLink', $headerMap)] ?? null;


            // Add optional fields
            if ($AvlFootageLink) $valueData['AvlVideoLink'] = $AvlFootageLink;


            // Save to DB
            DiyVideo::updateOrCreate(
                ['sku' => $sku],
                [
                    'value' => json_encode($valueData),
                ]
            );
        }

        return back()->with('success', 'Video Posted Sheet imported successfully!');
    }



}
