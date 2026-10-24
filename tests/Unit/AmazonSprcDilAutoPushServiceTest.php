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

    public function test_lmp_reduce_takes_ten_percent_off_lmp_when_dil_is_under_25(): void
    {
        // Std $100 is above LMP $70, so S PRC = LMP. Dil 2.5 is under 25 → LMP less 10% = $63.
        $out = $this->compute($this->lmpReduceRow(2.5), 0.0, ['lmp_reduce' => AmazonDilGroiRule::LMP_REDUCE_DEFAULTS]);

        $this->assertNotNull($out);
        $this->assertTrue($out['lmp_capped']);
        $this->assertEqualsWithDelta(63.0, $out['sprice'], 0.001);
    }

    public function test_lmp_reduce_takes_five_percent_off_lmp_when_dil_is_25_or_more(): void
    {
        // Dil 40 is in the 25+ slab → LMP less 5% = $66.50.
        $out = $this->compute($this->lmpReduceRow(40), 0.0, ['lmp_reduce' => AmazonDilGroiRule::LMP_REDUCE_DEFAULTS]);

        $this->assertNotNull($out);
        $this->assertTrue($out['lmp_capped']);
        $this->assertEqualsWithDelta(66.5, $out['sprice'], 0.001);
    }

    public function test_lmp_reduce_blank_slab_keeps_s_prc_at_lmp(): void
    {
        $slabs = [['min' => 0, 'max' => 25, 'pct' => null], ['min' => 25, 'max' => 9999, 'pct' => 5.0]];
        $out = $this->compute($this->lmpReduceRow(2.5), 0.0, ['lmp_reduce' => $slabs]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(70.0, $out['sprice'], 0.001);
    }

    public function test_lmp_reduce_does_not_touch_a_price_below_lmp(): void
    {
        $row = $this->lmpReduceRow(2.5);
        $row['lmp'] = 150;
        $out = $this->compute($row, 0.0, ['lmp_reduce' => AmazonDilGroiRule::LMP_REDUCE_DEFAULTS]);

        $this->assertNotNull($out);
        $this->assertFalse($out['lmp_capped']);
        $this->assertEqualsWithDelta(100.0, $out['sprice'], 0.001);
    }

    /**
     * @return array<string, mixed>
     */
    private function lmpReduceRow(float $dil): array
    {
        return [
            'inv' => 10,
            'dil' => $dil,
            'lp' => 40,
            'ship' => 8,
            'standard_price' => 100,
            'cvr' => 0,
            'review_count' => 0,
            'lmp' => 70,
        ];
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

    public function test_snroi_cap_lowers_price_to_the_dil_slab_target(): void
    {
        // SNROI at $100 = (100 x 0.80 - 8 - 40) / 40 = 80%. Cap 50% -> (40 x 1.5 + 8) / 0.80 = $85.
        $out = $this->compute($this->snroiRow(), 0.0, ['snroi_cap' => $this->snroiSlabs(50)]);

        $this->assertNotNull($out);
        $this->assertTrue($out['snroi_capped']);
        $this->assertEqualsWithDelta(85.0, $out['sprice'], 0.001);
    }

    public function test_snroi_cap_counts_ads_percent_in_the_snroi(): void
    {
        // Ads 5% -> net margin 0.75. Cap 50% -> (40 x 1.5 + 8) / 0.75 = $90.66 (rounded down to the cent).
        $out = $this->compute($this->snroiRow(), 5.0, ['snroi_cap' => $this->snroiSlabs(50)]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.66, $out['sprice'], 0.001);
    }

    public function test_snroi_cap_leaves_a_price_already_under_the_target(): void
    {
        $out = $this->compute($this->snroiRow(), 0.0, ['snroi_cap' => $this->snroiSlabs(200)]);

        $this->assertNotNull($out);
        $this->assertFalse($out['snroi_capped']);
        $this->assertEqualsWithDelta(100.0, $out['sprice'], 0.001);
    }

    public function test_snroi_cap_blank_slab_means_no_cap(): void
    {
        $out = $this->compute($this->snroiRow(), 0.0, ['snroi_cap' => $this->snroiSlabs(null)]);

        $this->assertNotNull($out);
        $this->assertFalse($out['snroi_capped']);
        $this->assertEqualsWithDelta(100.0, $out['sprice'], 0.001);
    }

    public function test_snroi_cap_uses_the_slab_of_the_sku_dil(): void
    {
        // Dil 40 is outside the 0.1-10 slab that carries the cap, so nothing is capped.
        $out = $this->compute(array_merge($this->snroiRow(), ['dil' => 40]), 0.0, ['snroi_cap' => $this->snroiSlabs(50)]);

        $this->assertNotNull($out);
        $this->assertFalse($out['snroi_capped']);
        $this->assertEqualsWithDelta(100.0, $out['sprice'], 0.001);
    }

    public function test_snroi_cap_applies_after_the_discounts(): void
    {
        // 10% off Std = $90 (SNROI 65%). Cap 50% still lowers it to $85.
        $out = $this->compute($this->snroiRow(), 0.0, [
            'dil' => [['min' => 0.1, 'max' => 10, 'disc' => 10]],
            'snroi_cap' => $this->snroiSlabs(50),
        ]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(85.0, $out['sprice'], 0.001);
    }

    /** @return array<string, mixed> */
    private function snroiRow(): array
    {
        return [
            'inv' => 10,
            'dil' => 2.5,
            'lp' => 40,
            'ship' => 8,
            'standard_price' => 100,
            'cvr' => 0,
            'review_count' => 0,
            'lmp' => 0,
        ];
    }

    /** @return list<array{min:float,max:float,cap:float|null}> */
    private function snroiSlabs(?float $cap): array
    {
        return [
            ['min' => 0.0, 'max' => 0.0, 'cap' => null],
            ['min' => 0.1, 'max' => 10.0, 'cap' => $cap],
            ['min' => 10.0, 'max' => 9999.0, 'cap' => null],
        ];
    }

    public function test_clearance_sku_gets_no_sprice(): void
    {
        $out = $this->compute([
            'inv' => 5,
            'dil' => 2.5,
            'lp' => 28.18,
            'ship' => 6,
            'standard_price' => 65.99,
            'clearance' => 'YES',
        ], 13.79);

        $this->assertNull($out);
    }

    public function test_clearance_sku_is_priced_from_the_clearance_snroi_slab_of_its_dil(): void
    {
        $promo = ['clearance_snroi' => AmazonDilGroiRule::CLEARANCE_SNROI_DEFAULTS];
        // LP 40, Ship 8, take-home 0.80, no Ads. Price at SNROI t% = (40 x (1 + t/100) + 8) / 0.80.
        foreach ([[2.5, 60.0], [25, 60.0], [26, 70.0], [50, 70.0], [51, 75.0], [300, 75.0]] as [$dil, $price]) {
            $out = $this->compute($this->clearanceRow($dil), 0.0, $promo);
            $this->assertNotNull($out, 'dil '.$dil);
            $this->assertEqualsWithDelta($price, $out['sprice'], 0.001, 'dil '.$dil);
            $this->assertTrue($out['snroi_capped']);
        }
    }

    public function test_clearance_snroi_rounds_up_so_snroi_is_never_under_the_target(): void
    {
        $row = $this->clearanceRow(2.5);
        $row['lp'] = 28.18;
        $row['ship'] = 6;
        $out = $this->compute($row, 0.0, ['clearance_snroi' => AmazonDilGroiRule::CLEARANCE_SNROI_DEFAULTS]);

        $this->assertNotNull($out);
        $this->assertGreaterThanOrEqual(0.0, (($out['sprice'] * 0.80) - 6 - 28.18) / 28.18 * 100);
        $this->assertEqualsWithDelta(42.73, $out['sprice'], 0.001);
    }

    public function test_clearance_with_a_blank_slab_or_no_block_gets_no_sprice(): void
    {
        $blank = [['min' => 0, 'max' => 25, 'nroi' => null], ['min' => 25, 'max' => 9999, 'nroi' => 20.0]];
        $this->assertNull($this->compute($this->clearanceRow(2.5), 0.0, ['clearance_snroi' => $blank]));
        $this->assertNull($this->compute($this->clearanceRow(2.5), 0.0, ['clearance_snroi' => []]));
        $this->assertNull($this->compute($this->clearanceRow(2.5)));
    }

    public function test_clearance_snroi_ignores_the_discounts_and_the_snroi_cap_block(): void
    {
        $out = $this->compute($this->clearanceRow(2.5), 0.0, [
            'clearance_snroi' => AmazonDilGroiRule::CLEARANCE_SNROI_DEFAULTS,
            'snroi_cap' => [['min' => 0, 'max' => 9999, 'cap' => 50.0]],
        ]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(60.0, $out['sprice'], 0.001);
    }

    /**
     * @return array<string, mixed>
     */
    private function clearanceRow(float $dil): array
    {
        return [
            'inv' => 10,
            'dil' => $dil,
            'lp' => 40,
            'ship' => 8,
            'standard_price' => 100,
            'cvr' => 0,
            'review_count' => 0,
            'lmp' => 0,
            'clearance' => 'YES',
        ];
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
