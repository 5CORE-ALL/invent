<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\TopDawgDataView;
use App\Models\TopDawgListingStatus;
use App\Support\Marketplace\AutomatedListingPage;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\ListingStatusCsv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ListingTopDawgController extends Controller
{
    use HandlesListingPublishActions;

    protected function listingPublishChannel(): string
    {
        return 'topdawg';
    }

    public function listingTopDawg(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $percentage = Cache::remember('topdawg_marketplace_percentage', now()->addDays(30), function () {
            return 100;
        });

        return view('market-places.listing-market-places.listingTopDawg', [
            'topdawgPercentage' => $percentage,
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getViewListingTopDawgData(Request $request)
    {
        return response()->json([
            'status' => 200,
            'data' => AutomatedListingPage::rows('topdawg'),
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

        $sku = trim($validated['sku']);
        if (Schema::hasTable('topdawg_listing_statuses')) {
            $status = TopDawgListingStatus::where('sku', $sku)->first();
            $existing = $status ? ($status->value ?? []) : [];
            if (! is_array($existing)) {
                $existing = [];
            }

            foreach (['nr_req', 'listed', 'buyer_link', 'seller_link'] as $field) {
                if ($request->has($field)) {
                    $existing[$field] = $validated[$field];
                }
            }

            TopDawgListingStatus::updateOrCreate(
                ['sku' => $sku],
                ['value' => $existing]
            );
        }

        if ($request->has('nr_req')) {
            $nrReq = strtoupper(trim((string) $validated['nr_req']));
            $isNrl = in_array($nrReq, ['NR', 'NRL'], true);
            $dataView = TopDawgDataView::firstOrNew(['sku' => $sku]);
            $value = is_array($dataView->value)
                ? $dataView->value
                : (json_decode((string) $dataView->value, true) ?: []);
            $value['NRL'] = $isNrl ? 'NRL' : 'REQ';
            $value['NR'] = $isNrl ? 'NR' : 'REQ';
            $dataView->value = $value;
            $dataView->save();
        }

        return response()->json(['status' => 'success']);
    }

    public function getNrReqCount()
    {
        return ChannelListingRegistry::nrReqCountArray('topdawg');
    }

    public function import(Request $request)
    {
        return ListingStatusCsv::import($request, TopDawgListingStatus::class);
    }

    public function export()
    {
        return ListingStatusCsv::export(TopDawgListingStatus::class, 'listing_topdawg_'.date('Y-m-d').'.csv');
    }
}
