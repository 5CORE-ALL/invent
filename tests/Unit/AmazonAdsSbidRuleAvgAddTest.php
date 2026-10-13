<?php

namespace Tests\Unit;

use App\Support\AmazonAdsSbidRule;
use PHPUnit\Framework\TestCase;

class AmazonAdsSbidRuleAvgAddTest extends TestCase
{
    public function test_default_avg_cpc_add_is_ten_cents(): void
    {
        $this->assertSame(0.10, AmazonAdsSbidRule::defaults()['avg_cpc_add']);
        $this->assertSame(0.10, AmazonAdsSbidRule::normalizeRule([])['avg_cpc_add']);
    }

    public function test_avg_cpc_add_is_saved_from_input(): void
    {
        $rule = AmazonAdsSbidRule::normalizeRule(['avg_cpc_add' => '0.25']);
        $this->assertSame(0.25, $rule['avg_cpc_add']);
    }

    public function test_avg_cpc_add_rejects_negative_and_huge_values(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AmazonAdsSbidRule::normalizeRule(['avg_cpc_add' => -1]);
    }

    public function test_avg_cpc_add_rejects_values_above_five(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AmazonAdsSbidRule::normalizeRule(['avg_cpc_add' => 6]);
    }
}
