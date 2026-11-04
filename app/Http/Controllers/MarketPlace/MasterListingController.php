<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Support\Marketplace\ListingChannelCounts;
use App\Support\Marketplace\MasterListingDetails;
use App\Support\Marketplace\SheetListingCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Master listing form for marketplaces without a listing API: Title, Description, Shopify image links,
 * weight / size (kg, cm, lb, in), Shopify category, price and inventory, all read from our masters,
 * ready to export as CSV and complete offline.
 */
class MasterListingController extends Controller
{
    /** The Missing L SKUs of the channel (SKU list only; details are fetched in batches). */
    public function skus(string $channel): JsonResponse
    {
        $slug = $this->slug($channel);
        if ($slug === null) {
            return $this->notSupported();
        }

        @set_time_limit(120);
        $rows = MasterListingDetails::missingSkus($slug);

        return response()->json([
            'success' => true,
            'channel' => $slug,
            'label' => MasterListingDetails::label($slug),
            'columns' => MasterListingDetails::columns(),
            'skus' => array_column($rows, 'sku'),
        ]);
    }

    /** Fresh details of a batch of SKUs from the masters. */
    public function details(Request $request, string $channel): JsonResponse
    {
        $slug = $this->slug($channel);
        if ($slug === null) {
            return $this->notSupported();
        }

        $data = $request->validate([
            'skus' => 'required|array|min:1|max:50',
            'skus.*' => 'required|string|max:191',
            'refresh' => 'nullable|boolean',
        ]);

        @set_time_limit(180);
        $result = MasterListingDetails::fetch($data['skus'], (bool) ($data['refresh'] ?? false));

        return response()->json([
            'success' => true,
            'rows' => $result['rows'],
            'message' => $result['message'],
        ]);
    }

    private function slug(string $channel): ?string
    {
        $slug = ListingChannelCounts::normalize($channel);
        if (! MasterListingDetails::supports($slug)) {
            return null;
        }

        return SheetListingCatalog::has($slug) ? SheetListingCatalog::canonical($slug) : $slug;
    }

    private function notSupported(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'This marketplace has a listing API, so it does not use the master listing sheet.',
        ], 404);
    }
}
