<?php

namespace Tests\Unit;

use App\Support\NegativeSnroiPushGuard;
use PHPUnit\Framework\TestCase;

class NegativeSnroiPushGuardTest extends TestCase
{
    public function test_negative_snroi_is_below_zero(): void
    {
        $snroi = NegativeSnroiPushGuard::percent(10, 0.80, 20, 5, 0);

        $this->assertNotNull($snroi);
        $this->assertLessThan(0, $snroi);
    }

    public function test_positive_snroi_stays_above_zero(): void
    {
        $snroi = NegativeSnroiPushGuard::percent(100, 0.80, 20, 5, 0);

        $this->assertNotNull($snroi);
        $this->assertGreaterThan(0, $snroi);
    }

    public function test_missing_lp_cannot_be_judged(): void
    {
        $this->assertNull(NegativeSnroiPushGuard::percent(50, 0.80, 0, 0, 0));
    }
}
