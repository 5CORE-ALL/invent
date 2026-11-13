<?php

namespace App\Services\MarketplaceManager\StaleListing;

/**
 * "Fetch listings" step that asks one marketplace's API about the SKUs the app currently calls Listed, and
 * takes the Listed state away from every SKU the marketplace definitively no longer has (deleted, ended,
 * dropped from its listing), so the SKU shows on Missing L and is listed again as a new product.
 *
 * Rules every implementation follows:
 *  - Only the SKUs of its own marketplace are touched.
 *  - A SKU loses its listing id only on a definite "not found / ended / not this SKU" answer from the API.
 *    Timeouts, auth errors, rate limits, malformed or empty responses mean "unknown": keep it, count it as an error.
 *  - After repeated failures (5 in a row) stop and report; a short or partial list never clears anything
 *    (use the guards of MarketplaceLinkMapPruner when working from a full catalog pull).
 *  - Clear exactly what the Listed rule of that channel reads (ChannelListingRegistry 'listed' config /
 *    ListingCountsEngine), so the SKU really flips to Missing L.
 */
interface StaleListingVerifier
{
    /**
     * @return array{ok: bool, message: string, checked: int, cleared: int, kept: int, errors: int}
     */
    public function verify(): array;
}
