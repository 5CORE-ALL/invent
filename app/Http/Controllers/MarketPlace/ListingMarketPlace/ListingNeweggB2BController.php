<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\NeweggB2BListingStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ListingNeweggB2BController extends Controller
{
    public function listingNeweggB2B(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $percentage = Cache::remember('neweggb2b_marketplace_percentage', now()->addDays(30), function () {
            return 100;
        });

        return view('market-places.listing-market-places.listingNeweggB2B', [
            'neweggB2BPercentage' => $percentage,
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getViewListingNeweggB2BData(Request $request)
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = NeweggB2BListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $processedData = $productMasters->map(function ($item) use ($shopifyData, $statusData) {
            $childSku = $item->sku;
            $item->INV = $shopifyData[$childSku]->inv ?? 0;
            $item->L30 = $shopifyData[$childSku]->quantity ?? 0;
            $item->nr_req = null;
            $item->listed = null;
            $item->buyer_link = null;
            $item->seller_link = null;
            if (isset($statusData[$childSku])) {
                $status = $statusData[$childSku]->value;
                $item->nr_req = $status['nr_req'] ?? null;
            }
            return $item;
        })->values();

        return response()->json([
            'status' => 200,
            'data' => $processedData
        ]);
    }

    public function saveStatus(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'nr_req' => 'nullable|string',
            'listed' => 'nullable|string',
            'buyer_link' => 'nullable|url',
            'seller_link' => 'nullable|url',
        ]);

        $sku = $validated['sku'];
        $status = NeweggB2BListingStatus::where('sku', $sku)->first();

        $existing = $status ? $status->value : [];

        // Only update the fields that are present in the request
        $fields = ['nr_req', 'listed', 'buyer_link', 'seller_link'];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $existing[$field] = $validated[$field];
            }
        }

        NeweggB2BListingStatus::updateOrCreate(
            ['sku' => $validated['sku']],
            ['value' => $existing]
        );

        return response()->json(['status' => 'success']);
    }

    public function getNrReqCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = NeweggB2BListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $reqCount = 0;
        $listedCount = 0;
        $pendingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $inv = $shopifyData[$sku]->inv ?? 0;
            $isParent = stripos($sku, 'PARENT') !== false;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // NR/REQ logic
            $nrReq = $status['nr_req'] ?? (floatval($inv) > 0 ? 'REQ' : 'NR');
            if ($nrReq === 'REQ') {
                $reqCount++;
            }

            // Listed/Pending logic
            $listed = $status['listed'] ?? (floatval($inv) > 0 ? 'Pending' : 'Listed');
            if ($listed === 'Listed') {
                $listedCount++;
            } elseif ($listed === 'Pending') {
                $pendingCount++;
            }
        }

        return [
            'REQ' => $reqCount,
            'Listed' => $listedCount,
            'Pending' => $pendingCount,
        ];
    }
}