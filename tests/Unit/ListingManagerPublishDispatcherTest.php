<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\ListingManagerPublishDispatcher;
use PHPUnit\Framework\TestCase;

class ListingManagerPublishDispatcherTest extends TestCase
{
    public function test_supports_every_marketplace_with_a_listing_api(): void
    {
        $supported = [
            'Amazon',
            'Amazon FBA',
            'eBay',
            'EbayTwo',
            'EbayThree',
            'Temu',
            'Temu 2',
            'Faire',
            'Reverb',
            'Wayfair',
            'Aliexpress',
            'Tiktok Shop',
            'TikTok 2',
            'Shein',
            'Newegg',
            'Newegg B2C',
            'Newegg B2B',
            'TopDawg',
        ];

        foreach ($supported as $channel) {
            $this->assertTrue(
                ListingManagerPublishDispatcher::supportsListingApi($channel),
                $channel.' should be selectable for listing-manager API publish'
            );
        }
    }

    public function test_rejects_sheet_or_order_only_marketplaces(): void
    {
        $unsupported = [
            'BestBuy USA',
            'Doba',
            'Macys',
            'Walmart',
            'Shopify',
            'Purchasing Power',
            'FB Marketplace',
            'Depop',
            'Mercari w ship',
            'Vinted',
            'Alibaba',
        ];

        foreach ($unsupported as $channel) {
            $this->assertFalse(
                ListingManagerPublishDispatcher::supportsListingApi($channel),
                $channel.' has no listing-create API and must stay out of the picker'
            );
        }
    }
}
