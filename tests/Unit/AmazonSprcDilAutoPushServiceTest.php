<?php

namespace Tests\Unit;

use App\Services\AmazonSprcDilAutoPushService;
use App\Support\AmazonDilGroiRule;
use Tests\TestCase;

class AmazonSprcDilAutoPushServiceTest extends TestCase
{
    public function test_std_price_minus_cvr_and_review_disc_even_when_dil_matches(): void
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
        $this->assertEqualsWithDelta(50.0, $out['nroi'], 0.001);
        $this->assertEqualsWithDelta(0.0, $out['cvr_disc'], 0.001);
        $this->assertEqualsWithDelta(4.0, $out['review_disc'], 0.001);
        $this->assertEqualsWithDelta(4.0, $out['sum_disc'], 0.001);
        // Std $100 − review 4%. CVR Disc is the Std prc vs dil disc, which is unset here.
        $this->assertEqualsWithDelta(96.0, $out['sprice'], 0.001);
    }

    public function test_ads_pct_does_not_change_std_discount_sprice(): void
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
        ], 10.0);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(96.0, $out['sprice'], 0.001);
    }

    public function test_std_price_minus_age_dil_cvr_and_review_discounts(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 12,
            'age_days' => 100,
            'lp' => 40,
            'ship' => 8,
            'standard_price' => 200,
            'cvr' => 0.5,
            'review_count' => 2,
            'lmp' => 0,
            'a_l30' => 8,
            'sess30' => 100,
            'a_l60' => 8,
            'sess60' => 100,
        ], 0.0, [
            'dil' => [['min' => 10, 'max' => 25, 'disc' => 5]],
            'age' => [['min' => 91, 'max' => 180, 'disc' => 3]],
            'cvr' => ['down_lt' => 7, 'down_disc' => 2, 'up_gt' => 10, 'up_disc' => 1, 'flat_disc' => 0],
        ]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(3.0, $out['age_disc'], 0.001);
        $this->assertEqualsWithDelta(5.0, $out['dil_disc'], 0.001);
        $this->assertEqualsWithDelta(0.0, $out['cvr_disc'], 0.001);
        $this->assertEqualsWithDelta(4.0, $out['review_disc'], 0.001);
        $this->assertEqualsWithDelta(12.0, $out['sum_disc'], 0.001);
        $this->assertEqualsWithDelta(176.0, $out['sprice'], 0.001);
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
        $this->assertEqualsWithDelta(96.0, $out['sprice'], 0.001);
        $this->assertEqualsWithDelta(0.0, $out['cvr_disc'], 0.001);
        $this->assertEqualsWithDelta(4.0, $out['review_disc'], 0.001);
    }

    public function test_std_under_15_halves_age_dil_cvr_and_review_discounts(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 12,
            'age_days' => 100,
            'lp' => 4,
            'ship' => 1,
            'standard_price' => 10,
            'cvr' => 0.5,
            'review_count' => 2,
            'lmp' => 0,
            'a_l30' => 8,
            'sess30' => 100,
            'a_l60' => 8,
            'sess60' => 100,
        ], 0.0, [
            'dil' => [['min' => 10, 'max' => 25, 'disc' => 5]],
            'age' => [['min' => 91, 'max' => 180, 'disc' => 3]],
            'cvr' => ['down_lt' => 7, 'down_disc' => 2, 'up_gt' => 10, 'up_disc' => 1, 'flat_disc' => 0],
        ]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(1.5, $out['age_disc'], 0.001);
        $this->assertEqualsWithDelta(2.5, $out['dil_disc'], 0.001);
        $this->assertEqualsWithDelta(0.0, $out['cvr_disc'], 0.001);
        $this->assertEqualsWithDelta(2.0, $out['review_disc'], 0.001);
        $this->assertEqualsWithDelta(6.0, $out['sum_disc'], 0.001);
        $this->assertEqualsWithDelta(9.4, $out['sprice'], 0.001);
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
        $this->assertEqualsWithDelta(100.0, $out['sprice'], 0.001);
    }

    public function test_missing_lmp_caps_sprice_at_std(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 2.5,
            'lp' => 40,
            'ship' => 8,
            'standard_price' => 70,
            'cvr' => 0,
            'review_count' => 0,
            'lmp' => 0,
        ]);

        $this->assertNotNull($out);
        $this->assertFalse($out['lmp_capped']);
        $this->assertEqualsWithDelta(70.0, $out['sprice'], 0.001);
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

    public function test_cvr_down_below_7_subtracts_10_from_target_nroi(): void
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
        $this->assertEqualsWithDelta(40.0, $out['nroi'], 0.001);
        $this->assertEqualsWithDelta(100.0, $out['sprice'], 0.001);
    }

    public function test_no_reviews_blocks_discount_when_star_rating_would_match(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 2.5,
            'lp' => 40,
            'ship' => 8,
            'standard_price' => 100,
            'cvr' => 0.5,
            'review_count' => 0,
            'review_rating' => 2,
            'lmp' => 0,
        ]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(0.0, $out['review_disc'], 0.001);
        $this->assertEqualsWithDelta(100.0, $out['sprice'], 0.001);
    }

    public function test_star_rating_still_discounts_when_a_review_exists(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 2.5,
            'lp' => 40,
            'ship' => 8,
            'standard_price' => 100,
            'cvr' => 0.5,
            'review_count' => 1,
            'review_rating' => 2,
            'lmp' => 0,
        ]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(4.0, $out['review_disc'], 0.001);
        $this->assertEqualsWithDelta(96.0, $out['sprice'], 0.001);
    }

    public function test_cvr_up_above_10_adds_10_to_target_nroi(): void
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
        $this->assertEqualsWithDelta(60.0, $out['nroi'], 0.001);
        $this->assertEqualsWithDelta(100.0, $out['sprice'], 0.001);
    }

    public function test_extra_cvr_slabs_use_the_tighter_band(): void
    {
        $service = new AmazonSprcDilAutoPushService;
        $cfg = [
            'down2_lt' => 4,
            'down2_disc' => 8,
            'down_lt' => 7,
            'down_disc' => 2,
            'up_gt' => 10,
            'up_disc' => 1,
            'up2_gt' => 15,
            'up2_disc' => 6,
            'flat_disc' => 0,
        ];

        $this->assertEqualsWithDelta(8.0, $service->discForStdCvrTrend([
            'a_l30' => 2, 'sess30' => 100, 'a_l60' => 10, 'sess60' => 100,
        ], $cfg), 0.001);
        $this->assertEqualsWithDelta(2.0, $service->discForStdCvrTrend([
            'a_l30' => 5, 'sess30' => 100, 'a_l60' => 10, 'sess60' => 100,
        ], $cfg), 0.001);
        $this->assertEqualsWithDelta(1.0, $service->discForStdCvrTrend([
            'a_l30' => 12, 'sess30' => 100, 'a_l60' => 8, 'sess60' => 100,
        ], $cfg), 0.001);
        $this->assertEqualsWithDelta(6.0, $service->discForStdCvrTrend([
            'a_l30' => 20, 'sess30' => 100, 'a_l60' => 8, 'sess60' => 100,
        ], $cfg), 0.001);
        $this->assertEqualsWithDelta(2.0, $service->discForStdCvrTrend([
            'a_l30' => 2, 'sess30' => 100, 'a_l60' => 10, 'sess60' => 100,
        ], [
            'down_lt' => 7, 'down_disc' => 2, 'up_gt' => 10, 'up_disc' => 1, 'flat_disc' => 0,
        ]), 0.001);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function compute(array $row, float $adsPct = 0.0, array $stdPromo = []): ?array
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

        return $service->computeTarget($row, AmazonDilGroiRule::defaults(), $cvrRules, $reviewRules, 4, null, $adsPct, $stdPromo);
    }
}
