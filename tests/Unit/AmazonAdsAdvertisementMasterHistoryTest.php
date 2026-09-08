<?php

namespace Tests\Unit;

use App\Support\AmazonAdsAdvertisementMasterHistory;
use PHPUnit\Framework\TestCase;

class AmazonAdsAdvertisementMasterHistoryTest extends TestCase
{
    public function test_roll_daily_to_l30_sums_the_inclusive_30_day_window(): void
    {
        $daily = [
            '2026-08-06' => ['spend' => 1000.0, 'clicks' => 10, 'sold' => 1, 'sales' => 50, 'active' => 5],
            '2026-09-03' => ['spend' => 300.0, 'clicks' => 20, 'sold' => 2, 'sales' => 80, 'active' => 8],
            '2026-09-04' => ['spend' => 200.0, 'clicks' => 15, 'sold' => 1, 'sales' => 40, 'active' => 7],
        ];

        $rolled = AmazonAdsAdvertisementMasterHistory::rollDailyToL30($daily, '2026-09-03', '2026-09-04');

        $this->assertSame(1300.0, $rolled['2026-09-03']['spend']);
        $this->assertSame(8.0, $rolled['2026-09-03']['active']);
        $this->assertSame(1500.0, $rolled['2026-09-04']['spend']);
        $this->assertSame(7.0, $rolled['2026-09-04']['active']);
    }

    public function test_roll_drops_a_day_that_ages_out_of_the_window(): void
    {
        $daily = [
            '2026-08-05' => ['spend' => 400.0, 'clicks' => 0, 'sold' => 0, 'sales' => 0, 'active' => 1],
            '2026-09-04' => ['spend' => 50.0, 'clicks' => 0, 'sold' => 0, 'sales' => 0, 'active' => 2],
        ];

        $rolled = AmazonAdsAdvertisementMasterHistory::rollDailyToL30($daily, '2026-09-03', '2026-09-04');

        $this->assertSame(400.0, $rolled['2026-09-03']['spend']);
        $this->assertSame(50.0, $rolled['2026-09-04']['spend']);
    }

    public function test_overlay_replaces_stale_amazon_spend_on_totals(): void
    {
        $byDate = [
            '2026-09-04' => [
                'spend' => 20000.0,
                'clicks' => 100,
                'sold' => 10,
                'sales' => 500,
                'active' => 400,
                'missing_ads' => 0,
            ],
        ];
        $byChannel = [
            'Amazon' => [
                '2026-09-04' => [
                    'spend' => 6688.0,
                    'clicks' => 40,
                    'sold' => 4,
                    'sales' => 200,
                    'active' => 202,
                    'missing_ads' => 0,
                ],
            ],
            'eBay' => [
                '2026-09-04' => [
                    'spend' => 13312.0,
                    'clicks' => 60,
                    'sold' => 6,
                    'sales' => 300,
                    'active' => 198,
                    'missing_ads' => 0,
                ],
            ],
        ];
        $computed = [
            'Amazon' => [
                '2026-09-04' => [
                    'spend' => 9100.5,
                    'clicks' => 55,
                    'sold' => 6,
                    'sales' => 260,
                    'active' => 156,
                ],
            ],
            'Amazon · KW' => [
                '2026-09-04' => [
                    'spend' => 4000.0,
                    'clicks' => 20,
                    'sold' => 3,
                    'sales' => 120,
                    'active' => 80,
                ],
            ],
        ];

        [$byDate, $byChannel] = AmazonAdsAdvertisementMasterHistory::overlayOnHistory(
            $byDate,
            $byChannel,
            $computed
        );

        $this->assertSame(9100.5, $byChannel['Amazon']['2026-09-04']['spend']);
        $this->assertSame(22412.5, $byDate['2026-09-04']['spend']);
        $this->assertSame(4000.0, $byChannel['Amazon · KW']['2026-09-04']['spend']);
        $this->assertSame(13312.0, $byChannel['eBay']['2026-09-04']['spend']);
    }

    public function test_overlay_fills_amazon_days_without_changing_missing_all_channel_totals(): void
    {
        $byDate = [];
        $byChannel = [];
        $computed = [
            'Amazon' => [
                '2026-09-06' => [
                    'spend' => 8800.0,
                    'clicks' => 10,
                    'sold' => 1,
                    'sales' => 40,
                    'active' => 170,
                ],
            ],
        ];

        [$byDate, $byChannel] = AmazonAdsAdvertisementMasterHistory::overlayOnHistory(
            $byDate,
            $byChannel,
            $computed
        );

        $this->assertSame(8800.0, $byChannel['Amazon']['2026-09-06']['spend']);
        $this->assertArrayNotHasKey('2026-09-06', $byDate);
    }
}
