<?php

namespace Tests\Unit;

use App\Support\CpMasterDil;
use PHPUnit\Framework\TestCase;

class CpMasterDilTest extends TestCase
{
    public function test_rounds_ov_l30_over_inventory_like_cp_master(): void
    {
        $this->assertSame(50, CpMasterDil::percent(10, 20));
        $this->assertSame(33, CpMasterDil::percent(1, 3));
        $this->assertSame(0, CpMasterDil::percent(0, 20));
        $this->assertSame(101, CpMasterDil::percent(101, 100));
    }

    public function test_inventory_zero_and_missing_are_not_dil_zero(): void
    {
        $this->assertNull(CpMasterDil::percent(5, 0));
        $this->assertNull(CpMasterDil::percent(5, null));
        $this->assertNull(CpMasterDil::percent(null, 10));
        $this->assertNull(CpMasterDil::percent('', 10));
    }
}
