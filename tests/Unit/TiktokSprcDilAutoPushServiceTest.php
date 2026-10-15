<?php

namespace Tests\Unit;

use App\Services\TiktokSprcDilAutoPushService;
use App\Support\StdPrcVsDilPricer;
use PHPUnit\Framework\TestCase;

class TiktokSprcDilAutoPushServiceTest extends TestCase
{
    private function pricer(array $over = [], string $channel = 'tiktok'): StdPrcVsDilPricer
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
        ], $over), $channel);
    }

    /** @return array<string, mixed> */
    private function row(array $over = []): array
    {
        return array_merge([
            'sku' => 'TT-TEST',
            'inv' => 10,
            'dil' => 20,
            'tt_l30' => 2,
            'age_days' => 10,
            'lp' => 20,
            'ship' => 5,
            'margin' => 0.80,
            'standard_price' => 100,
            'lmp' => 0,
            'clearance' => false,
        ], $over);
    }

    private function target(array $row, ?StdPrcVsDilPricer $pricer = null, float $ads = 0.0, string $channel = 'tiktok'): ?array
    {
        return (new TiktokSprcDilAutoPushService($channel))->computeTarget($row, $pricer ?? $this->pricer(), $ads);
    }

    public function test_price_is_std_minus_age_dil_and_b_disc(): void
    {
        $out = $this->target($this->row());

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2) / 100) = 90.00. NPFT is far above 10%.
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
        $this->assertFalse($out['lmp_capped']);
    }

    public function test_zero_sold_uses_tt_l30(): void
    {
        $out = $this->target($this->row(['tt_l30' => 0]));

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2 + 4) / 100) = 86.00
        $this->assertEqualsWithDelta(86.00, $out['sprice'], 0.001);
    }

    public function test_clearance_keeps_the_discount_price(): void
    {
        $out = $this->target($this->row(['standard_price' => 120, 'lp' => 70, 'ship' => 10, 'clearance' => true]));

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(108.00, $out['sprice'], 0.001);
    }

    public function test_lmp_below_the_price_caps_to_lmp(): void
    {
        $out = $this->target($this->row(['lmp' => 85]));

        $this->assertNotNull($out);
        // SGROI at LMP 85 is 215% (≥ 20%), so the price drops to LMP.
        $this->assertEqualsWithDelta(85.00, $out['sprice'], 0.001);
        $this->assertTrue($out['lmp_capped']);
        $this->assertEqualsWithDelta(90.00, $out['uncapped'], 0.001);
    }

    public function test_lmp_above_the_price_does_not_raise_it(): void
    {
        $out = $this->target($this->row(['lmp' => 120]));

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
        $this->assertFalse($out['lmp_capped']);
    }

    public function test_tiktok2_uses_its_own_channel_key(): void
    {
        $this->assertSame('tiktok2', TiktokSprcDilAutoPushService::normalizeChannel('tiktok_2'));
        $this->assertSame('tiktok', TiktokSprcDilAutoPushService::normalizeChannel('anything'));

        $out = $this->target($this->row(['tt_l30' => 0]), $this->pricer([], 'tiktok2'), 0.0, 'tiktok2');
        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(86.00, $out['sprice'], 0.001);
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
}
