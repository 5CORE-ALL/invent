<?php

namespace Tests\Unit;

use App\Support\Marketplace\LmpMissingChannelCounts;
use App\Support\Marketplace\PriceGtLmpChannelCounts;
use PHPUnit\Framework\TestCase;

class PriceGtLmpChannelCountsTest extends TestCase
{
    public function test_resolve_key_maps_temu_channels_separately(): void
    {
        $this->assertSame('temu', PriceGtLmpChannelCounts::resolveKey('temu'));
        $this->assertSame('temu2', PriceGtLmpChannelCounts::resolveKey('temu2'));
        $this->assertSame('temu3', PriceGtLmpChannelCounts::resolveKey('temu3'));
        $this->assertSame('amazon', PriceGtLmpChannelCounts::resolveKey('amazon'));
        $this->assertNull(PriceGtLmpChannelCounts::resolveKey('unknown-channel'));
    }

    public function test_analytics_includes_temu3_decrease_page(): void
    {
        $analytics = LmpMissingChannelCounts::analytics();
        $this->assertArrayHasKey('temu3', $analytics);
        $this->assertSame('Temu 3', $analytics['temu3']['label']);
        $this->assertSame('/temu3-decrease', $analytics['temu3']['url']);
    }

    public function test_temu_listing_price_adds_shipping_at_or_below_26_99(): void
    {
        $this->assertSame(0.0, PriceGtLmpChannelCounts::temuListingPrice(0));
        $this->assertSame(29.98, PriceGtLmpChannelCounts::temuListingPrice(26.99));
        $this->assertSame(27.0, PriceGtLmpChannelCounts::temuListingPrice(27));
    }

    public function test_temu_recovery_lmp_matches_page_formula(): void
    {
        $this->assertSame(0.0, PriceGtLmpChannelCounts::temuRecoveryLmp(0));
        $this->assertSame(24.24, PriceGtLmpChannelCounts::temuRecoveryLmp(25));
        $this->assertSame(25.5, PriceGtLmpChannelCounts::temuRecoveryLmp(30));
    }

    public function test_red_triangle_matches_aliexpress_badge_rule(): void
    {
        $hit = [
            'sku' => 'AE-1',
            'inv' => 4,
            'price' => 20,
            'lmp' => 15,
            'lmp_entries' => [],
        ];
        $this->assertTrue(PriceGtLmpChannelCounts::rowHasRedTriangle($hit, 'price'));

        $zeroInv = $hit;
        $zeroInv['inv'] = 0;
        $this->assertFalse(PriceGtLmpChannelCounts::rowHasRedTriangle($zeroInv, 'price'));

        $offline = $hit;
        $offline['price'] = 0;
        $this->assertFalse(PriceGtLmpChannelCounts::rowHasRedTriangle($offline, 'price'));

        $ignoredOnly = [
            'sku' => 'AE-2',
            'inv' => 3,
            'price' => 20,
            'lmp' => 10,
            'lmp_entries' => [['price' => 10, 'ignored' => true]],
        ];
        $this->assertFalse(PriceGtLmpChannelCounts::rowHasRedTriangle($ignoredOnly, 'price'));
    }
}
