<?php

namespace Tests\Unit;

use App\Services\AlibabaSprcDilAutoPushService;
use App\Support\StdPrcVsDilPricer;
use PHPUnit\Framework\TestCase;

class AlibabaSprcDilAutoPushServiceTest extends TestCase
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
        ], $over), 'alibaba');
    }

    /** @return array<string, mixed> */
    private function row(array $over = []): array
    {
        return array_merge([
            'sku' => 'AB-TEST',
            'inv' => 10,
            'dil' => 20,
            'al30' => 2,
            'age_days' => 10,
            'lp' => 20,
            'margin' => 0.80,
            'standard_price' => 100,
            'clearance' => false,
        ], $over);
    }

    private function target(array $row, ?StdPrcVsDilPricer $pricer = null, float $ads = 0.0): ?array
    {
        return (new AlibabaSprcDilAutoPushService)->computeTarget($row, $pricer ?? $this->pricer(), $ads);
    }

    public function test_price_is_std_minus_age_dil_and_b_disc(): void
    {
        $out = $this->target($this->row());

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2) / 100) = 90.00
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }

    public function test_ship_is_reduced_from_sprice_before_the_snroi_cap(): void
    {
        // 90.00 − Ship 8 = 82.00 (no cap rule).
        $out = $this->target($this->row(['ship' => 8]));
        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(82.00, $out['sprice'], 0.001);

        // SNROI at 82 = ((82 × 0.80) − 20) / 20 = 128%. Cap 100% → (20 × 2) / 0.80 = 50.00. Ship is not taken off after the cap.
        $capped = $this->target(
            $this->row(['ship' => 8]),
            $this->pricer(['snroi_cap' => [['min' => 0, 'max' => 100, 'cap' => 100.0]]])
        );
        $this->assertNotNull($capped);
        $this->assertEqualsWithDelta(50.00, $capped['sprice'], 0.001);
    }

    public function test_zero_sold_uses_ab_l30(): void
    {
        $out = $this->target($this->row(['al30' => 0]));

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2 + 4) / 100) = 86.00
        $this->assertEqualsWithDelta(86.00, $out['sprice'], 0.001);
    }

    public function test_clearance_sku_has_no_target_like_amazon(): void
    {
        // Clearance SKUs show "C" in S PRC. No rule prices them and nothing is saved or pushed.
        $this->assertNull($this->target($this->row(['standard_price' => 150, 'lp' => 100, 'clearance' => true])));
    }

    public function test_no_std_or_inventory_has_no_target(): void
    {
        $this->assertNull($this->target($this->row(['standard_price' => 0])));
        $this->assertNull($this->target($this->row(['standard_price' => null])));
        $this->assertNull($this->target($this->row(['inv' => 0])));
    }

    public function test_margin_given_as_percent_is_normalised(): void
    {
        $out = $this->target($this->row(['margin' => 80]));

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(0.80, $out['margin'], 0.0001);
    }

    public function test_missing_margin_defaults_to_one(): void
    {
        $out = $this->target($this->row(['margin' => 0]));

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(1.0, $out['margin'], 0.0001);
    }

    public function test_snroi_cap_ignores_ads_because_the_page_snroi_has_none(): void
    {
        // The page SNROI% = (S PRC x take-home - LP) / LP. Cap 100% -> (20 x 2) / 0.80 = 50.00, even when an Ads% is passed in.
        $pricer = $this->pricer(['snroi_cap' => [['min' => 0, 'max' => 100, 'cap' => 100.0]]]);
        $out = $this->target($this->row(['ship' => 8]), $pricer, 0.10);

        $this->assertNotNull($out);
        $this->assertSame(50.0, $out['sprice']);
        $this->assertEqualsWithDelta(100.0, (($out['sprice'] * 0.80) - 20) / 20 * 100, 0.01);
    }

    public function test_snroi_cap_above_what_std_less_ship_can_reach_holds_there(): void
    {
        // A 1000% target needs far more than Std. S PRC holds at Std - Ship (100 - 8 = 92), the discounts are not taken.
        $pricer = $this->pricer(['snroi_cap' => [['min' => 0, 'max' => 100, 'cap' => 1000.0]]]);
        $out = $this->target($this->row(['ship' => 8]), $pricer);

        $this->assertNotNull($out);
        $this->assertSame(92.0, $out['sprice']);
    }

    public function test_snroi_cap_blank_slab_keeps_the_discount_price_less_ship(): void
    {
        $pricer = $this->pricer(['snroi_cap' => [['min' => 0, 'max' => 100, 'cap' => null]]]);
        $out = $this->target($this->row(['ship' => 8]), $pricer);

        $this->assertNotNull($out);
        $this->assertSame(82.0, $out['sprice']);
    }

    /** @return array{rules:list<array<string,mixed>>} */
    private function dilContext(array $slabs = []): array
    {
        $slabs = $slabs ?: [[0, 0, 40], [0.1, 25, 60], [25, 100, 30]];

        return ['rules' => array_map(
            static fn (array $s): array => \App\Support\AmazonDilGroiRule::make((float) $s[0], (float) $s[1], (float) $s[2]),
            $slabs
        )];
    }

    private function dilTarget(array $row, ?array $ctx = null): ?array
    {
        return (new AlibabaSprcDilAutoPushService)->computeTarget($row, $this->pricer(), 0.0, $ctx ?? $this->dilContext());
    }

    public function test_sprc_dil_prices_the_dil_slab_target_snroi(): void
    {
        // Dil 20 → slab 0.1–25 → Target SNROI 60%. (LP 20 × 1.60) ÷ 0.80 = 40.00. Std 100 is far above.
        $out = $this->dilTarget($this->row(['dil' => 20]));

        $this->assertNotNull($out);
        $this->assertSame(40.0, $out['sprice']);
        $this->assertEqualsWithDelta(60.0, (($out['sprice'] * 0.80) - 20) / 20 * 100, 0.01);
    }

    public function test_sprc_dil_uses_the_zero_to_zero_slab_when_dil_is_zero(): void
    {
        // Dil 0 → slab 0–0 → 40%. (20 × 1.40) ÷ 0.80 = 35.00, even when AB L30 is 0 (OV Dil decides, not 0 Sold).
        $out = $this->dilTarget($this->row(['dil' => 0, 'al30' => 0]));

        $this->assertNotNull($out);
        $this->assertSame(35.0, $out['sprice']);
    }

    public function test_sprc_dil_has_std_prc_as_the_maximum(): void
    {
        // Target 60% needs 40.00 but Std is 30.00, so S PRC holds at Std.
        $out = $this->dilTarget($this->row(['dil' => 20, 'standard_price' => 30]));

        $this->assertNotNull($out);
        $this->assertSame(30.0, $out['sprice']);
    }

    public function test_sprc_dil_does_not_take_ship_off_and_ignores_the_std_discounts(): void
    {
        $plain = $this->dilTarget($this->row(['dil' => 20, 'ship' => 0]));
        $withShip = $this->dilTarget($this->row(['dil' => 20, 'ship' => 8]));

        $this->assertNotNull($plain);
        $this->assertNotNull($withShip);
        $this->assertSame($plain['sprice'], $withShip['sprice']);
    }

    public function test_sprc_dil_has_no_price_outside_every_slab_or_without_lp(): void
    {
        $ctx = $this->dilContext([[0.1, 25, 60]]);

        $this->assertNull($this->dilTarget($this->row(['dil' => 80]), $ctx));
        $this->assertNull($this->dilTarget($this->row(['dil' => 20, 'lp' => 0])));
    }

    public function test_sprc_dil_clearance_sku_and_no_inventory_have_no_target(): void
    {
        $this->assertNull($this->dilTarget($this->row(['dil' => 20, 'clearance' => true])));
        $this->assertNull($this->dilTarget($this->row(['dil' => 20, 'inv' => 0])));
    }

    public function test_std_rule_is_used_when_no_dil_context_is_given(): void
    {
        $out = (new AlibabaSprcDilAutoPushService)->computeTarget($this->row(['dil' => 20]), $this->pricer(), 0.0, null);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }

    public function test_diff_boost_does_not_depend_on_the_live_price_so_a_push_cannot_flip_it(): void
    {
        // Dil 20 → slab 60%: base (20 × 1.60) ÷ 0.80 = 40.00. Std 100 is 150% above it (> 20%) → +10 points → 70%
        // → (20 × 1.70) ÷ 0.80 = 42.50. Read against the live Price, 40.00 gave 42.50 and 42.50 gave 40.00 (a flip-flop).
        $ctx = $this->dilContext() + ['diff_adj' => ['gt_pct' => 20.0, 'points' => 10.0]];

        $before = $this->dilTarget($this->row(['dil' => 20, 'price' => 40.00]), $ctx);
        $after = $this->dilTarget($this->row(['dil' => 20, 'price' => 42.50]), $ctx);

        $this->assertNotNull($before);
        $this->assertNotNull($after);
        $this->assertSame(42.5, $before['sprice']);
        $this->assertSame($before['sprice'], $after['sprice']);
    }

    public function test_diff_boost_is_off_when_std_is_within_the_threshold_of_the_unboosted_price(): void
    {
        $ctx = $this->dilContext() + ['diff_adj' => ['gt_pct' => 20.0, 'points' => 10.0]];

        // Std 45 is 12.5% above the 40.00 base: no boost, whatever the live price is.
        $low = $this->dilTarget($this->row(['dil' => 20, 'standard_price' => 45, 'price' => 30.00]), $ctx);
        $high = $this->dilTarget($this->row(['dil' => 20, 'standard_price' => 45, 'price' => 44.00]), $ctx);

        $this->assertNotNull($low);
        $this->assertSame(40.0, $low['sprice']);
        $this->assertSame(40.0, $high['sprice']);
    }

    public function test_diff_boost_needs_a_listed_price(): void
    {
        $ctx = $this->dilContext() + ['diff_adj' => ['gt_pct' => 20.0, 'points' => 10.0]];

        $out = $this->dilTarget($this->row(['dil' => 20, 'price' => 0]), $ctx);

        $this->assertNotNull($out);
        $this->assertSame(40.0, $out['sprice']);
    }

    public function test_extreme_price_change_is_held_for_a_person(): void
    {
        $svc = new AlibabaSprcDilAutoPushService;

        // A 5.67 listing cut to 0.57 is a pack listing priced from a single-unit cost, not a reprice.
        $this->assertNotNull($svc->extremeChangeReason(['price' => 5.67], 0.57));
        $this->assertStringContainsString('not pushed, check the listing', (string) $svc->extremeChangeReason(['price' => 5.67], 0.57));
        $this->assertNotNull($svc->extremeChangeReason(['price' => 10.0], 30.0));

        // Ordinary moves go out, including the big but real ones seen in production.
        $this->assertNull($svc->extremeChangeReason(['price' => 29.59], 20.66));
        $this->assertNull($svc->extremeChangeReason(['price' => 4.84], 9.15));
        $this->assertNull($svc->extremeChangeReason(['price' => 10.0], 25.0));
        $this->assertNull($svc->extremeChangeReason(['price' => 10.0], 4.0));
        // No live price or no target: nothing to compare.
        $this->assertNull($svc->extremeChangeReason(['price' => 0], 5.0));
        $this->assertNull($svc->extremeChangeReason(['price' => 5.0], 0.0));
    }

    public function test_the_extreme_change_message_counts_as_permanent_so_the_page_does_not_show_blue(): void
    {
        $msg = (string) (new AlibabaSprcDilAutoPushService)->extremeChangeReason(['price' => 5.67], 0.57);

        $this->assertTrue(\App\Support\AlibabaPushError::isPermanentMessage($msg));
    }

    public function test_saved_sprice_target_is_used_when_the_rule_gives_no_price(): void
    {
        $svc = new AlibabaSprcDilAutoPushService;

        $out = $svc->savedSpriceTarget(['inv' => 5, 'sprice' => 52.82, 'margin' => 80, 'lp' => 10]);
        $this->assertNotNull($out);
        $this->assertSame(52.82, $out['sprice']);

        $this->assertNull($svc->savedSpriceTarget(['inv' => 0, 'sprice' => 52.82]));
        $this->assertNull($svc->savedSpriceTarget(['inv' => 5, 'sprice' => 0]));
    }
}
