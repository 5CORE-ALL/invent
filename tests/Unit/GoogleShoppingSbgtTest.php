<?php

namespace Tests\Unit;

use App\Support\GoogleShoppingBgtCvrRule;
use App\Support\GoogleShoppingBgtParts;
use App\Support\GoogleShoppingBgtPrcRule;
use App\Support\GoogleShoppingBgtViewsRule;
use App\Support\GoogleShoppingSbgt;
use PHPUnit\Framework\TestCase;

class GoogleShoppingSbgtTest extends TestCase
{
    public function test_sum_adds_present_parts(): void
    {
        $this->assertSame(10, GoogleShoppingSbgt::sumFromParts(2, 3, 4, 1));
        $this->assertSame(8, GoogleShoppingSbgt::sumFromParts(2, 3, 3, null));
    }

    public function test_explicit_bgt_acos_zero_zeros_the_total(): void
    {
        $this->assertSame(0, GoogleShoppingSbgt::sumFromParts(5, 4, 0, 3));
        $this->assertSame(0, GoogleShoppingSbgt::sumFromParts(null, null, 0, null));
    }

    public function test_all_missing_is_null(): void
    {
        $this->assertNull(GoogleShoppingSbgt::sumFromParts(null, null, null, null));
    }

    public function test_default_views_band_matches_amazon_logic(): void
    {
        $this->assertSame(6, GoogleShoppingBgtViewsRule::apply(400.0, GoogleShoppingBgtViewsRule::defaults())['bgt']);
        $this->assertSame(1, GoogleShoppingBgtViewsRule::apply(10.0, GoogleShoppingBgtViewsRule::defaults())['bgt']);
    }

    public function test_default_cvr_band_matches_amazon_logic(): void
    {
        $this->assertSame(6, GoogleShoppingBgtCvrRule::apply(22.0, GoogleShoppingBgtCvrRule::defaults())['bgt']);
        $this->assertSame(1, GoogleShoppingBgtCvrRule::apply(1.0, GoogleShoppingBgtCvrRule::defaults())['bgt']);
    }

    public function test_default_prc_band_matches_amazon_logic(): void
    {
        $this->assertSame(5, GoogleShoppingBgtPrcRule::apply(200.0, GoogleShoppingBgtPrcRule::defaults())['bgt']);
        $this->assertSame(1, GoogleShoppingBgtPrcRule::apply(30.0, GoogleShoppingBgtPrcRule::defaults())['bgt']);
        $this->assertNull(GoogleShoppingBgtPrcRule::apply(null, GoogleShoppingBgtPrcRule::defaults())['bgt']);
    }

    public function test_inventory_zero_or_negative_forces_sbgt_zero(): void
    {
        $row = ['sbgt' => 12];
        GoogleShoppingBgtParts::applyInventoryGate($row, null);
        $this->assertSame(12, $row['sbgt']);

        $row = ['sbgt' => 12, 'inventory' => 0];
        GoogleShoppingBgtParts::applyInventoryGate($row);
        $this->assertSame(0, $row['sbgt']);

        $row = ['sbgt' => 12, 'inventory' => -3];
        GoogleShoppingBgtParts::applyInventoryGate($row);
        $this->assertSame(0, $row['sbgt']);

        $row = ['sbgt' => 12];
        GoogleShoppingBgtParts::applyInventoryGate($row, 0);
        $this->assertSame(0, $row['sbgt']);

        $row = ['sbgt' => 12, 'inventory' => 4];
        GoogleShoppingBgtParts::applyInventoryGate($row);
        $this->assertSame(12, $row['sbgt']);
    }
}
