<?php

namespace Tests\Unit;

use App\Services\FaireSprcDilAutoPushService;
use App\Support\StdPrcVsDilPricer;
use PHPUnit\Framework\TestCase;

class FaireSprcDilAutoPushServiceTest extends TestCase
{
    private function pricer(array $over = []): StdPrcVsDilPricer
    {
        return new StdPrcVsDilPricer(array_merge([
            'dil' => [['min' => 0, 'max' => 100, 'disc' => 5]],
            'age' => [['min' => 0, 'max' => 9999, 'disc' => 3]],
            'cvr' => [
                'down2_lt' => 4, 'down2_disc' => 0, 'down_lt' => 7, 'down_disc' => 0,
                'up_gt' => 10, 'up_disc' => 0, 'up2_gt' => 15, 'up2_disc' => 0, 'flat_disc' => 0,
            ],
            'reviews' => [],
            'review_max' => 4,
            'buss' => [['min' => 0, 'max' => 9999, 'disc' => 2]],
            'zero_sold_disc' => 4,
        ], $over), 'faire');
    }

    /** @return array<string, mixed> */
    private function row(array $over = []): array
    {
        return array_merge([
            'sku' => 'FA-TEST',
            'inv' => 10,
            'dil' => 20,
            'al30' => 2,
            'age_days' => 10,
            'lp' => 20,
            'margin' => 0.75,
            'standard_price' => 100,
            'clearance' => false,
        ], $over);
    }

