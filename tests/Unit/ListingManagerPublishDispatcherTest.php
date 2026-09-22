<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\ListingManagerPublishDispatcher;
use App\Services\MarketplaceManager\MiraklListingPublishService;
use App\Services\MarketplaceManager\TopDawgListingPublishService;
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

    public function test_topdawg_editor_has_category_tab(): void
    {
        $profile = ListingManagerEditorProfile::forChannel('TopDawg');
        $this->assertSame('topdawg', $profile['family']);
        $tabIds = array_map(static fn ($tab) => $tab['id'], $profile['tabs']);
        $this->assertContains('category', $tabIds);
        $this->assertContains('policies', $tabIds);
        $this->assertTrue($profile['topdawg']);
        $this->assertStringContainsString('package', strtolower($profile['policies_help']));
    }

    public function test_topdawg_placeholder_ids_are_not_live(): void
    {
        $sku = 'LS100-6 RED';
        $this->assertFalse(\App\Support\Marketplace\ChannelListingRegistry::isLiveTopDawgListingId('', $sku));
        $this->assertFalse(\App\Support\Marketplace\ChannelListingRegistry::isLiveTopDawgListingId($sku, $sku));
        $this->assertFalse(\App\Support\Marketplace\ChannelListingRegistry::isLiveTopDawgListingId('td-abc123def456', $sku));
        $this->assertTrue(\App\Support\Marketplace\ChannelListingRegistry::isLiveTopDawgListingId('TD123456', $sku));
    }

    public function test_shein_editor_has_category_search(): void
    {
        $this->assertSame('shein', ListingManagerEditorProfile::family('shein'));

        $profile = ListingManagerEditorProfile::forChannel('Shein');
        $this->assertSame('shein', $profile['family']);
        $this->assertTrue($profile['shein']);
        $tabIds = array_map(static fn ($tab) => $tab['id'], $profile['tabs']);
        $this->assertContains('category', $tabIds);
        $this->assertStringContainsString('Search Shein', $profile['category_placeholder']);
        $this->assertStringContainsString('leaf', $profile['category_help']);
    }

    public function test_aliexpress_editor_has_category_search(): void
    {
        $this->assertSame('aliexpress', ListingManagerEditorProfile::family('aliexpress'));

        $profile = ListingManagerEditorProfile::forChannel('Aliexpress');
        $this->assertSame('aliexpress', $profile['family']);
        $this->assertTrue($profile['aliexpress']);
        $tabIds = array_map(static fn ($tab) => $tab['id'], $profile['tabs']);
        $this->assertContains('category', $tabIds);
        $this->assertStringContainsString('Search AliExpress', $profile['category_placeholder']);
        $this->assertStringContainsString('leaf', $profile['category_help']);
        $this->assertTrue($profile['category_search']);
        $this->assertTrue($profile['category_manual']);
        $this->assertSame('aliexpress', ListingManagerEditorProfile::family('ae'));
        $this->assertSame('aliexpress', ListingManagerEditorProfile::family('aliexpresscom'));
    }

    public function test_topdawg_category_search_and_resolve(): void
    {
        $all = TopDawgListingPublishService::searchListingCategories('');
        $this->assertTrue($all['success']);
        $this->assertNotEmpty($all['categories']);

        $mics = TopDawgListingPublishService::searchListingCategories('microphone');
        $this->assertNotEmpty($mics['categories']);
        $foundMic = false;
        foreach ($mics['categories'] as $row) {
            if (str_contains(strtolower((string) ($row['path'] ?? '')), 'microphone')) {
                $foundMic = true;
                break;
            }
        }
        $this->assertTrue($foundMic);

        $resolved = TopDawgListingPublishService::resolveCategory('Electronics|Music|Light Stands', 'Electronics > Music > Light Stands');
        $this->assertSame([
            'dept' => 'Electronics',
            'section' => 'Music',
            'category' => 'Light Stands',
        ], $resolved);
    }
}
