<?php

namespace Tests\Unit;

use App\Support\AmazonAdsLRangeMetrics;
use PHPUnit\Framework\TestCase;

class AmazonAdsLRangeMetricsTest extends TestCase
{
    public function test_l1_sales_prefers_sales1d_over_sales30d(): void
    {
        $cols = ['sales1d', 'sales30d', 'sales'];
        $this->assertSame(7.75, AmazonAdsLRangeMetrics::salesFromRow([
            'sales1d' => '7.7500',
            'sales30d' => '0.0000',
        ], $cols, 'L1'));
    }

    public function test_l1_sales_falls_back_to_sales_then_sales30d(): void
    {
        $this->assertSame(12.5, AmazonAdsLRangeMetrics::salesFromRow([
            'sales' => '12.50',
        ], ['sales', 'sales30d'], 'L1'));
        $this->assertSame(8.98, AmazonAdsLRangeMetrics::salesFromRow([
            'sales30d' => '8.9800',
        ], ['sales30d'], 'L1'));
    }

    public function test_l7_sales_prefers_sales7d(): void
    {
        $this->assertSame(4.4, AmazonAdsLRangeMetrics::salesFromRow([
            'sales7d' => '4.4000',
            'sales30d' => '20.0000',
        ], ['sales7d', 'sales30d'], 'L7'));
    }

    public function test_spend_prefers_cost_then_spend_and_skips_zero(): void
    {
        $cols = ['cost', 'spend'];
        $this->assertSame(1.28, AmazonAdsLRangeMetrics::spendFromRow([
            'cost' => '1.2800',
            'spend' => null,
        ], $cols));
        $this->assertSame(0.83, AmazonAdsLRangeMetrics::spendFromRow([
            'cost' => '0.0000',
            'spend' => '0.8300',
        ], $cols));
        $this->assertSame(0.0, AmazonAdsLRangeMetrics::spendFromRow([
            'cost' => '0.0000',
            'spend' => '0.0000',
        ], $cols));
    }

    public function test_prefer_amount_replaces_zero_with_later_positive(): void
    {
        $this->assertSame(1.29, AmazonAdsLRangeMetrics::preferAmount(0.0, 1.29));
        $this->assertSame(0.83, AmazonAdsLRangeMetrics::preferAmount(0.83, 0.0));
        $this->assertSame(0.0, AmazonAdsLRangeMetrics::preferAmount(null, 0.0));
        $this->assertNull(AmazonAdsLRangeMetrics::preferAmount(null, null));
    }
}
