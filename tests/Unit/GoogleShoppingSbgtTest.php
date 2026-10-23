<?php

namespace Tests\Unit;

use App\Support\GoogleShoppingBgtCvrRule;
use App\Support\GoogleShoppingBgtParts;
use App\Support\GoogleShoppingBgtDilRule;
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

    public function test_bgt_acos_zero_adds_nothing_but_does_not_zero_the_total(): void
    {
        // e.g. the 40–9999 ACOS band has BGT ACOS 0: SBGT is still Views + Cvr + PRC.
        $this->assertSame(12, GoogleShoppingSbgt::sumFromParts(5, 4, 0, 3));
        $this->assertSame(1, GoogleShoppingSbgt::sumFromParts(1, 0, 0, null));
        // Nothing but zeros still sums to 0 (campaign is paused).
        $this->assertSame(0, GoogleShoppingSbgt::sumFromParts(null, null, 0, null));
        $this->assertSame(0, GoogleShoppingSbgt::sumFromParts(0, 0, 0, 0));
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
    public function test_inventory_gate_can_be_turned_off(): void
    {
        $row = ['sbgt' => 12, 'inventory' => 0];
        GoogleShoppingBgtParts::applyInventoryGate($row, null, false);
        $this->assertSame(12, $row['sbgt']);
    }

    public function test_price_cap_is_ten_percent_of_price(): void
    {
        $row = ['sbgt' => 50];
        GoogleShoppingBgtParts::applyPriceCap($row, 200.0, 10.0);
        $this->assertSame(20, $row['sbgt']);

        $row = ['sbgt' => 5];
        GoogleShoppingBgtParts::applyPriceCap($row, 200.0, 10.0);
        $this->assertSame(5, $row['sbgt']);

        $row = ['sbgt' => 50];
        GoogleShoppingBgtParts::applyPriceCap($row, 200.0, 0.0);
        $this->assertSame(50, $row['sbgt']);
    }

    public function test_dil_part_is_summed_and_negatives_are_allowed(): void
    {
        $this->assertSame(3, GoogleShoppingSbgt::sumFromParts(1, 1, 2, 1, -2));
        $this->assertSame(
            -4,
            GoogleShoppingBgtDilRule::apply(30.0, ['bands' => [
                ['dil_from' => 0, 'dil_to' => 100, 'bgt' => -4, 'label' => 'x', 'color' => '#000000'],
            ]])['bgt']
        );
    }

    public function test_price_below_threshold_zeros_sbgt(): void
    {
        $row = ['sbgt' => 8];
        GoogleShoppingBgtParts::applyPricePause($row, 19.99, 20.0);
        $this->assertSame(0, $row['sbgt']);

        $row = ['sbgt' => 8];
        GoogleShoppingBgtParts::applyPricePause($row, 20.0, 20.0);
        $this->assertSame(8, $row['sbgt']);

        $row = ['sbgt' => 8];
        GoogleShoppingBgtParts::applyPricePause($row, 5.0, 0.0);
        $this->assertSame(8, $row['sbgt']);

        $row = ['sbgt' => 8];
        GoogleShoppingBgtParts::applyPricePause($row, null, 20.0);
        $this->assertSame(8, $row['sbgt']);
    }
}
