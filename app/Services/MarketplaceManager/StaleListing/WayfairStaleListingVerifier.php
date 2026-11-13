<?php

namespace App\Services\MarketplaceManager\StaleListing;

/**
 * Wayfair "Listed" (ChannelListingRegistry::listedWayfair) is a SKU in wayfair_pricing_prices, or a
 * wayfair_listing_statuses row with value.listed = Listed / a buyer link / a listing id.
 *
 * This app has no Wayfair call that proves a listing is gone. WayfairApiService::lookupInventoryBySkus is a
 * fallback chain (supplier catalog, catalog inventory, purchase-order history) that is additive only: it can
 * confirm a SKU, but an empty answer is the same whether the SKU is gone, the part number is spelled
 * differently, the catalog read is not permitted or the SKU never had an order. Listing status rows and
 * price rows are also written by uploads, not only by the API. So nothing is cleared here, on purpose.
 */
class WayfairStaleListingVerifier implements StaleListingVerifier
{
    public function verify(): array
    {
        return [
            'ok' => true,
            'message' => 'Wayfair has no live listing lookup in this app; nothing was changed.',
            'checked' => 0,
            'cleared' => 0,
            'kept' => 0,
            'errors' => 0,
        ];
    }
}
