<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

use App\Services\Lqs\LqsShopifySeoService;
use Illuminate\Http\Request;

class LqsShopifyController extends LqsMarketplaceBaseController
{
    protected function slug(): string
    {
        return 'shopify';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.shopify';
    }

    public function syncSeo(Request $request, LqsShopifySeoService $seo)
    {
        @set_time_limit(180);

        try {
            $result = $seo->sync($request->boolean('live', true));

            return response()->json(['success' => true] + $result);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Failed to sync Shopify SEO scores.',
            ], 500);
        }
    }
}
