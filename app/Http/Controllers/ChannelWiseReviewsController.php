<?php

namespace App\Http\Controllers;

use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\ChannelsReviewsData;
use Illuminate\Http\Request;

class ChannelWiseReviewsController extends Controller
{
   public function reviews_dashboard()
    {
        return view('reviews.reviews_dashboard');
    }

    private function getDilStyle($dilValue)
    {
        if ($dilValue >= 0 && $dilValue <= 10) {
            return 'color: red;';
        } elseif ($dilValue >= 11 && $dilValue <= 15) {
            return 'background-color: yellow; color: black; padding: 2px 4px; border-radius: 4px;';
        } elseif ($dilValue >= 16 && $dilValue <= 20) {
            return 'color: blue;';
        } elseif ($dilValue >= 21 && $dilValue <= 40) {
            return 'color: green;';
        } elseif ($dilValue >= 41) {
            return 'color: purple;';
        }
        return '';
    }

    public function reviews_dashboard_details()
    {
        $productData = ProductMaster::whereNull('deleted_at')
            ->orderBy('id', 'asc')
            ->get();

        $skus = $productData
            ->pluck('sku')
            ->filter(fn($sku) => stripos($sku, 'PARENT') === false)
            ->unique()
            ->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)
            ->get()
            ->keyBy(fn($item) => trim(strtoupper($item->sku)));

        // Fetch reviews data
        $reviewsData = ChannelsReviewsData::whereIn('sku', $skus)
            ->get()
            ->keyBy(fn($item) => trim(strtoupper($item->sku)));

        // Prepare the final flat array
        $data = [];

        foreach ($productData as $product) {
            $sku = strtoupper(trim($product->sku));
            $shopify = $shopifyData[$sku] ?? null;

            $inv = $shopify->inv ?? 0;
            $quantity = $shopify->quantity ?? 0;

            $dilValue = $inv > 0 ? round(($quantity / $inv) * 100) : 0;

            // Get reviews for this SKU
            $reviews = $reviewsData[$sku] ?? null;

            $data[] = [
                'Parent' => $product->parent ?? '-',
                'SKU' => $sku,
                'Shopify_INV' => $inv,
                'OVL3' => $shopify->quantity ?? 0,
                'Dil' => $dilValue,
                'Dil_Style' => $this->getDilStyle($dilValue),
                'Amazon_Reviews' => $reviews->amazon_reviews ?? '-',
                'Ebay_One_Reviews' => $reviews->ebay_one_reviews ?? '-',
                'Ebay_Two_Reviews' => $reviews->ebay_two_reviews ?? '-',
                'Ebay_Three_Reviews' => $reviews->ebay_three_reviews ?? '-',
                'Temu_Reviews' => $reviews->temu_reviews ?? '-',
            ];
        }


        return response()->json($data);
    }

    public function saveReview(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'channel' => 'required|string',
            'link' => 'required|url',
            'rating' => 'required|numeric|min:0.5|max:5'
        ]);

        $sku = strtoupper(trim($request->sku));
        $channel = $request->channel;
        $reviewData = json_encode([
            'link' => $request->link,
            'rating' => $request->rating,
            'date' => date('Y-m-d H:i:s')
        ]);

        // Map channel name to database field
        $channelFieldMap = [
            'Amazon' => 'amazon_reviews',
            'Ebay One' => 'ebay_one_reviews',
            'Ebay Two' => 'ebay_two_reviews',
            'Ebay Three' => 'ebay_three_reviews',
            'Temu' => 'temu_reviews'
        ];

        $field = $channelFieldMap[$channel] ?? null;

        if (!$field) {
            return response()->json(['message' => 'Invalid channel'], 400);
        }

        // Find or create record
        $review = ChannelsReviewsData::firstOrNew(['sku' => $sku]);
        
        // Get existing reviews for this channel
        $existingReviews = $review->$field ? json_decode($review->$field, true) : [];
        
        // If it's not an array, make it one
        if (!is_array($existingReviews)) {
            $existingReviews = [];
        }
        
        // Add new review
        $existingReviews[] = [
            'link' => $request->link,
            'rating' => $request->rating,
            'date' => date('Y-m-d H:i:s')
        ];
        
        // Save as JSON
        $review->$field = json_encode($existingReviews);
        $review->save();

        return response()->json(['message' => 'Review saved successfully', 'data' => $review]);
    }
}
