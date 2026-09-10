<?php

namespace Tests\Unit;

use App\Services\DilRuleSpriceApplyService;
use App\Support\AmazonDilGroiRule;
use Tests\TestCase;

class DilRuleSpriceApplyServiceTest extends TestCase
{
    public function test_dil_in_slab_uses_target_groi_and_takehome(): void
    {
        $out = $this->compute('bestbuy', [
            'inv' => 10,
            'dil' => 3,
            'ov_l30' => 2,
            'cvr' => 8,
            'lp' => 20,
            'ship' => 0,
            'lmp' => 0,
        ]);

        $this->assertNotNull($out);
        // GROI 50% (0.1–5 slab): (20*1.5)/0.80 = 37.50
        $this->assertEqualsWithDelta(37.50, $out['sprice'], 0.01);
        $this->assertEqualsWithDelta(50.0, $out['groi'], 0.01);
    }

    public function test_zero_sold_uses_min_target_groi(): void
    {
        $rules = [
            AmazonDilGroiRule::make(0.1, 5.0, 80),
            AmazonDilGroiRule::make(5.0, 10.0, 40),
        ];
        $out = DilRuleSpriceApplyService::for('bestbuy')->computeTarget(
            [
                'inv' => 8,
                'dil' => 0,
                'ov_l30' => 0,
                'cvr' => 8,
                'lp' => 20,
                'ship' => 0,
                'lmp' => 0,
            ],
            $rules,
            AmazonDilGroiRule::defaultCvrAdj(),
            0.80
        );

        $this->assertNotNull($out);
        // min slab GROI is 40, not the 0.1–5 / 80 slab: (20*1.4)/0.80 = 35.00
        $this->assertEqualsWithDelta(35.00, $out['sprice'], 0.01);
        $this->assertEqualsWithDelta(40.0, $out['groi'], 0.01);
    }

    public function test_temu2_zero_dil_uses_first_slab_not_min_skip(): void
    {
        $out = $this->compute('temu2', [
            'inv' => 5,
            'dil' => 0,
            'ov_l30' => 0,
            'cvr' => 8,
            'lp' => 10,
            'ship' => 2,
            'lmp' => 0,
        ]);

        $this->assertNotNull($out);
        // first slab GROI 50, no CVR adj (8 is between 7 and 10): (10*1.5 + 2)/0.80 = 21.25
        $this->assertEqualsWithDelta(21.25, $out['sprice'], 0.01);
    }

    public function test_exclude_ship_on_wayfair(): void
    {
        $out = $this->compute('wayfair', [
            'inv' => 10,
            'dil' => 3,
            'ov_l30' => 2,
            'cvr' => 8,
            'lp' => 20,
            'ship' => 8,
            'lmp' => 0,
        ]);

        $this->assertNotNull($out);
        // ship ignored: (20*1.5)/0.80 = 37.50
        $this->assertEqualsWithDelta(37.50, $out['sprice'], 0.01);
    }

    public function test_cvr_down_lowers_groi_on_temu(): void
    {
        $out = $this->compute('temu', [
            'inv' => 10,
            'dil' => 3,
            'ov_l30' => 2,
            'cvr' => 5,
            'lp' => 20,
            'ship' => 0,
            'lmp' => 0,
        ]);

        $this->assertNotNull($out);
        // 50 + (−10) = 40 → (20*1.4)/0.80 = 35.00
        $this->assertEqualsWithDelta(35.00, $out['sprice'], 0.01);
        $this->assertEqualsWithDelta(40.0, $out['groi'], 0.01);
    }

    public function test_newegg_raises_to_amz_floor(): void
    {
        $out = $this->compute('newegg', [
            'inv' => 10,
            'dil' => 3,
            'ov_l30' => 2,
            'cvr' => 8,
            'lp' => 20,
            'ship' => 0,
            'lmp' => 0,
            'amz_price' => 40,
        ]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(40.0, $out['sprice'], 0.01);
    }

    public function test_inv_zero_skips(): void
    {
        $this->assertNull($this->compute('bestbuy', [
            'inv' => 0,
            'dil' => 3,
            'ov_l30' => 2,
            'lp' => 20,
            'ship' => 0,
        ]));
    }

    public function test_sold_out_of_slab_skips_on_min_groi_channels(): void
    {
        $this->assertNull($this->compute('bestbuy', [
            'inv' => 10,
            'dil' => 80,
            'ov_l30' => 5,
            'lp' => 20,
            'ship' => 0,
        ]));
    }

    public function test_channels_from_arg(): void
    {
        $this->assertSame(DilRuleSpriceApplyService::CHANNELS, DilRuleSpriceApplyService::channelsFromArg('all'));
        $this->assertSame(['bestbuy'], DilRuleSpriceApplyService::channelsFromArg('bb'));
        $this->assertSame(['fb_marketplace'], DilRuleSpriceApplyService::channelsFromArg('fb'));
        $this->assertSame(['walmart'], DilRuleSpriceApplyService::channelsFromArg('wm'));
        $this->assertSame(['pls'], DilRuleSpriceApplyService::channelsFromArg('pls'));
        $this->assertSame([], DilRuleSpriceApplyService::channelsFromArg('ebay3'));
        $this->assertNotContains('fb_marketplace', DilRuleSpriceApplyService::PUSH_CHANNELS);
        $this->assertContains('walmart', DilRuleSpriceApplyService::PUSH_CHANNELS);
        $this->assertContains('pls', DilRuleSpriceApplyService::PUSH_CHANNELS);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{sprice: float, groi: float}|null
     */
    private function compute(string $channel, array $row): ?array
    {
        return DilRuleSpriceApplyService::for($channel)->computeTarget(
            $row,
            AmazonDilGroiRule::defaults(),
            AmazonDilGroiRule::defaultCvrAdj(),
            0.80
        );
    }
}
