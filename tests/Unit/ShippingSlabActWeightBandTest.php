<?php

namespace Tests\Unit;

use App\Services\ShippingSlabRateService;
use PHPUnit\Framework\TestCase;

class ShippingSlabActWeightBandTest extends TestCase
{
    public function test_multi_piece_weight_moves_into_the_heavier_slab(): void
    {
        $slabs = new ShippingSlabRateService();

        $this->assertSame('oz_4', $slabs->slabKeyForActWeight(0.20));
        $this->assertSame('oz_6', $slabs->slabKeyForActWeight(0.40));
        $this->assertSame('oz_1599', $slabs->slabKeyForActWeight(0.80));
        $this->assertSame('oz_1599', $slabs->slabKeyForActWeight(1.0));
        $this->assertSame('lb_101_2', $slabs->slabKeyForActWeight(1.20));
        $this->assertSame('lb_1001_20', $slabs->slabKeyForActWeight(12.0));
        $this->assertSame('lb_20_30', $slabs->slabKeyForActWeight(24.0));
    }

    public function test_band_lookup_matches_the_slab_for_act_weight(): void
    {
        $slabs = new ShippingSlabRateService();
        $bands = $slabs->actWeightSlabBands();
        $weights = [0.0, 0.01, 0.25, 0.26, 0.50, 0.51, 0.75, 0.76, 1.0, 1.01, 2.0, 2.01, 5.0, 5.01, 10.0, 10.01, 20.0, 20.01, 50.0, 50.01, 80.5];

        foreach ($weights as $weight) {
            $this->assertSame(
                $slabs->slabKeyForActWeight($weight),
                ShippingSlabRateService::bandKeyForWeight($bands, $weight),
                'weight '.$weight
            );
        }
    }
}
