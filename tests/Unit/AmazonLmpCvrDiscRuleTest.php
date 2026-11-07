<?php

namespace Tests\Unit;

use App\Support\AmazonLmpCvrDiscRule;
use PHPUnit\Framework\TestCase;

class AmazonLmpCvrDiscRuleTest extends TestCase
{
    private function cfg(array $over = []): array
    {
        return AmazonLmpCvrDiscRule::normalize(array_merge(['enabled' => true, 'need_lmp' => true], $over));
    }

    public function test_pages_and_save_all_groups(): void
    {
        $this->assertSame(['temu', 'newtemutwo', 'temu3'], AmazonLmpCvrDiscRule::groupFor('newtemutwo'));
        $this->assertSame(['ebay1', 'ebay2', 'ebay3'], AmazonLmpCvrDiscRule::groupFor('ebay2'));
        $this->assertContains('temu3', AmazonLmpCvrDiscRule::pageChannels());
        $this->assertSame('temu3_lmp_cvr_disc_rule', AmazonLmpCvrDiscRule::keyFor('temu3'));
    }

    public function test_takes_disc_pct_of_std_off_the_final_price_on_a_down_arrow(): void
    {
        // CVR 3 < 4 on a Down arrow (CVR 3 vs CVR 60 5) -> 6% of Std 100 = $6 off the capped S PRC 90.
        [$price, $pct] = AmazonLmpCvrDiscRule::applyToPrice(90.0, 100.0, 5.0, 80.0, 3.0, 5.0, $this->cfg());
        $this->assertSame(84.0, $price);
        $this->assertSame(6.0, $pct);
    }

    public function test_cvr_zero_is_a_down_arrow_like_the_temu_column(): void
    {
        [$price, $pct] = AmazonLmpCvrDiscRule::applyToPrice(90.0, 100.0, 5.0, 80.0, 0.0, 0.0, $this->cfg());
        $this->assertSame(84.0, $price);
        $this->assertSame(6.0, $pct);
    }

    public function test_needs_lmp_when_asked_and_skips_a_flat_arrow_and_off_rule(): void
    {
        $this->assertSame([90.0, 0.0], AmazonLmpCvrDiscRule::applyToPrice(90.0, 100.0, 5.0, 0.0, 3.0, 5.0, $this->cfg()));
        $this->assertSame([84.0, 6.0], AmazonLmpCvrDiscRule::applyToPrice(90.0, 100.0, 5.0, 0.0, 3.0, 5.0, $this->cfg(['need_lmp' => false])));
        $this->assertSame([90.0, 0.0], AmazonLmpCvrDiscRule::applyToPrice(90.0, 100.0, 5.0, 80.0, 3.0, 3.05, $this->cfg()));
        $this->assertSame([90.0, 0.0], AmazonLmpCvrDiscRule::applyToPrice(90.0, 100.0, 5.0, 80.0, 3.0, 5.0, $this->cfg(['enabled' => false])));
    }

    public function test_needs_stock_and_a_std_price_and_halves_under_15(): void
    {
        $this->assertSame([90.0, 0.0], AmazonLmpCvrDiscRule::applyToPrice(90.0, 100.0, 0.0, 80.0, 3.0, 5.0, $this->cfg()));
        $this->assertSame([90.0, 0.0], AmazonLmpCvrDiscRule::applyToPrice(90.0, 0.0, 5.0, 80.0, 3.0, 5.0, $this->cfg()));
        // Std 10 (< $15): 6% halves to 3% -> $0.30 off 9.00.
        [$price, $pct] = AmazonLmpCvrDiscRule::applyToPrice(9.0, 10.0, 5.0, 8.0, 3.0, 5.0, $this->cfg());
        $this->assertSame(8.7, $price);
        $this->assertSame(3.0, $pct);
    }
}
