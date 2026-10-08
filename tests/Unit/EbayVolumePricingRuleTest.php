<?php

namespace Tests\Unit;

use App\Support\EbayVolumePricingRule;
use PHPUnit\Framework\TestCase;

class EbayVolumePricingRuleTest extends TestCase
{
    public function test_sum_adds_weight_dil_and_npft_into_each_buy_column(): void
    {
        $rules = EbayVolumePricingRule::defaults([
            ['key' => 'lb_0', 'label' => '0 lb'],
            ['key' => 'lb_101_2', 'label' => '1 lb – 2 lb'],
        ]);
        $rules['weight'][1]['buy2'] = 2;
        $rules['weight'][1]['buy3'] = 3;
        $rules['weight'][1]['buy4'] = 4;
        $rules['dil'][2]['buy2'] = 1;
        $rules['dil'][2]['buy3'] = 2;
        $rules['dil'][2]['buy4'] = 3;
        $rules['npft'][2]['buy2'] = 1;
        $rules['npft'][2]['buy3'] = 1;
        $rules['npft'][2]['buy4'] = 2;

        $sum = EbayVolumePricingRule::sum('lb_101_2', 12.0, 15.0, $rules);

        $this->assertSame(4.0, $sum['buy2']);
        $this->assertSame(6.0, $sum['buy3']);
        $this->assertSame(9.0, $sum['buy4']);
        $this->assertSame(2.0, $sum['parts']['weight']['buy2']);
        $this->assertSame(1.0, $sum['parts']['dil']['buy2']);
        $this->assertSame(1.0, $sum['parts']['npft']['buy2']);
    }

    public function test_range_that_starts_where_the_previous_ended_is_exclusive_on_from(): void
    {
        $ranges = [
            ['min' => 0, 'max' => 10, 'buy2' => 1, 'buy3' => 1, 'buy4' => 1],
            ['min' => 10, 'max' => 25, 'buy2' => 5, 'buy3' => 5, 'buy4' => 5],
        ];

        $this->assertSame(1.0, EbayVolumePricingRule::rangeTier(10.0, $ranges)['buy2']);
        $this->assertSame(5.0, EbayVolumePricingRule::rangeTier(10.01, $ranges)['buy2']);
        $this->assertSame(1.0, EbayVolumePricingRule::rangeTier(0.0, [
            ['min' => 0, 'max' => 0, 'buy2' => 1, 'buy3' => 0, 'buy4' => 0],
            ['min' => 0.01, 'max' => 10, 'buy2' => 9, 'buy3' => 0, 'buy4' => 0],
        ])['buy2']);
    }

    public function test_missing_weight_slab_adds_zero(): void
    {
        $rules = EbayVolumePricingRule::defaults([['key' => 'lb_0', 'label' => '0 lb']]);
        $sum = EbayVolumePricingRule::sum('lb_gt50', 0.0, null, $rules);

        $this->assertSame(0.0, $sum['buy2']);
        $this->assertSame(0.0, $sum['buy3']);
        $this->assertSame(0.0, $sum['buy4']);
    }

    public function test_ebay_tiers_drop_zeros_and_keep_later_tiers_strictly_higher(): void
    {
        $tiers = EbayVolumePricingRule::ebayTiers(['buy2' => 5, 'buy3' => 5, 'buy4' => 0]);

        $this->assertSame([
            ['qty' => 2, 'percent' => 5.0],
            ['qty' => 3, 'percent' => 5.1],
        ], $tiers);
        $this->assertSame('2=5|3=5.1', EbayVolumePricingRule::signature($tiers));
        $this->assertSame([], EbayVolumePricingRule::ebayTiers(['buy2' => 0, 'buy3' => 0, 'buy4' => -2]));
    }
}