    public function test_price_is_std_minus_age_dil_and_b_disc(): void
    {
        $out = (new FaireSprcDilAutoPushService)->computeTarget($this->row(), $this->pricer());

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2) / 100) = 90.00. NPFT is far above 10%.
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }

    public function test_ship_is_reduced_from_sprice_before_the_snroi_cap(): void
    {
        $svc = new FaireSprcDilAutoPushService;

        // 90.00 − Ship 8 = 82.00 (no cap rule).
        $out = $svc->computeTarget($this->row(['ship' => 8]), $this->pricer());
        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(82.00, $out['sprice'], 0.001);

        // SNROI at 82 = ((82 × 0.75) − 20) / 20 = 206%. Cap 100% → (20 × 2) / 0.75 = 53.33. Ship is NOT taken off after the cap.
        $capped = $svc->computeTarget(
            $this->row(['ship' => 8]),
            $this->pricer(['snroi_cap' => [['min' => 0, 'max' => 100, 'cap' => 100.0]]])
        );
        $this->assertNotNull($capped);
        $this->assertEqualsWithDelta(53.33, $capped['sprice'], 0.001);
    }

    public function test_zero_sold_uses_al30(): void
    {
        $out = (new FaireSprcDilAutoPushService)->computeTarget($this->row(['al30' => 0]), $this->pricer());

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2 + 4) / 100) = 86.00
        $this->assertEqualsWithDelta(86.00, $out['sprice'], 0.001);
    }

    public function test_clearance_keeps_the_discount_price(): void
    {
        $out = (new FaireSprcDilAutoPushService)->computeTarget(
            $this->row(['lp' => 60, 'clearance' => true]),
            $this->pricer()
        );

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }

    public function test_no_lmp_or_amz_rule(): void
    {
        $out = (new FaireSprcDilAutoPushService)->computeTarget(
            $this->row(['lmp' => 50, 'amz' => 95]),
            $this->pricer()
        );

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
        $this->assertArrayNotHasKey('amz_capped', $out);
    }

    public function test_no_inventory_or_std_has_no_target(): void
    {
        $svc = new FaireSprcDilAutoPushService;
        $this->assertNull($svc->computeTarget($this->row(['inv' => 0]), $this->pricer()));
        $this->assertNull($svc->computeTarget($this->row(['standard_price' => 0]), $this->pricer()));
    }

    public function test_margin_given_as_percent_is_normalised(): void
    {
        $out = (new FaireSprcDilAutoPushService)->computeTarget($this->row(['margin' => 75]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(0.75, $out['margin'], 0.0001);
    }

    public function test_snroi_cap_above_what_std_less_ship_can_reach_holds_there(): void
    {
        $svc = new FaireSprcDilAutoPushService();
        // A 1000% target needs far more than Std. S PRC holds at Std - Ship (100 - 8 = 92), the discounts are not taken.
        $out = $svc->computeTarget(
            $this->row(['ship' => 8]),
            $this->pricer(['snroi_cap' => [['min' => 0, 'max' => 100, 'cap' => 1000.0]]])
        );

        $this->assertNotNull($out);
        $this->assertSame(92.0, $out['sprice']);
    }

    public function test_snroi_cap_blank_slab_keeps_the_discount_price_less_ship(): void
    {
        $svc = new FaireSprcDilAutoPushService();
        $out = $svc->computeTarget(
            $this->row(['ship' => 8]),
            $this->pricer(['snroi_cap' => [['min' => 0, 'max' => 100, 'cap' => null]]])
        );

        $this->assertNotNull($out);
        $this->assertSame(82.0, $out['sprice']);
    }

    public function test_capped_price_gives_the_target_snroi_on_the_page_formula(): void
    {
        $svc = new FaireSprcDilAutoPushService();
        $out = $svc->computeTarget(
            $this->row(['ship' => 8]),
            $this->pricer(['snroi_cap' => [['min' => 0, 'max' => 100, 'cap' => 100.0]]])
        );

        $this->assertNotNull($out);
        // Page SNROI% = (S PRC x take-home - LP) / LP, no Ship, no Ads%.
        $snroi = (($out['sprice'] * 0.75) - 20) / 20 * 100;
        $this->assertLessThanOrEqual(100.0, $snroi);
        $this->assertGreaterThan(99.9, $snroi);
    }

    /** @return array<string, mixed> */
    private function dilCtx(): array
    {
        return [
            'rules' => [
                ['key' => '0-0', 'label' => '0%', 'min' => 0.0, 'max' => 0.0, 'groi' => 40.0],
                ['key' => '0.1-50', 'label' => '0.1–50%', 'min' => 0.1, 'max' => 50.0, 'groi' => 60.0],
            ],
            'cvr_adj' => ['down_lt' => 7, 'down_adj' => -5, 'up_gt' => 10, 'up_adj' => 5],
            'diff_adj' => ['gt_pct' => 20, 'points' => 10],
            'lmp_cap' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function dilRow(array $over = []): array
    {
        return $this->row(array_merge(['price' => 100, 'views' => 0, 'cvr' => 8, 'ship' => 4], $over));
    }

    public function test_sprc_dil_price_is_the_slab_target_with_no_ship(): void
    {
        $out = (new FaireSprcDilAutoPushService)->computeTarget($this->dilRow(), null, $this->dilCtx());

        $this->assertNotNull($out);
        // Target 60%: 20 x 1.6 / 0.75 = 42.67. The Ship column (4) is not used in Sprc Dil. Std 100 is far above.
        $this->assertEqualsWithDelta(42.67, $out['sprice'], 0.001);
    }

    public function test_sprc_dil_diff_adds_points_when_std_is_above_price_by_more_than_the_threshold(): void
    {
        // Std 100 vs Price 70 -> Diff 42.9% (> 20): Target 70%. 20 x 1.7 / 0.75 = 45.33.
        $out = (new FaireSprcDilAutoPushService)->computeTarget($this->dilRow(['price' => 70]), null, $this->dilCtx());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(45.33, $out['sprice'], 0.001);
    }

    public function test_sprc_dil_cvr_overlay_only_applies_with_views(): void
    {
        $svc = new FaireSprcDilAutoPushService;

        // CVR 5% < 7% (down): Adj -5 -> Target 55%. 20 x 1.55 / 0.75 = 41.33.
        $withViews = $svc->computeTarget($this->dilRow(['views' => 200, 'cvr' => 5]), null, $this->dilCtx());
        $this->assertNotNull($withViews);
        $this->assertEqualsWithDelta(41.33, $withViews['sprice'], 0.001);

        $noViews = $svc->computeTarget($this->dilRow(['views' => 0, 'cvr' => 5]), null, $this->dilCtx());
        $this->assertNotNull($noViews);
        $this->assertEqualsWithDelta(42.67, $noViews['sprice'], 0.001);
    }

    public function test_sprc_dil_std_price_is_the_maximum(): void
    {
        $out = (new FaireSprcDilAutoPushService)->computeTarget($this->dilRow(['standard_price' => 30]), null, $this->dilCtx());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(30.0, $out['sprice'], 0.001);
    }

    public function test_sprc_dil_above_every_slab_clearance_and_no_inventory(): void
    {
        $svc = new FaireSprcDilAutoPushService;

        $this->assertNull($svc->computeTarget($this->dilRow(['dil' => 60]), null, $this->dilCtx()));
        $this->assertNull($svc->computeTarget($this->dilRow(['inv' => 0]), null, $this->dilCtx()));

        $clearance = $svc->computeTarget($this->dilRow(['clearance' => true]), null, $this->dilCtx());
        $this->assertNotNull($clearance);
        $this->assertEqualsWithDelta(42.67, $clearance['sprice'], 0.001);
    }

    public function test_without_a_dil_context_the_std_rule_still_prices(): void
    {
        $out = (new FaireSprcDilAutoPushService)->computeTarget($this->row(), $this->pricer(), null);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }
}
