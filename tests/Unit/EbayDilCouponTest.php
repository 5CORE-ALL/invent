<?php

namespace Tests\Unit;

use App\Support\EbayDilCoupon;
use PHPUnit\Framework\TestCase;

class EbayDilCouponTest extends TestCase
{
    public function test_zero_ov_l30_uses_the_zero_slab_and_inventory_zero_is_off(): void
    {
        $slabs = EbayDilCoupon::defaults();
        $slabs[0]['pct'] = 5;
        $slabs[1]['pct'] = 10;

        $this->assertSame(5, EbayDilCoupon::percentFor(0, 20, $slabs));
        $this->assertSame(0, EbayDilCoupon::percentFor(0, 0, $slabs));
        $this->assertSame(0, EbayDilCoupon::percentFor(4, 0, $slabs));
    }

    public function test_a_sale_that_is_not_zero_leaves_the_zero_slab(): void
    {
        $slabs = EbayDilCoupon::defaults();
        $slabs[0]['pct'] = 5;
        $slabs[1]['pct'] = 8;

        $this->assertSame(8, EbayDilCoupon::percentFor(1, 10000, $slabs));
        $this->assertSame(8, EbayDilCoupon::percentFor(1, 10, $slabs));
    }

    public function test_a_shared_boundary_stays_on_the_earlier_slab(): void
    {
        $slabs = EbayDilCoupon::defaults();
        $slabs[1]['pct'] = 8;
        $slabs[2]['pct'] = 12;

        $this->assertSame(8, EbayDilCoupon::percentFor(10, 100, $slabs));
        $this->assertSame(12, EbayDilCoupon::percentFor(10.01, 100, $slabs));
        $this->assertSame(12, EbayDilCoupon::percentFor(25, 100, $slabs));
    }

    public function test_percents_outside_five_to_eighty_are_off(): void
    {
        $this->assertSame(0, EbayDilCoupon::couponPercent(0));
        $this->assertSame(0, EbayDilCoupon::couponPercent(4));
        $this->assertSame(5, EbayDilCoupon::couponPercent(5));
        $this->assertSame(8, EbayDilCoupon::couponPercent(7.6));
        $this->assertSame(0, EbayDilCoupon::couponPercent(81));
    }

    public function test_legacy_percent_lands_on_the_zero_slab(): void
    {
        $slabs = EbayDilCoupon::fromLegacy(12);

        $this->assertSame(12, $slabs[0]['pct']);
        $this->assertSame(0, $slabs[1]['pct']);
        $this->assertSame(12, EbayDilCoupon::percentFor(0, 8, $slabs));
        $this->assertSame(0, EbayDilCoupon::percentFor(3, 10, $slabs));
    }

    public function test_normalize_drops_bad_rows_and_clamps_percent(): void
    {
        $slabs = EbayDilCoupon::normalize([
            ['min' => 25, 'max' => 10, 'pct' => 15],
            ['min' => 'nope', 'max' => 1, 'pct' => 5],
            ['min' => 0, 'max' => 0, 'pct' => 3],
        ]);

        $this->assertCount(2, $slabs);
        $this->assertSame(10.0, $slabs[0]['min']);
        $this->assertSame(25.0, $slabs[0]['max']);
        $this->assertSame(15, $slabs[0]['pct']);
        $this->assertSame(0, $slabs[1]['pct']);
    }
}
