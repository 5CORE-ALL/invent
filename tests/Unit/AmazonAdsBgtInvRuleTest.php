<?php

namespace Tests\Unit;

use App\Support\AmazonAdsBgtInvRule;
use PHPUnit\Framework\TestCase;

class AmazonAdsBgtInvRuleTest extends TestCase
{
    public function test_defaults_cover_inventory_and_start_at_zero_budget(): void
    {
        $bands = AmazonAdsBgtInvRule::defaultBands();
        $this->assertCount(3, $bands);
        $this->assertSame(0.0, (float) $bands[2]['inv_from']);
        $this->assertSame(9999.0, (float) $bands[0]['inv_to']);
        foreach ($bands as $band) {
            $this->assertSame(0, $band['bgt']);
        }
    }

    public function test_apply_uses_first_matching_slab(): void
    {
        $rule = [
            'bands' => [
                ['inv_from' => 50, 'inv_to' => 9999, 'bgt' => 3, 'label' => 'High', 'color' => '#e83e8c'],
                ['inv_from' => 10, 'inv_to' => 50, 'bgt' => 2, 'label' => 'Mid', 'color' => '#28a745'],
                ['inv_from' => 0, 'inv_to' => 10, 'bgt' => 1, 'label' => 'Low', 'color' => '#a00211'],
            ],
        ];
        $this->assertSame(3, AmazonAdsBgtInvRule::apply(80.0, $rule)['bgt']);
        $this->assertSame(2, AmazonAdsBgtInvRule::apply(10.0, $rule)['bgt']);
        $this->assertSame(1, AmazonAdsBgtInvRule::apply(0.0, $rule)['bgt']);
        $this->assertNull(AmazonAdsBgtInvRule::apply(null, $rule)['bgt']);
    }

    public function test_normalize_rejects_from_greater_than_to(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AmazonAdsBgtInvRule::normalizeRule([
            'bands' => [
                ['inv_from' => 40, 'inv_to' => 10, 'bgt' => 1, 'label' => 'Bad', 'color' => '#111111'],
            ],
        ]);
    }
}
