<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VideosMasterController extends Controller
{
    public function videosMaster()
    {
        return view('videos-master');
    }

    public function getVideosMasterData(Request $request)
    {
        // Fetch all products from the database ordered by parent and SKU
        $products = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        // Fetch all shopify SKUs and normalize keys by replacing non-breaking spaces
        $shopifySkus = ShopifySku::all()->keyBy(function ($item) {
            return str_replace("\u{00a0}", ' ', $item->sku);
        });

        // Fetch amazon data view with buyer and seller links
        $amazonDataViews = \App\Models\AmazonDataView::all()->keyBy(function ($item) {
            return str_replace("\u{00a0}", ' ', $item->sku);
        });

        // Fetch junglescout data for LQS (Listing Quality Score)
        $junglescoutData = \DB::table('junglescout_product_data')
            ->get()
            ->keyBy(function ($item) {
                return str_replace("\u{00a0}", ' ', $item->sku);
            });

        // Prepare data
        $result = [];
        foreach ($products as $product) {
            $row = [
                'id' => $product->id,
                'Parent' => $product->parent,
                'SKU' => $product->sku,
                // Add video fields directly from product model
                'video_product_overview' => $product->video_product_overview,
                'video_product_overview_status' => $product->video_product_overview_status,
                'video_unboxing' => $product->video_unboxing,
                'video_unboxing_status' => $product->video_unboxing_status,
                'video_how_to' => $product->video_how_to,
                'video_how_to_status' => $product->video_how_to_status,
                'video_setup' => $product->video_setup,
                'video_setup_status' => $product->video_setup_status,
                'video_troubleshooting' => $product->video_troubleshooting,
                'video_troubleshooting_status' => $product->video_troubleshooting_status,
                'video_brand_story' => $product->video_brand_story,
                'video_brand_story_status' => $product->video_brand_story_status,
                'video_product_benefits' => $product->video_product_benefits,
                'video_product_benefits_status' => $product->video_product_benefits_status,
            ];

            // Merge the Values array (if not null)
            if (is_array($product->Values)) {
                $row = array_merge($row, $product->Values);
            } elseif (is_string($product->Values)) {
                $values = json_decode($product->Values, true);
                if (is_array($values)) {
                    $row = array_merge($row, $values);
                }
            }

            // Normalize the product SKU for lookup
            $normalizedSku = str_replace("\u{00a0}", ' ', $product->sku);

            // Add Shopify data
            if (isset($shopifySkus[$normalizedSku])) {
                $shopifyData = $shopifySkus[$normalizedSku];
                $row['shopify_inv'] = $shopifyData->inv !== null ? (float) $shopifyData->inv : 0;
                $row['shopify_quantity'] = $shopifyData->quantity !== null ? (float) $shopifyData->quantity : 0;
                $row['ovl30'] = $row['shopify_quantity'];
                
                // Calculate Dil
                $inv = $row['shopify_inv'];
                $ovl30 = $row['shopify_quantity'];
                $dil = ($inv > 0) ? ($ovl30 / $inv) * 100 : 0;
                $row['dil'] = round($dil, 2);
                
                $shopifyImage = $shopifyData->image_src ?? null;
            } else {
                $row['shopify_inv'] = 0;
                $row['shopify_quantity'] = 0;
                $row['ovl30'] = 0;
                $row['dil'] = 0;
                $shopifyImage = null;
            }

            // Set image path
            $localImage = isset($row['image_path']) && $row['image_path'] ? $row['image_path'] : null;
            if ($shopifyImage) {
                $row['image_path'] = $shopifyImage;
            } elseif ($localImage) {
                $row['image_path'] = '/'.ltrim($localImage, '/');
            } else {
                $row['image_path'] = null;
            }

            // Add Amazon buyer and seller links
            if (isset($amazonDataViews[$normalizedSku])) {
                $amazonData = $amazonDataViews[$normalizedSku];
                $amazonValue = is_array($amazonData->value) ? $amazonData->value : json_decode($amazonData->value, true);
                $row['buyer_link'] = $amazonValue['buyer_link'] ?? null;
                $row['seller_link'] = $amazonValue['seller_link'] ?? null;
            } else {
                $row['buyer_link'] = null;
                $row['seller_link'] = null;
            }

            // Add Junglescout LQS data
            if (isset($junglescoutData[$normalizedSku])) {
                $jsData = $junglescoutData[$normalizedSku];
                $jsDataValue = is_array($jsData->data) ? $jsData->data : json_decode($jsData->data, true);
                $row['lqs'] = $jsDataValue['listing_quality_score'] ?? null;
            } else {
                $row['lqs'] = null;
            }

            $result[] = $row;
        }

        return response()->json([
            'message' => 'Data loaded from database',
            'data' => $result,
            'status' => 200,
        ]);
    }
}
