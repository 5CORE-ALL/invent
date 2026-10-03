<?php

namespace Tests\Unit;

use App\Support\AmazonAdsStoredLiveBid;
use PHPUnit\Framework\TestCase;

class AmazonAdsStoredLiveBidTest extends TestCase
{
    public function test_newest_daily_bid_wins_over_older_and_l30(): void
    {
        $picked = AmazonAdsStoredLiveBid::pick([
            ['report_date_range' => 'L30', 'last_sbid' => 0.40],
            ['report_date_range' => '2026-10-03', 'last_sbid' => 0.55],
            ['report_date_range' => '2026-10-04', 'last_sbid' => 0.62],
            ['report_date_range' => 'L1', 'last_sbid' => ''],
        ]);

        $this->assertSame(0.62, $picked);
    }

    public function test_l1_is_used_when_no_daily_bid_is_stored(): void
    {
        $picked = AmazonAdsStoredLiveBid::pick([
            ['report_date_range' => 'L30', 'last_sbid' => 0.40],
            ['report_date_range' => 'L1', 'last_sbid' => 0.51],
            ['report_date_range' => '2026-10-04', 'last_sbid' => null],
        ]);

        $this->assertSame(0.51, $picked);
    }

    public function test_blank_means_missing_or_not_positive(): void
    {
        $this->assertTrue(AmazonAdsStoredLiveBid::isBlank(null));
        $this->assertTrue(AmazonAdsStoredLiveBid::isBlank(''));
        $this->assertTrue(AmazonAdsStoredLiveBid::isBlank(0));
        $this->assertFalse(AmazonAdsStoredLiveBid::isBlank(0.62));
    }
}
