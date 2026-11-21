<?php

namespace Tests\Unit;

use App\Support\AmazonCvrPriceRule;
use PHPUnit\Framework\TestCase;

class AmazonCvrPriceRuleUpperCapTest extends TestCase
{
    private function row(): array
    {
        // CVR L30 15% vs LT O CVR 5%: the Up case. LP 5, ship 1, take-home 0.80.
        return ['a_l30' => 15.0, 'sess30' => 100.0, 'a_l60' => 5.0, 'sess60' => 100.0, 'price' => 10.0, 'lp' => 5.0, 'ship' => 1.0];
    }

    private function cfg(array $over = []): array
    {
        return $over + ['enabled' => true, 'down' => null, 'same' => null, 'up' => 50.0, 'pink' => null, 'down_band' => 20.0, 'up_band' => 20.0];
    }

    public function test_no_cap_means_no_ceiling_at_all(): void
    {
        $r = AmazonCvrPriceRule::apply(10.0, $this->row(), $this->cfg(), 0.0);
        $this->assertSame(15.0, $r['price']);
        $this->assertFalse($r['capped']);
    }

    public function test_upper_cap_holds_the_price_at_max_nroi(): void
    {
        // NROI 100% -> (5 * 2 + 1) / 0.80 = 13.75
        $r = AmazonCvrPriceRule::apply(10.0, $this->row(), $this->cfg(['upper_cap' => true, 'max_nroi' => 100.0]), 0.0);
        $this->assertSame(13.75, $r['price']);
        $this->assertTrue($r['capped']);
    }

    public function test_whichever_limit_is_hit_first_wins(): void
    {
        // NPFT 30% -> (1 + 5) / (0.80 - 0.30) = 12.00 is lower than the NROI price 13.75.
        $r = AmazonCvrPriceRule::apply(10.0, $this->row(), $this->cfg(['upper_cap' => true, 'max_nroi' => 100.0, 'max_npft' => 30.0]), 0.0);
        $this->assertSame(12.0, $r['price']);
    }

    public function test_up_never_lowers_a_price_already_above_the_ceiling(): void
    {
        $r = AmazonCvrPriceRule::apply(20.0, $this->row(), $this->cfg(['upper_cap' => true, 'max_nroi' => 100.0]), 0.0);
        $this->assertNull($r);
    }

    public function test_upper_cap_off_ignores_the_maximums(): void
    {
        $r = AmazonCvrPriceRule::apply(10.0, $this->row(), $this->cfg(['upper_cap' => false, 'max_nroi' => 100.0]), 0.0);
        $this->assertSame(15.0, $r['price']);
    }

    public function test_no_lifetime_cvr_starts_from_zero_percent(): void
    {
        // No sessions, but sold 1 (inconsistent data): CVR L30 0%, LT O CVR starts at 0% -> within target.
        $row = ['a_l30' => 1.0, 'sess30' => 0.0, 'a_l60' => 0.0, 'sess60' => 0.0, 'price' => 10.0, 'lp' => 5.0, 'ship' => 1.0];
        $cfg = $this->cfg(['same' => 5.0, 'up' => 50.0]);
        $r = AmazonCvrPriceRule::apply(10.0, $row, $cfg, 0.0);
        $this->assertSame(10.5, $r['price']);
    }

    public function test_sold_zero_is_always_cvr_down(): void
    {
        $row = ['a_l30' => 0.0, 'sess30' => 0.0, 'a_l60' => 0.0, 'sess60' => 0.0, 'price' => 10.0, 'lp' => 5.0, 'ship' => 1.0];
        $cfg = $this->cfg(['down' => 5.0, 'same' => 9.0, 'up' => 50.0]);
        $r = AmazonCvrPriceRule::apply(10.0, $row, $cfg, 0.0);
        $this->assertSame(9.5, $r['price']); // Down always lowers, even with no lifetime CVR
        // Sold 0 with sessions and a lifetime CVR inside the band is still Down.
        $row2 = ['a_l30' => 0.0, 'sess30' => 50.0, 'a_l60' => 0.0, 'sess60' => 50.0, 'price' => 10.0, 'lp' => 5.0, 'ship' => 1.0];
        $r = AmazonCvrPriceRule::apply(10.0, $row2, $cfg, 0.0);
        $this->assertSame(9.5, $r['price']);
    }
}
