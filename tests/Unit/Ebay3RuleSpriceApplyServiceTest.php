<?php

namespace Tests\Unit;

use App\Services\Ebay3RuleSpriceApplyService;
use App\Services\EbayRuleSpriceApplyService;
use App\Support\AmazonDilGroiRule;
use Tests\TestCase;

class Ebay3RuleSpriceApplyServiceTest extends TestCase
{
    public function test_dil_in_slab_uses_target_groi_and_takehome(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 3,
            'cvr' => 8,
            'lp' => 20,
            'ship' => 0,
            'lmp' => 0,
        ]);

        $this->assertNotNull($out);
        // GROI 50% (0.1–5 slab), no CVR adj (8 is between 7 and 10): (20*1.5)/0.80 = 37.50
        $this->assertEqualsWithDelta(37.50, $out['sprice'], 0.01);
        $this->assertEqualsWithDelta(50.0, $out['groi'], 0.01);
    }

    public function test_cvr_down_lowers_groi(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 3,
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

    public function test_zero_dil_uses_first_slab(): void
    {
        $out = $this->compute([
            'inv' => 5,
            'dil' => 0,
            'cvr' => 8,
            'lp' => 10,
            'ship' => 2,
            'lmp' => 0,
        ]);

        $this->assertNotNull($out);
        // first slab GROI 50: (10*1.5 + 2)/0.80 = 21.25
        $this->assertEqualsWithDelta(21.25, $out['sprice'], 0.01);
    }

    public function test_inv_zero_skips(): void
    {
        $this->assertNull($this->compute([
            'inv' => 0,
            'dil' => 3,
            'cvr' => 8,
            'lp' => 20,
            'ship' => 0,
            'lmp' => 0,
        ]));
    }

    public function test_dil_below_lmp_keeps_dil_price_after_cvr_overlay(): void
    {
        $rules = [AmazonDilGroiRule::make(50, 60, 100)];
        $out = EbayRuleSpriceApplyService::for('ebay1')->computeTarget(
            [
                'inv' => 10,
                'dil' => 51,
                'cvr' => 3.77,
                'lp' => 7,
                'ship' => 1.748,
                'lmp' => 24.95,
            ],
            $rules,
            AmazonDilGroiRule::defaultCvrAdj(),
            0.80
        );

        $this->assertNotNull($out);
        // Dil 51 → GROI 100, CVR 3.77 < 7 → 90. Dil $18.81 < LMP $24.95 → no cap.
        $this->assertEqualsWithDelta(90.0, $out['groi'], 0.01);
        $this->assertEqualsWithDelta(18.81, $out['sprice'], 0.01);
    }

    public function test_lmp_caps_when_sgroi_at_lmp_is_at_least_20(): void
    {
        $svc = EbayRuleSpriceApplyService::for('ebay1');
        // raw 50, LMP 30, SGROI at 30 = (30*0.80 - 10)/10*100 = 140 ≥ 20
        $this->assertEqualsWithDelta(30.0, $svc->capToLmp(50, 30, 10, 0, 0.80), 0.01);
    }

    public function test_lmp_does_not_cap_when_sgroi_at_lmp_below_20(): void
    {
        $svc = EbayRuleSpriceApplyService::for('ebay2');
        // LMP 12, SGROI = (12*0.80 - 10)/10*100 = −4 < 20
        $this->assertEqualsWithDelta(50.0, $svc->capToLmp(50, 12, 10, 0, 0.80), 0.01);
    }

    public function test_same_dil_formula_on_ebay1_and_ebay2(): void
    {
        $row = [
            'inv' => 10,
            'dil' => 3,
            'cvr' => 8,
            'lp' => 20,
            'ship' => 0,
            'lmp' => 0,
        ];
        $rules = AmazonDilGroiRule::defaults();
        $adj = AmazonDilGroiRule::defaultCvrAdj();
        $a = EbayRuleSpriceApplyService::for('ebay1')->computeTarget($row, $rules, $adj, 0.80);
        $b = EbayRuleSpriceApplyService::for('ebay2')->computeTarget($row, $rules, $adj, 0.80);
        $c = (new Ebay3RuleSpriceApplyService)->computeTarget($row, $rules, $adj, 0.80);
        $this->assertEquals($a, $b);
        $this->assertEquals($a, $c);
        $this->assertEqualsWithDelta(37.50, $a['sprice'], 0.01);
    }

    public function test_channels_from_arg(): void
    {
        $this->assertSame(['ebay1', 'ebay2', 'ebay3'], EbayRuleSpriceApplyService::channelsFromArg('all'));
        $this->assertSame(['ebay1'], EbayRuleSpriceApplyService::channelsFromArg('ebay'));
        $this->assertSame([], EbayRuleSpriceApplyService::channelsFromArg('temu'));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{sprice: float, groi: float}|null
     */
    private function compute(array $row): ?array
    {
        return (new Ebay3RuleSpriceApplyService)->computeTarget(
            $row,
            AmazonDilGroiRule::defaults(),
            AmazonDilGroiRule::defaultCvrAdj(),
            0.80
        );
    }
}
