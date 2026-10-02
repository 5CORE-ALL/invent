<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\DirectStoreListingPublishService;
use App\Services\MarketplaceManager\ListingManagerPublishDispatcher;
use PHPUnit\Framework\TestCase;

class DirectStoreListingPublishChannelTest extends TestCase
{
    public function test_channel_names_map_to_direct_store_publishers(): void
    {
        $this->assertSame('pls', DirectStoreListingPublishService::channelFor('pls'));
        $this->assertSame('doba', DirectStoreListingPublishService::channelFor('doba'));
        $this->assertSame('b5cb2b', DirectStoreListingPublishService::channelFor('b5cb2b'));
        $this->assertSame('b5cb2b', DirectStoreListingPublishService::channelFor('business5core(b2b)'));
        $this->assertNull(DirectStoreListingPublishService::channelFor('business5core'));
    }

    public function test_listing_api_includes_new_channels(): void
    {
        $keys = ListingManagerPublishDispatcher::listingApiChannelKeys();
        foreach (['pls', 'doba', 'b5cb2b', 'business5core(b2b)'] as $key) {
            $this->assertContains($key, $keys);
        }
    }
}
