<?php

namespace Tests\Unit;

use App\Support\AmazonAdsCampaignSkuMetrics;
use PHPUnit\Framework\TestCase;

class AmazonAdsInvFilterTest extends TestCase
{
    public function test_inv_zero_includes_zero_and_missing(): void
    {
        $this->assertTrue(AmazonAdsCampaignSkuMetrics::invMatchesFilter(0, 'zero'));
        $this->assertTrue(AmazonAdsCampaignSkuMetrics::invMatchesFilter(0.0, 'zero'));
        $this->assertTrue(AmazonAdsCampaignSkuMetrics::invMatchesFilter(null, 'zero'));
        $this->assertTrue(AmazonAdsCampaignSkuMetrics::invMatchesFilter('', 'zero'));
        $this->assertTrue(AmazonAdsCampaignSkuMetrics::invMatchesFilter(0.4, 'zero'));
        $this->assertFalse(AmazonAdsCampaignSkuMetrics::invMatchesFilter(1, 'zero'));
        $this->assertFalse(AmazonAdsCampaignSkuMetrics::invMatchesFilter(0.6, 'zero'));
    }

    public function test_inv_gt_is_rounded_inventory_above_zero(): void
    {
        $this->assertTrue(AmazonAdsCampaignSkuMetrics::invMatchesFilter(3, 'gt'));
        $this->assertTrue(AmazonAdsCampaignSkuMetrics::invMatchesFilter(0.6, 'gt'));
        $this->assertFalse(AmazonAdsCampaignSkuMetrics::invMatchesFilter(0, 'gt'));
        $this->assertFalse(AmazonAdsCampaignSkuMetrics::invMatchesFilter(0.4, 'gt'));
        $this->assertFalse(AmazonAdsCampaignSkuMetrics::invMatchesFilter(null, 'gt'));
    }

    public function test_unknown_mode_keeps_every_row(): void
    {
        $this->assertTrue(AmazonAdsCampaignSkuMetrics::invMatchesFilter(0, 'all'));
        $this->assertTrue(AmazonAdsCampaignSkuMetrics::invMatchesFilter(4, ''));
    }
}
