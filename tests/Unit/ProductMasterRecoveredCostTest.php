<?php

namespace Tests\Unit;

use App\Models\ProductMaster;
use Tests\TestCase;

class ProductMasterRecoveredCostTest extends TestCase
{
    public function test_unit_landed_price_reads_values_lp(): void
    {
        $pm = new ProductMaster();
        $pm->Values = ['lp' => 12.5];

        $this->assertSame(12.5, $pm->unitLandedPrice());
    }

    public function test_recovered_cost_is_lp_times_qty(): void
    {
        $pm = new ProductMaster();
        $pm->Values = ['lp' => 18.4];

        $this->assertSame(36.8, ProductMaster::recoveredCostUsd($pm, 2, 2));
        $this->assertSame(37.0, ProductMaster::recoveredCostUsd($pm, 2, 0));
    }

    public function test_recovered_cost_is_null_without_lp_or_qty(): void
    {
        $pm = new ProductMaster();
        $pm->Values = ['lp' => 0];

        $this->assertNull(ProductMaster::recoveredCostUsd($pm, 3, 2));
        $this->assertNull(ProductMaster::recoveredCostUsd(null, 3, 2));

        $pm->Values = ['lp' => 10];
        $this->assertNull(ProductMaster::recoveredCostUsd($pm, 0, 2));
    }
}
