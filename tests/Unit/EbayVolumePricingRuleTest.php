<?php

namespace Tests\Unit;

use App\Support\EbayVolumePricingRule;
use PHPUnit\Framework\TestCase;

class EbayVolumePricingRuleTest extends TestCase
{
    public function test_sum_adds_weight_dil_and_npft_into_each_buy_column(): void
    {
        $rules = EbayVolumePricingRule::defaults();
        $rules['weight'][4]['buy2'] = 2;
        $rules['weight'][4]['buy3'] = 3;
        $rules['weight'][4]['buy4'] = 4;
        $rules['dil'][2]['buy2'] = 1;
        $rules['dil'][2]['buy3'] = 2;
        $rules['dil'][2]['buy4'] = 3;
        $rules['npft'][2]['buy2'] = 1;
        $rules['npft'][2]['buy3'] = 1;
        $rules['npft'][2]['buy4'] = 2;

        $sum = EbayVolumePricingRule::sum(1.5, 12.0, 15.0, $rules);

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
        $rules = EbayVolumePricingRule::defaults();
        $sum = EbayVolumePricingRule::sum(null, 0.0, null, $rules);

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

    public function test_ebay_tiers_must_be_consecutive_from_buy_2(): void
    {
        $this->assertSame([], EbayVolumePricingRule::ebayTiers(['buy2' => 0, 'buy3' => 10, 'buy4' => 20]));
        $this->assertSame([
            ['qty' => 2, 'percent' => 6.0],
        ], EbayVolumePricingRule::ebayTiers(['buy2' => 6, 'buy3' => 0, 'buy4' => 25]));
    }

    public function test_listing_row_matches_the_page_math_and_skips_parents(): void
    {
        $row = EbayVolumePricingRule::listingRow([
            '(Child) sku' => 'CS 06 2W',
            'eBay_item_id' => '235983162568',
            'INV' => 200,
            'L30' => 50,
            'STANDARD_PRICE' => 100,
            'LP_productmaster' => 20,
            'Ship_productmaster' => 10,
            'wt_act' => '1.5',
        ]);

        $this->assertSame('235983162568', $row['item_id']);
        $this->assertSame(25.0, $row['dil']);
        $this->assertSame(40.0, $row['npft']);
        $this->assertSame(1.5, $row['weight_lb']);
        $this->assertNull(EbayVolumePricingRule::listingRow(['(Child) sku' => 'PARENT CS 06', 'eBay_item_id' => '1']));
        $this->assertNull(EbayVolumePricingRule::listingRow(['(Child) sku' => 'CS 06', 'eBay_item_id' => '']));
        $this->assertNull(EbayVolumePricingRule::listingRow(['(Child) sku' => 'CS 06', 'eBay_item_id' => '9', 'is_parent_summary' => true]));
        $this->assertSame(0.0, EbayVolumePricingRule::listingRow(['(Child) sku' => 'A', 'eBay_item_id' => '9', 'INV' => 0, 'L30' => 5])['dil']);
        $this->assertNull(EbayVolumePricingRule::listingRow(['(Child) sku' => 'A', 'eBay_item_id' => '9', 'STANDARD_PRICE' => 0])['npft']);
    }

    public function test_auto_push_flag_and_promotion_state_survive_save_and_load(): void
    {
        $this->assertFalse(EbayVolumePricingRule::defaults()['auto_push']);

        $rules = EbayVolumePricingRule::merge([
            'enabled' => true,
            'auto_push' => true,
            'promotion_ids' => ['2=2|3=8|4=15' => '1616684486303@EBAY_US'],
            'promotion_state' => ['2=2|3=8|4=15' => 'abc123', 'bad' => ''],
        ]);

        $this->assertTrue($rules['auto_push']);
        $this->assertSame(['2=2|3=8|4=15' => 'abc123'], $rules['promotion_state']);
        $this->assertSame(['2=2|3=8|4=15' => '1616684486303@EBAY_US'], $rules['promotion_ids']);
    }

    public function test_custom_weight_min_max_is_kept_and_old_slab_keys_convert(): void
    {
        $custom = EbayVolumePricingRule::merge([
            'enabled' => true,
            'weight' => [
                ['min' => 0.5, 'max' => 2, 'buy2' => 8, 'buy3' => 9, 'buy4' => 10],
            ],
        ]);
        $this->assertCount(1, $custom['weight']);
        $this->assertSame(0.5, $custom['weight'][0]['min']);
        $this->assertSame(8.0, EbayVolumePricingRule::sum(1.0, 0.0, null, $custom)['buy2']);

        $legacy = EbayVolumePricingRule::merge([
            'weight' => [
                ['key' => 'lb_0', 'label' => '0 lb', 'buy2' => 4, 'buy3' => 4, 'buy4' => 4],
                ['key' => 'oz_4', 'label' => '0.01–4 oz', 'buy2' => 10, 'buy3' => 15, 'buy4' => 20],
            ],
        ]);
        $this->assertSame(0.01, $legacy['weight'][0]['min']);
        $this->assertSame(10.0, $legacy['weight'][0]['buy2']);
        $this->assertSame(0.0, EbayVolumePricingRule::sum(0.0, 0.0, null, $legacy)['buy2']);
    }
}
