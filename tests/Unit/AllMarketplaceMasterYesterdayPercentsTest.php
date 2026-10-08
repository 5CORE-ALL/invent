<?php

namespace Tests\Unit;

use App\Support\Badges\AllMarketplaceMasterBadgeAggregator;
use PHPUnit\Framework\TestCase;

/**
 * Y GROI% / YNPFT% / YNROI% must be yesterday's own numbers. Rescaling the L30
 * percentages by Y Sales cancels that factor and reproduces G Roi / N PFT / N ROI
 * exactly, so the columns used to echo the 30-day figures on every row.
 */
class AllMarketplaceMasterYesterdayPercentsTest extends TestCase
{
    public function test_measured_yesterday_columns_are_used_instead_of_l30_rates(): void
    {
        $out = AllMarketplaceMasterBadgeAggregator::aggregate([
            [
                'Channel ' => 'Temu 2',
                'L30 Sales' => 43605,
                'Gprofit%' => 39.92,
                'N PFT' => 39.29,
                'G Roi' => 139.19,
                'N ROI' => 138.56,
                'cogs' => 12506.01,
                'Y Sales' => 1405,
                'Y Day Sales' => 1167.12,
                'Y GPFT $' => 265.72,
                'Y COGS' => 429.78,
                'Y Spend' => 11.67,
            ],
        ]);

        // 265.72 / 429.78 = 61.8%, nowhere near the 139.19% L30 G Roi.
        $this->assertSame(61.83, $out['y_groi_pct']);
        // (265.72 - 11.67) / 1167.12 = 21.77%, not the 39.29% L30 N PFT.
        $this->assertSame(21.77, $out['y_npft_pct']);
        $this->assertSame(59.11, $out['y_nroi_pct']);
    }

    public function test_falls_back_to_l30_rates_when_yesterday_columns_are_absent(): void
    {
        $rows = [
            [
                'Channel ' => 'Amazon',
                'L30 Sales' => 1000,
                'Y Sales' => 200,
                'Gprofit%' => 40,
                'N PFT' => 25,
                'cogs' => 600,
            ],
        ];

        $out = AllMarketplaceMasterBadgeAggregator::aggregate($rows);

        // Y COGS = 600 × (200 / 1000) = 120; Y net = 200 × 0.25 = 50.
        $this->assertSame(66.67, $out['y_groi_pct']);
        $this->assertSame(25.0, $out['y_npft_pct']);
        $this->assertSame(41.67, $out['y_nroi_pct']);
    }

    public function test_measured_and_unmeasured_channels_aggregate_together(): void
    {
        $out = AllMarketplaceMasterBadgeAggregator::aggregate([
            [
                'Channel ' => 'Amazon',
                'L30 Sales' => 1000,
                'Y Sales' => 200,
                'Gprofit%' => 40,
                'N PFT' => 25,
                'cogs' => 600,
            ],
            [
                'Channel ' => 'Temu',
                'L30 Sales' => 2000,
                'Y Sales' => 300,
                'Gprofit%' => 50,
                'N PFT' => 45,
                'cogs' => 900,
                'Y Day Sales' => 300,
                'Y GPFT $' => 60,
                'Y COGS' => 150,
                'Y Spend' => 15,
            ],
        ]);

        // Gross 80 + 60 = 140 over COGS 120 + 150 = 270.
        $this->assertSame(51.85, $out['y_groi_pct']);
        // Net 50 + 45 = 95 over sales 200 + 300 = 500.
        $this->assertSame(19.0, $out['y_npft_pct']);
        $this->assertSame(35.19, $out['y_nroi_pct']);
    }

    public function test_zero_measured_cogs_does_not_divide_by_zero(): void
    {
        $out = AllMarketplaceMasterBadgeAggregator::aggregate([
            [
                'Channel ' => 'Shein',
                'L30 Sales' => 500,
                'Y Sales' => 50,
                'Gprofit%' => 0,
                'N PFT' => 0,
                'cogs' => 0,
                'Y Day Sales' => 50,
                'Y GPFT $' => 0,
                'Y COGS' => 0,
                'Y Spend' => 0,
            ],
        ]);

        $this->assertSame(0.0, $out['y_groi_pct']);
        $this->assertSame(0.0, $out['y_nroi_pct']);
    }
}
