<?php

namespace Tests\Unit;

use App\Support\AmazonCvrPriceRule;
use PHPUnit\Framework\TestCase;

class AmazonCvrPriceRulePinkTest extends TestCase
{
    private function row(float $aL30, float $sess30): array
    {
        // LT O CVR stays at 5% (aL60 5 of 100) so CVR L30 is far above it.
        return ['a_l30' => $aL30, 'sess30' => $sess30, 'a_l60' => 5.0, 'sess60' => 100.0, 'price' => 20.0, 'lp' => 5.0, 'ship' => 1.0];
    }

    public function test_pink_slab_raises_price_when_cvr_is_above_the_pink_target(): void
    {
        $cfg = ['enabled' => true, 'down' => -5.0, 'same' => null, 'up' => 2.0, 'pink' => 7.0, 'pink_above' => 12.99, 'down_band' => 20.0, 'up_band' => 20.0];
        // CVR L30 = 15 / 100 = 15% > 12.99 -> pink 7% (not the Up 2%).
        $r = AmazonCvrPriceRule::apply(20.0, $this->row(15.0, 100.0), $cfg, 0.0);
        $this->assertSame(21.4, $r['price']);
    }

    public function test_pink_slab_is_off_when_blank_or_below_the_target(): void
    {
        $cfg = ['enabled' => true, 'down' => null, 'same' => null, 'up' => 2.0, 'pink' => null, 'pink_above' => 12.99, 'down_band' => 20.0, 'up_band' => 20.0];
        $r = AmazonCvrPriceRule::apply(20.0, $this->row(15.0, 100.0), $cfg, 0.0);
        $this->assertSame(20.4, $r['price']); // blank pink -> normal Up case
        $cfg['pink'] = 7.0;
        // 12% <= 12.99: no pink. It is also between 7% and 13%, so it is within target (no Up), and "within" has no % here.
        $this->assertNull(AmazonCvrPriceRule::apply(20.0, $this->row(12.0, 100.0), $cfg, 0.0));
    }

    public function test_pink_target_is_dynamic(): void
    {
        $cfg = ['enabled' => true, 'down' => null, 'same' => null, 'up' => 2.0, 'pink' => 7.0, 'pink_above' => 10.0, 'down_band' => 20.0, 'up_band' => 20.0];
        $r = AmazonCvrPriceRule::apply(20.0, $this->row(12.0, 100.0), $cfg, 0.0); // 12% > 10
        $this->assertSame(21.4, $r['price']);
    }
}
