<?php

namespace Tests\Unit;

use App\Services\PlsSprcDilAutoPushService;
use App\Support\StdPrcVsDilPricer;
use PHPUnit\Framework\TestCase;

class PlsSprcDilAutoPushServiceTest extends TestCase
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
        ], $over), 'pls');
    }

    /** @return array<string, mixed> */
    private function row(array $over = []): array
    {
        return array_merge([
            'sku' => 'PLS-TEST',
            'inv' => 10,
            'dil' => 20,
            'pls_l30' => 2,
            'age_days' => 10,
            'lp' => 20,
            'ship' => 5,
            'margin' => 0.80,
            'standard_price' => 100,
            'clearance' => false,
        ], $over);
    }

    private function target(array $row, ?StdPrcVsDilPricer $pricer = null): ?array
    {
        return (new PlsSprcDilAutoPushService)->computeTarget($row, $pricer ?? $this->pricer());
    }

    public function test_price_is_std_minus_age_dil_and_b_disc(): void
    {
        $out = $this->target($this->row());

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2) / 100) = 90.00
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }

    public function test_zero_sold_uses_pls_l30(): void
    {
        $out = $this->target($this->row(['pls_l30' => 0]));

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2 + 4) / 100) = 86.00
        $this->assertEqualsWithDelta(86.00, $out['sprice'], 0.001);
    }

    public function test_clearance_sku_is_priced_only_from_the_clearance_nroi(): void
    {
        // (70 × 1.10 + 10) ÷ 0.80 = 108.75 although Std Prc is 100: no discount or Std Prc rule applies. Same in Sprc Dil.
        $row = $this->row(['standard_price' => 100, 'lp' => 70, 'ship' => 10, 'clearance' => true, 'clearance_nroi' => 10]);
        $this->assertEqualsWithDelta(108.75, $this->target($row)['sprice'], 0.001);
        $this->assertEqualsWithDelta(108.75, (new PlsSprcDilAutoPushService)->computeTarget($row, null, $this->dilCtx())['sprice'], 0.001);
        $this->assertNull($this->target($this->row(['lp' => 0, 'clearance' => true, 'clearance_nroi' => 10])));
    }

    public function test_blue_catch_up_falls_back_to_the_saved_s_prc_the_page_shows(): void
    {
        $svc = new PlsSprcDilAutoPushService;
        $row = ['inv' => 5, 'sprice' => 41.5, 'margin' => 80, 'lp' => 10, 'ship' => 2, 'standard_price' => 60];
        $t = $svc->savedSpriceTarget($row);
        $this->assertSame(41.5, $t['sprice']);
        $this->assertEqualsWithDelta(0.8, $t['margin'], 0.0001);

        $this->assertNull($svc->savedSpriceTarget(['inv' => 0, 'sprice' => 41.5]));
        $this->assertNull($svc->savedSpriceTarget(['inv' => 5, 'sprice' => 0]));
    }

    public function test_lmp_has_no_effect_on_pls(): void
    {
        $out = $this->target($this->row(['lmp' => 50]));

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }

    public function test_no_inventory_or_std_has_no_target(): void
    {
        $this->assertNull($this->target($this->row(['inv' => 0])));
        $this->assertNull($this->target($this->row(['standard_price' => 0])));
    }

    public function test_margin_given_as_percent_is_normalised(): void
    {
        $out = $this->target($this->row(['margin' => 80]));

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(0.80, $out['margin'], 0.0001);
    }

    public function test_invalid_margin_defaults_to_one(): void
    {
        $out = $this->target($this->row(['margin' => 0]));

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(1.0, $out['margin'], 0.0001);
    }

    /** @return list<array{min:float,max:float,cap:float|null}> */
    private function capSlabs(?float $cap): array
    {
        return [['min' => 0.0, 'max' => 100.0, 'cap' => $cap]];
    }

    public function test_snroi_cap_prices_s_prc_from_the_target_with_ship_and_no_ads(): void
    {
        // LP 20, Ship 5, PLS% 0.80. Cap 100% -> (20 x 2 + 5) / 0.80 = 56.25 (the 90.00 discount price is not used).
        $out = $this->target($this->row(), $this->pricer(['snroi_cap' => $this->capSlabs(100)]));

        $this->assertNotNull($out);
        $this->assertSame(56.25, $out['sprice']);
        // The page SNROI% = (S PRC x PLS% - Ship - LP) / LP lands on the target.
        $this->assertEqualsWithDelta(100.0, ((56.25 * 0.80) - 5 - 20) / 20 * 100, 0.01);
    }

    public function test_snroi_cap_above_what_std_can_reach_holds_at_std(): void
    {
        $out = $this->target($this->row(), $this->pricer(['snroi_cap' => $this->capSlabs(1000)]));

        $this->assertNotNull($out);
        $this->assertSame(100.0, $out['sprice']);
    }

    public function test_snroi_cap_blank_slab_keeps_the_discount_price(): void
    {
        $out = $this->target($this->row(), $this->pricer(['snroi_cap' => $this->capSlabs(null)]));

        $this->assertNotNull($out);
        $this->assertSame(90.0, $out['sprice']);
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
        return $this->row(array_merge(['price' => 100], $over));
    }

    public function test_sprc_dil_price_is_the_slab_target_with_ship(): void
    {
        $out = (new PlsSprcDilAutoPushService)->computeTarget($this->dilRow(), null, $this->dilCtx());

        $this->assertNotNull($out);
        // Target 60%: (20 x 1.6 + 5) / 0.80 = 46.25. Std 100 is far above.
        $this->assertEqualsWithDelta(46.25, $out['sprice'], 0.001);
    }

    public function test_sprc_dil_diff_adds_points_when_std_is_above_price_by_more_than_the_threshold(): void
    {
        // Std 100 vs Price 70 -> Diff 42.9% (> 20): Target 70%. (20 x 1.7 + 5) / 0.80 = 48.75.
        $out = (new PlsSprcDilAutoPushService)->computeTarget($this->dilRow(['price' => 70]), null, $this->dilCtx());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(48.75, $out['sprice'], 0.001);
    }

    public function test_sprc_dil_has_no_cvr_overlay_and_no_lmp_cap(): void
    {
        $out = (new PlsSprcDilAutoPushService)->computeTarget($this->dilRow(['cvr' => 5, 'views' => 500, 'lmp' => 30]), null, $this->dilCtx());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(46.25, $out['sprice'], 0.001);
    }

    public function test_sprc_dil_std_price_is_the_maximum(): void
    {
        $out = (new PlsSprcDilAutoPushService)->computeTarget($this->dilRow(['standard_price' => 30]), null, $this->dilCtx());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(30.0, $out['sprice'], 0.001);
    }

    public function test_sprc_dil_above_every_slab_and_no_inventory_have_no_target(): void
    {
        $svc = new PlsSprcDilAutoPushService;

        $this->assertNull($svc->computeTarget($this->dilRow(['dil' => 60]), null, $this->dilCtx()));
        $this->assertNull($svc->computeTarget($this->dilRow(['inv' => 0]), null, $this->dilCtx()));
    }

    public function test_without_a_dil_context_the_std_rule_still_prices(): void
    {
        $out = (new PlsSprcDilAutoPushService)->computeTarget($this->row(), $this->pricer(), null);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }
}
