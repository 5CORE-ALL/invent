<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;



use App\Http\Controllers\Controller;
use App\Support\Marketplace\AutomatedListingPage;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\ZendropListingStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ListingZendropController extends Controller
{
    use HandlesListingPublishActions;

    public function listingZendrop(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $percentage = Cache::remember('zendrop_marketplace_percentage', now()->addDays(30), function () {
            return 100;
        });

        return view('market-places.listing-market-places.listingZendrop', [
            'zendropPercentage' => $percentage,
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getViewListingZendropData(Request $request)
    {
        return response()->json([
            'status' => 200,
            'data' => AutomatedListingPage::rows('zendrop'),
        ]);
    }

    public function saveStatus(Request $request)
    {
        if ($response = $this->listingPublishResponse($request)) {
            return $response;
        }

        $validated = $request->validate([
            'sku' => 'required|string',
            'nr_req' => 'nullable|string',
            'listed' => 'nullable|string',
            'buyer_link' => 'nullable|url',
            'seller_link' => 'nullable|url',
        ]);

        $sku = $validated['sku'];
        $status = ZendropListingStatus::where('sku', $sku)->first();

        $existing = $status ? $status->value : [];

        // Only update the fields that are present in the request
        $fields = ['nr_req', 'listed', 'buyer_link', 'seller_link'];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $existing[$field] = $validated[$field];
            }
        }

        ZendropListingStatus::updateOrCreate(
            ['sku' => $validated['sku']],
            ['value' => $existing]
        );

        return response()->json(['status' => 'success']);
    }

    public function getNrReqCount()
    {
        return ChannelListingRegistry::nrReqCountArray('zendrop');
    }

}