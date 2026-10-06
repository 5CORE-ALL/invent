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

    public function test_sold_units_follow_compact_sku_so_spaced_and_packed_spellings_add(): void
    {
        $sold = ShopifySku::indexSoldQuantitiesByCompact([
            (object) ['sku' => 'DME9PRPL', 'qty' => 1],
            ['sku' => 'DM-E9-PRPL', 'quantity' => 2],
        ]);

        $this->assertSame(3, ShopifySku::soldUnitsForSku('DM E9 PRPL', $sold));
        $this->assertSame(0, ShopifySku::soldUnitsForSku('OTHER SKU', $sold));
    }

    public function test_lookup_columns_include_live_inventory_fields(): void
    {
        foreach (['available_to_sell', 'inv', 'on_hand', 'committed', 'unavailable', 'incoming'] as $col) {
            $this->assertContains($col, ShopifySku::LOOKUP_COLUMNS);
        }
    }
}
