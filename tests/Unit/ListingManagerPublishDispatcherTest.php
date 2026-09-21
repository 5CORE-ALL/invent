<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\ListingManagerPublishDispatcher;
use App\Services\MarketplaceManager\MiraklListingPublishService;
use App\Support\Marketplace\ListingManagerEditorProfile;
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
            'Macys',
            "Macy's",
            'Best Buy USA',
            'BestBuy USA',
            'Purchasing Power',
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
            'Doba',
            'Walmart',
            'Shopify',
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

    public function test_macy_bestbuy_purchasing_power_use_mirakl_editor(): void
    {
        foreach (['Macys', 'Best Buy USA', 'Purchasing Power'] as $channel) {
            $this->assertTrue(MiraklListingPublishService::isMiraklListingChannel($channel));
            $this->assertSame('mirakl', ListingManagerEditorProfile::family(
                \App\Support\Marketplace\ListingChannelCounts::normalize($channel)
            ));
        }
    }

    public function test_topdawg_editor_has_no_category_tab(): void
    {
        $profile = ListingManagerEditorProfile::forChannel('TopDawg');
        $this->assertSame('topdawg', $profile['family']);
        $tabIds = array_map(static fn ($tab) => $tab['id'], $profile['tabs']);
        $this->assertNotContains('category', $tabIds);
    }
}
