<?php

namespace Tests\Unit;

use App\Support\Marketplace\LmpMissingChannelCounts;
use App\Support\Marketplace\PriceGtLmpChannelCounts;
use App\Support\Marketplace\PriceGtLmpPageCounts;
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
        $this->assertArrayHasKey('bestbuy', $analytics);
        $this->assertSame('BestBuy USA', $analytics['bestbuy']['label']);
        $this->assertSame('/bestbuy-pricing', $analytics['bestbuy']['url']);
        $this->assertSame('bestbuy', PriceGtLmpChannelCounts::resolveKey('bestbuyusa'));
    }

    public function test_temu_norm_folds_piece_count(): void
    {
        $this->assertSame('MS 080 WH 2PC', PriceGtLmpPageCounts::temuNorm('MS 080 WH 2 PCS'));
        $this->assertSame('MS 080 WH 2PC', PriceGtLmpPageCounts::temuNorm('ms 080  wh  2pcs'));
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

    public function test_red_triangle_uses_amazon_landed_lmp_from_entries(): void
    {
        $hit = [
            '(Child) sku' => 'AMZ-1',
            'INV' => 2,
            'price' => 29.99,
            'lmp_price' => 25.00,
            'lmp_entries' => [
                ['price' => 24.00, 'landed_price' => 28.00, 'ignored' => 0],
                ['price' => 20.00, 'landed_price' => 22.00, 'ignored' => 1],
            ],
        ];
        $this->assertTrue(PriceGtLmpChannelCounts::rowHasRedTriangle($hit, 'price'));

        $notOver = $hit;
        $notOver['price'] = 27.50;
        $this->assertFalse(PriceGtLmpChannelCounts::rowHasRedTriangle($notOver, 'price'));
    }

    public function test_red_triangle_uses_bestbuy_price_field(): void
    {
        $hit = [
            '(Child) sku' => 'BB-1',
            'INV' => 5,
            'BB Price' => 49.99,
            'lmp_price' => 44.00,
            'lmp_entries' => [],
        ];
        $this->assertTrue(PriceGtLmpChannelCounts::rowHasRedTriangle($hit, 'BB Price'));

        $notOver = $hit;
        $notOver['BB Price'] = 40.00;
        $this->assertFalse(PriceGtLmpChannelCounts::rowHasRedTriangle($notOver, 'BB Price'));
    }
}
