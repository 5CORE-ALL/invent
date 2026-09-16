<?php

namespace Tests\Unit;

use App\Models\ShopifySku;
use App\Services\ChannelLivePriceSync;
use Tests\TestCase;

class ShopifySkuLookupTest extends TestCase
{
    public function test_skus_match_nbsp_and_compact_variants(): void
    {
        $plain = 'WF 8120 4 OHM 2PCS';
        $nbsp = "WF\xC2\xA08120 4 OHM 2PCS";
        $compact = 'WF81204OHM2PCS';

        $this->assertTrue(ShopifySku::skusMatch($plain, $nbsp));
        $this->assertTrue(ShopifySku::skusMatch($plain, $compact));
        $this->assertSame('WF 8120 4 OHM 2PCS', ShopifySku::normalizeSkuForShopifyLookup($nbsp));
        $this->assertSame('WF81204OHM2PCS', ShopifySku::compactSkuForLookup($plain));
    }

    public function test_channel_live_price_sync_lookup_keys_include_norm_and_compact(): void
    {
        $keys = ChannelLivePriceSync::skuLookupKeys("WF\xC2\xA08120 4 OHM 2PCS");

        $this->assertContains('WF 8120 4 OHM 2PCS', $keys);
        $this->assertContains('WF81204OHM2PCS', $keys);
    }
}
