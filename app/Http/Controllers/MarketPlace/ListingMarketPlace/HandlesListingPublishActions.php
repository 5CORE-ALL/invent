<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;

use App\Services\MarketplaceManager\ListingVariationPreviewService;
use Illuminate\Http\Request;

trait HandlesListingPublishActions
{
    /**
     * Listing pages post preview/publish to the existing save-status URL so the
     * Shopify GET /{first}/{second} wildcard cannot claim the path.
     */
    protected function listingPublishResponse(Request $request)
    {
        $isPreview = $request->boolean('preview') || $request->input('action') === 'preview';
        $isPublish = $request->boolean('publish') || $request->input('action') === 'publish';
        if (! $isPreview && ! $isPublish) {
            return null;
        }

        $controller = app(ListingPublishCommonController::class);
        $service = app(ListingVariationPreviewService::class);

        return $isPreview
            ? $controller->preview($request, $service)
            : $controller->publish($request, $service);
    }
}
