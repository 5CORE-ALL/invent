<?php

namespace Tests\Unit;

use App\Services\AmazonSprcDilAutoPushService;
use App\Support\AmazonDilGroiRule;
use Tests\TestCase;

class AmazonSprcDilAutoPushServiceTest extends TestCase
{
    public function test_dil_match_uses_groi_price_and_ignores_cvr_rev_disc(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 2.5,
            'lp' => 40,
            'ship' => 8,
            'standard_price' => 100,
            'cvr' => 0.5,
            'review_count' => 2,
            'lmp' => 0,
        ]);

        $this->assertNotNull($out);
        $this->assertTrue($out['dil_groi']);
        $this->assertEqualsWithDelta(50.0, $out['groi'], 0.001);
        $this->assertEqualsWithDelta(85.0, $out['sprice'], 0.001);
        $this->assertSame(0.0, $out['cvr_disc']);
        $this->assertSame(0.0, $out['review_disc']);
    }

    public function test_no_dil_match_uses_std_minus_cvr_and_review_disc(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 0,
            'lp' => 40,
            'ship' => 8,
            'standard_price' => 100,
            'cvr' => 0.5,
            'review_count' => 2,
            'lmp' => 0,
        ]);

        $this->assertNotNull($out);
        $this->assertFalse($out['dil_groi']);
        $this->assertEqualsWithDelta(87.0, $out['sprice'], 0.001);
        $this->assertEqualsWithDelta(9.0, $out['cvr_disc'], 0.001);
        $this->assertEqualsWithDelta(4.0, $out['review_disc'], 0.001);
    }

    public function test_no_dil_and_no_disc_returns_std(): void
    {
        $out = $this->compute([
            'inv' => 5,
            'dil' => 40,
            'lp' => 40,
            'ship' => 8,
            'standard_price' => 99.5,
            'cvr' => 8,
            'review_count' => 20,
            'lmp' => 0,
        ]);

        $this->assertNotNull($out);
        $this->assertFalse($out['dil_groi']);
        $this->assertEqualsWithDelta(99.5, $out['sprice'], 0.001);
    }

    public function test_dil_below_lmp_keeps_dil_price(): void
    {
        $svc = new AmazonSprcDilAutoPushService;
        // Dil $18.81 < LMP $24.95 → keep Dil (same as eBay).
        $this->assertEqualsWithDelta(18.81, $svc->capSpriceToLmp(18.81, 24.95, 7, 1.748), 0.01);
    }

    public function test_lmp_caps_when_sgroi_at_lmp_is_at_least_20(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 2.5,
            'lp' => 40,
            'ship' => 8,
            'standard_price' => 100,
            'cvr' => 0,
            'review_count' => 0,
            'lmp' => 70,
        ]);

        $this->assertNotNull($out);
        $this->assertTrue($out['lmp_capped']);
        $this->assertEqualsWithDelta(70.0, $out['sprice'], 0.001);
    }

    public function test_lmp_does_not_cap_when_sgroi_at_lmp_is_below_20(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 2.5,
            'lp' => 40,
            'ship' => 8,
            'standard_price' => 100,
            'cvr' => 0,
            'review_count' => 0,
            'lmp' => 60,
        ]);

        $this->assertNotNull($out);
        $this->assertFalse($out['lmp_capped']);
        $this->assertEqualsWithDelta(85.0, $out['sprice'], 0.001);
    }

    public function test_inv_zero_is_skipped(): void
    {
        $out = $this->compute([
            'inv' => 0,
            'dil' => 10,
            'lp' => 40,
            'ship' => 8,
            'standard_price' => 100,
            'cvr' => 1,
            'review_count' => 2,
            'lmp' => 0,
        ]);

        $this->assertNull($out);
    }

    public function test_cvr_down_below_7_subtracts_10_from_target_groi(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 2.5,
            'lp' => 40,
            'ship' => 8,
            'standard_price' => 100,
            'cvr' => 4,
            'review_count' => 0,
            'lmp' => 0,
            'a_l30' => 4,
            'sess30' => 100,
            'a_l60' => 10,
            'sess60' => 100,
        ]);

        $this->assertNotNull($out);
        $this->assertTrue($out['dil_groi']);
        $this->assertEqualsWithDelta(40.0, $out['groi'], 0.001);
        $expected = AmazonDilGroiRule::suggestedPrice(40, 8, 40);
        $this->assertEqualsWithDelta($expected, $out['sprice'], 0.001);
    }

    public function test_cvr_up_above_10_adds_10_to_target_groi(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 2.5,
            'lp' => 40,
            'ship' => 8,
            'standard_price' => 100,
            'cvr' => 12,
            'review_count' => 0,
            'lmp' => 0,
            'a_l30' => 12,
            'sess30' => 100,
            'a_l60' => 8,
            'sess60' => 100,
        ]);

        $this->assertNotNull($out);
        $this->assertTrue($out['dil_groi']);
        $this->assertEqualsWithDelta(60.0, $out['groi'], 0.001);
        $expected = AmazonDilGroiRule::suggestedPrice(40, 8, 60);
        $this->assertEqualsWithDelta($expected, $out['sprice'], 0.001);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function compute(array $row): ?array
    {
        $row = array_merge([
            'a_l30' => 8,
            'sess30' => 100,
            'a_l60' => 8,
            'sess60' => 100,
        ], $row);
        $service = new AmazonSprcDilAutoPushService;
        $cvrRules = [
            ['key' => '0.01-1', 'label' => '0.01–1%', 'disc' => 9],
            ['key' => 'gt-7', 'label' => '> 7%', 'disc' => 0],
        ];
        $reviewRules = [
            ['key' => '1-2', 'min' => 1, 'max' => 2, 'disc' => 4],
            ['key' => '2-3', 'min' => 2, 'max' => 3, 'disc' => 4],
        ];

        return $service->computeTarget($row, AmazonDilGroiRule::defaults(), $cvrRules, $reviewRules, 4);
    }
}
