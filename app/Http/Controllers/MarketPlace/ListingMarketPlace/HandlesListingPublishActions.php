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
    protected function listingPublishChannel(): string
    {
        return '';
    }

    protected function listingPublishResponse(Request $request)
    {
        if ($request->input('action') === 'ebay_required_specifics') {
            if (trim((string) $request->input('channel', '')) === '' && trim($this->listingPublishChannel()) !== '') {
                $request->merge(['channel' => trim($this->listingPublishChannel())]);
            }

            return app(ListingPublishCommonController::class)->ebayRequiredSpecifics(
                $request,
                app(\App\Services\MarketplaceManager\EbayListingPublishService::class)
            );
        }

        $isPreview = $request->boolean('preview') || $request->input('action') === 'preview';
        $isPublish = $request->boolean('publish') || $request->input('action') === 'publish';
        if (! $isPreview && ! $isPublish) {
            return null;
        }

        if (trim((string) $request->input('channel', '')) === '') {
            $defaultChannel = trim($this->listingPublishChannel());
            if ($defaultChannel !== '') {
                $request->merge(['channel' => $defaultChannel]);
            }
        }

        $controller = app(ListingPublishCommonController::class);
        $service = app(ListingVariationPreviewService::class);

        return $isPreview
            ? $controller->preview($request, $service)
            : $controller->publish($request, $service);
    }
}
