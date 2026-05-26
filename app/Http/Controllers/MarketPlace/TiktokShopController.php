<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MarketplacePercentage;
use App\Models\TiktokShopDataView;
use Illuminate\Support\Facades\Cache;

class TiktokShopController extends Controller
{
    public function tiktokPricingCVR(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        $percentage = Cache::remember('Walmart', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Walmart')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100;
        });

        return view('market-places.walmartPricingCVR', [
            'mode' => $mode,
            'demo' => $demo,
            'percentage' => $percentage
        ]);
    }

    public function updateAllTiktokSkus(Request $request)
    {
        try {
            $type = $request->input('type');
            $value = $request->input('value');

            // Support legacy 'percent' parameter
            if (!$type && $request->has('percent')) {
                $type = 'percentage';
                $value = $request->input('percent');
            }

            $marketplaceData = MarketplacePercentage::where('marketplace', 'TiktokShop')->first();
            $percent = $marketplaceData ? $marketplaceData->percentage : 100;
            $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 100;

            if ($type === 'percentage') {
                if (!is_numeric($value) || $value < 0 || $value > 100) {
                    return response()->json([
                        'status' => 400,
                        'message' => 'Invalid percentage value. Must be between 0 and 100.'
                    ], 400);
                }
                $percent = $value;
            }

            if ($type === 'ad_updates') {
                if (!is_numeric($value) || $value < 0) {
                    return response()->json([
                        'status' => 400,
                        'message' => 'Invalid ad_updates value.'
                    ], 400);
                }
                $adUpdates = $value;
            }

            // Update database
            $marketplace = MarketplacePercentage::updateOrCreate(
                ['marketplace' => 'TiktokShop'],
                [
                    'percentage' => $percent,
                    'ad_updates' => $adUpdates
                ]
            );

            // Store in cache
            Cache::put('TiktokShop_marketplace_percentage', $percent, now()->addDays(30));
            Cache::put('TiktokShop_marketplace_ad_updates', $adUpdates, now()->addDays(30));

            return response()->json([
                'status' => 200,
                'message' => ucfirst($type) . ' updated successfully',
                'data' => [
                    'marketplace' => 'TiktokShop',
                    'percentage' => $marketplace->percentage,
                    'ad_updates' => $marketplace->ad_updates
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error updating percentage',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function saveNrToDatabase(Request $request)
    {
        $sku = $request->input('sku');
        $nr = $request->input('nr');

        if (!$sku || $nr === null) {
            return response()->json(['error' => 'SKU and nr are required.'], 400);
        }

        $dataView = TiktokShopDataView::firstOrNew(['sku' => $sku]);
        $value = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?: []);
        $value['NR'] = filter_var($nr, FILTER_VALIDATE_BOOLEAN);
        $dataView->value = $value;
        $dataView->save();

        return response()->json(['success' => true, 'data' => $dataView]);
    }
}
