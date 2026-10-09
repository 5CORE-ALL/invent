<?php

namespace Tests\Unit;

use App\Support\CpMasterDil;
use App\Support\DilVsSbidListingRollup;
use PHPUnit\Framework\TestCase;

class DilVsSbidListingRollupTest extends TestCase
{
    public function test_one_sku_is_unchanged(): void
    {
        $out = DilVsSbidListingRollup::combine([
            ['sku' => 'RED', 'quantity' => 2, 'inv' => 10, 'views' => 100, 'ebay_l30' => 4, 'ebay_l60' => 6, 'l7_views' => 20, 'npft' => 12.5],
        ]);

        $this->assertSame('RED', $out['sku']);
        $this->assertSame(2.0, $out['quantity']);
        $this->assertSame(10.0, $out['inv']);
        $this->assertSame(100.0, $out['views']);
        $this->assertSame(4.0, $out['ebay_l30']);
        $this->assertSame(6.0, $out['ebay_l60']);
        $this->assertSame(20.0, $out['l7_views']);
        $this->assertSame(12.5, $out['npft']);
        $this->assertSame(20.0, CpMasterDil::slabPercent($out['quantity'], $out['inv']));
    }

    public function test_variation_skus_sum_dil_inputs_and_average_npft(): void
    {
        $out = DilVsSbidListingRollup::combine([
            ['sku' => 'RED', 'quantity' => 2, 'inv' => 10, 'views' => 80, 'ebay_l30' => 2, 'ebay_l60' => 3, 'l7_views' => 10, 'npft' => 10.0],
            ['sku' => 'BLUE', 'quantity' => 4, 'inv' => 10, 'views' => 20, 'ebay_l30' => 6, 'ebay_l60' => 5, 'l7_views' => 30, 'npft' => 20.0],
        ]);

        $this->assertSame(6.0, $out['quantity']);
        $this->assertSame(20.0, $out['inv']);
        $this->assertSame(80.0, $out['views']);
        $this->assertSame(6.0, $out['ebay_l30']);
        $this->assertSame(5.0, $out['ebay_l60']);
        $this->assertSame(30.0, $out['l7_views']);
        $this->assertSame(15.0, $out['npft']);
        $this->assertSame(30.0, CpMasterDil::slabPercent($out['quantity'], $out['inv']));
    }

    public function test_listing_metrics_are_not_tripled_when_copied_onto_each_variation(): void
    {
        $out = DilVsSbidListingRollup::combine([
            ['sku' => 'RED', 'quantity' => 1, 'inv' => 5, 'views' => 40, 'ebay_l30' => 12, 'ebay_l60' => 8, 'l7_views' => 9, 'npft' => 10.0],
            ['sku' => 'BLUE', 'quantity' => 2, 'inv' => 5, 'views' => 40, 'ebay_l30' => 12, 'ebay_l60' => 8, 'l7_views' => 9, 'npft' => 10.0],
            ['sku' => 'GREEN', 'quantity' => 3, 'inv' => 5, 'views' => 40, 'ebay_l30' => 12, 'ebay_l60' => 8, 'l7_views' => 9, 'npft' => 10.0],
        ]);

        $this->assertSame(6.0, $out['quantity']);
        $this->assertSame(15.0, $out['inv']);
        $this->assertSame(40.0, $out['views']);
        $this->assertSame(12.0, $out['ebay_l30']);
        $this->assertSame(8.0, $out['ebay_l60']);
        $this->assertSame(9.0, $out['l7_views']);
    }
}
