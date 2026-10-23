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

    public function test_clearance_sku_has_no_target_like_amazon(): void
    {
        // Clearance SKUs show "C" in S PRC. Nothing is saved or pushed for them.
        $this->assertNull($this->target($this->row(['lp' => 70, 'ship' => 8, 'clearance' => true])));
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

    /** @return list<array{min:float,max:float,cap:float|null}> */
    private function capSlabs(?float $cap): array
    {
        return [['min' => 0.0, 'max' => 100.0, 'cap' => $cap]];
    }

    public function test_snroi_cap_prices_s_prc_from_the_target_with_ship_and_tacos(): void
    {
        // LP 20, Ship 5, take-home 0.80, TACOS 5%. Cap 100% -> (20 x 2 + 5) / (0.80 - 0.05) = 60.00 (the 90.00 discount price is not used).
        $out = $this->target($this->row(['tacos' => 5.0]), $this->pricer(['snroi_cap' => $this->capSlabs(100)]));

        $this->assertNotNull($out);
        $this->assertSame(60.0, $out['sprice']);
        // The page SNROI% = (S PRC x take-home - Ship - LP - S PRC x Ads%) / LP lands on the target.
        $snroi = ((60.0 * 0.80) - 5 - 20 - (60.0 * 0.05)) / 20 * 100;
        $this->assertEqualsWithDelta(100.0, $snroi, 0.01);
    }

    public function test_tiktok2_cap_has_no_ads(): void
    {
        // TikTok 2 never subtracts Ads%, even when the row carries a TACOS%: (20 x 2 + 5) / 0.80 = 56.25.
        $out = $this->target(
            $this->row(['tacos' => 5.0]),
            $this->pricer(['snroi_cap' => $this->capSlabs(100)], 'tiktok2'),
            0.0,
            'tiktok2'
        );

        $this->assertNotNull($out);
        $this->assertSame(56.25, $out['sprice']);
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

    public function test_lmp_cap_still_applies_after_the_snroi_cap(): void
    {
        // Cap 1000% holds at Std 100, then LMP 80 (SGROI at LMP is far above 20%) pulls it to 80.
        $out = $this->target($this->row(['lmp' => 80]), $this->pricer(['snroi_cap' => $this->capSlabs(1000)]));

        $this->assertNotNull($out);
        $this->assertSame(80.0, $out['sprice']);
        $this->assertTrue($out['lmp_capped']);
    }
}
