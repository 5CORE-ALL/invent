<?php

namespace Tests\Unit;

use App\Support\EbayCvrPriceRule;
use PHPUnit\Framework\TestCase;

/** eBay 1 / 2 / 3 run the same CVR Price Rule as Amazon (see AmazonCvrPriceRuleUpperCapTest). */
class EbayCvrPriceRuleAmazonStyleTest extends TestCase
{
    private function cfg(array $over = []): array
    {
        return array_merge([
            'enabled' => 1, 'down' => 1, 'same' => null, 'up' => 1, 'pink' => null, 'pink_above' => null,
            'lower_cap' => 0, 'upper_cap' => 0, 'down_band' => 20, 'up_band' => 20, 'amazon_style' => true,
        ], $over);
    }

    private function input(array $over = []): array
    {
        return array_merge([
            'price' => 100.0, 'cvr' => 3.0, 'lto_cvr' => 5.0, 'ebay_l30' => 4.0,
            'lp' => 40.0, 'ship' => 5.0, 'percentage' => 0.8, 'ads' => 0.05,
        ], $over);
    }

    public function test_amazon_style_flag_for_ebay_channels_only(): void
    {
        $this->assertTrue(EbayCvrPriceRule::amazonStyle('ebay1'));
        $this->assertTrue(EbayCvrPriceRule::amazonStyle('ebay2'));
        $this->assertTrue(EbayCvrPriceRule::amazonStyle('ebay3'));
        $this->assertFalse(EbayCvrPriceRule::amazonStyle('shopify_b2c'));
    }

    public function test_cvr_down_below_lifetime_lowers_price(): void
    {
        $hit = EbayCvrPriceRule::apply(100.0, $this->input(), $this->cfg());
        $this->assertSame(99.0, $hit['price']);
    }

    public function test_no_lifetime_cvr_starts_at_zero_and_sold_zero_is_down(): void
    {
        $hit = EbayCvrPriceRule::apply(100.0, $this->input(['lto_cvr' => null, 'cvr' => 0.0, 'ebay_l30' => 0.0]), $this->cfg());
        $this->assertSame(99.0, $hit['price']);
        // CVR 0% and no lifetime CVR but a sale: 0 is not below 0, so no change.
        $this->assertNull(EbayCvrPriceRule::apply(100.0, $this->input(['lto_cvr' => null, 'cvr' => 0.0, 'ebay_l30' => 2.0]), $this->cfg()));
    }

    public function test_cvr_between_7_and_13_is_within_target(): void
    {
        $this->assertNull(EbayCvrPriceRule::apply(100.0, $this->input(['cvr' => 9.0, 'lto_cvr' => 30.0]), $this->cfg()));
        $hit = EbayCvrPriceRule::apply(100.0, $this->input(['cvr' => 9.0, 'lto_cvr' => 30.0]), $this->cfg(['same' => 2]));
        $this->assertSame(102.0, $hit['price']);
    }

    public function test_pink_slab_raises_price(): void
    {
        $hit = EbayCvrPriceRule::apply(100.0, $this->input(['cvr' => 15.0, 'lto_cvr' => 30.0]), $this->cfg(['pink' => 3]));
        $this->assertSame(103.0, $hit['price']);
    }

    public function test_upper_cap_brings_price_down_to_the_ceiling(): void
    {
        $ceil = EbayCvrPriceRule::ceilingPrice($this->input(), ['max_nroi' => 30]);
        $this->assertNotNull($ceil);
        $hit = EbayCvrPriceRule::apply(60.0, $this->input(['cvr' => 20.0, 'lto_cvr' => 5.0, 'price' => 60.0]), $this->cfg(['up' => 50, 'upper_cap' => 1, 'max_nroi' => 30]));
        $this->assertSame(round($ceil, 2), $hit['price']);
        $this->assertTrue($hit['capped']);
        // CVR Up never lowers the price: a base already above the ceiling is left alone.
        $this->assertNull(EbayCvrPriceRule::apply(100.0, $this->input(['cvr' => 20.0, 'lto_cvr' => 5.0]), $this->cfg(['up' => 50, 'upper_cap' => 1, 'max_nroi' => 30])));
    }

    public function test_within_target_with_blank_percent_still_gets_caps(): void
    {
        $floor = EbayCvrPriceRule::floorPrice($this->input(), ['min_nroi' => 80]);
        $hit = EbayCvrPriceRule::apply(60.0, $this->input(['cvr' => 9.0, 'lto_cvr' => 9.0, 'price' => 60.0]), $this->cfg(['lower_cap' => 1, 'min_nroi' => 80]));
        $this->assertSame(round($floor, 2), $hit['price']);
    }

    public function test_no_std_ceiling_when_running_alone(): void
    {
        $this->assertSame(110.0, EbayCvrPriceRule::noCeiling(110.0, 100.0));
        $this->assertNull(EbayCvrPriceRule::noCeiling(100.0, 100.0));
    }

    public function test_old_rule_unchanged_without_flag(): void
    {
        $cfg = $this->cfg();
        unset($cfg['amazon_style']);
        // The first version needs a lifetime CVR and has no 7%..13% exception.
        $this->assertNull(EbayCvrPriceRule::apply(100.0, $this->input(['lto_cvr' => null]), $cfg));
        $hit = EbayCvrPriceRule::apply(100.0, $this->input(['cvr' => 9.0, 'lto_cvr' => 30.0]), $cfg);
        $this->assertSame(99.0, $hit['price']);
    }
}
