<?php

namespace Tests\Unit;

use App\Support\AmazonAcosSbgtRule;
use App\Support\AmazonAdsSbgt;
use PHPUnit\Framework\TestCase;

class AmazonAdsSbgtTest extends TestCase
{
    public function test_sum_is_null_when_all_parts_missing(): void
    {
        $this->assertNull(AmazonAdsSbgt::sumFromParts(null, null, null, null, null));
        $this->assertNull(AmazonAdsSbgt::sumFromParts('', '', null));
    }

    public function test_sum_adds_present_parts(): void
    {
        $this->assertSame(10, AmazonAdsSbgt::sumFromParts(2, 3, 4, 1, 0));
        $this->assertSame(8, AmazonAdsSbgt::sumFromParts(2, 3, 3, null, null));
    }

    public function test_explicit_bgt_acos_zero_zeros_the_total(): void
    {
        $this->assertSame(0, AmazonAdsSbgt::sumFromParts(5, 4, 0, 3, 2));
        $this->assertSame(0, AmazonAdsSbgt::sumFromParts(null, null, 0, null, null));
        $this->assertSame(0, AmazonAdsSbgt::sumFromParts(5, 4, '0', 3, 2));
    }

    public function test_zero_sum_of_present_parts_is_zero_not_null(): void
    {
        $this->assertSame(1, AmazonAdsSbgt::sumFromParts(0, 0, 1, 0, 0));
        $this->assertSame(0, AmazonAdsSbgt::sumFromParts(0, null, null));
    }

    public function test_is_explicit_zero(): void
    {
        $this->assertTrue(AmazonAdsSbgt::isExplicitZero(0));
        $this->assertTrue(AmazonAdsSbgt::isExplicitZero('0'));
        $this->assertTrue(AmazonAdsSbgt::isExplicitZero(0.4));
        $this->assertFalse(AmazonAdsSbgt::isExplicitZero(null));
        $this->assertFalse(AmazonAdsSbgt::isExplicitZero(''));
        $this->assertFalse(AmazonAdsSbgt::isExplicitZero(1));
        $this->assertFalse(AmazonAdsSbgt::isExplicitZero('x'));
    }

    public function test_parse_pushable_budget_rejects_zero(): void
    {
        $this->assertSame(12, AmazonAdsSbgt::parsePushableBudget(12));
        $this->assertNull(AmazonAdsSbgt::parsePushableBudget(0));
        $this->assertNull(AmazonAdsSbgt::parsePushableBudget(null));
        $this->assertNull(AmazonAdsSbgt::parsePushableBudget(10000));
    }

    public function test_acos_rule_accepts_sbgt_zero(): void
    {
        $rule = AmazonAcosSbgtRule::normalizeRule([
            'bands' => [
                ['acos_from' => 40, 'acos_to' => 9999, 'sbgt' => 0, 'label' => 'Pause', 'color' => '#dc2626'],
                ['acos_from' => 0, 'acos_to' => 40, 'sbgt' => 10, 'label' => 'Ok', 'color' => '#16a34a'],
            ],
        ]);
        $this->assertSame(0, $rule['bands'][0]['sbgt']);
        $this->assertSame(10, $rule['bands'][1]['sbgt']);
    }

    public function test_acos_rule_rejects_negative_sbgt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AmazonAcosSbgtRule::normalizeRule([
            'bands' => [
                ['acos_from' => 0, 'acos_to' => 9999, 'sbgt' => -1, 'label' => 'Bad', 'color' => '#111111'],
            ],
        ]);
    }

    public function test_acos_is_100_when_spend_exists_and_sold_is_zero(): void
    {
        $this->assertSame(100.0, AmazonAcosSbgtRule::acosPercentForSbgtFromReportRow([
            'cost' => 12.5,
            'purchases30d' => 0,
            'sales30d' => 0,
        ]));
        $this->assertSame(100.0, AmazonAcosSbgtRule::acosPercentForSbgtFromReportRow([
            'cost' => 0,
            'spend' => 8,
            'purchases30d' => 0,
            'sales30d' => 40,
        ]));
        $this->assertSame(100.0, AmazonAcosSbgtRule::acosPercentForSbgtFromReportRow([
            'cost' => 0,
            'L7spend' => 4.2,
            'Prchase' => 0,
            'sales30d' => 0,
        ]));
    }

    public function test_acos_is_100_when_spend_exists_and_sales_missing(): void
    {
        $this->assertSame(100.0, AmazonAcosSbgtRule::acosPercentForSbgtFromReportRow([
            'cost' => 5,
            'purchases30d' => 0,
        ]));
    }

    public function test_acos_is_ratio_when_sold_and_sales_exist(): void
    {
        $this->assertSame(25.0, AmazonAcosSbgtRule::acosPercentForSbgtFromReportRow([
            'cost' => 10,
            'purchases30d' => 2,
            'sales30d' => 40,
        ]));
    }

    public function test_acos_is_zero_when_no_spend_and_no_sales(): void
    {
        $this->assertSame(0.0, AmazonAcosSbgtRule::acosPercentForSbgtFromReportRow([
            'cost' => 0,
            'purchases30d' => 0,
            'sales30d' => 0,
        ]));
    }

    public function test_acos_is_null_when_row_has_no_metrics(): void
    {
        $this->assertNull(AmazonAcosSbgtRule::acosPercentForSbgtFromReportRow([]));
    }

    public function test_bgt_acos_uses_highest_band_for_saved_100_percent(): void
    {
        $bands = [
            ['acos_from' => 40, 'acos_to' => 99, 'sbgt' => 1, 'label' => 'Red', 'color' => '#dc2626'],
            ['acos_from' => 0, 'acos_to' => 10, 'sbgt' => 12, 'label' => 'Pink', 'color' => '#db2777'],
        ];
        $this->assertSame(1, AmazonAcosSbgtRule::sbgtFromAcosAndBands(100.0, $bands));
        $this->assertSame(1, AmazonAcosSbgtRule::sbgtFromAcosAndBands(40.0, $bands));
        $this->assertSame(12, AmazonAcosSbgtRule::sbgtFromAcosAndBands(0.0, $bands));
    }
}
