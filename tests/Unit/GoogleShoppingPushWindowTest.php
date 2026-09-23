<?php

namespace Tests\Unit;

use App\Support\GoogleShoppingPushWindow;
use PHPUnit\Framework\TestCase;

class GoogleShoppingPushWindowTest extends TestCase
{
    public function test_push_uses_the_latest_stored_campaign_date(): void
    {
        $ranges = GoogleShoppingPushWindow::ranges('2026-09-22', '2026-09-23 05:18:00');

        $this->assertSame('2026-09-22', $ranges['end']);
        $this->assertSame('2026-09-22', $ranges['L1']['start']);
        $this->assertSame('2026-09-22', $ranges['L1']['end']);
        $this->assertSame('2026-09-16', $ranges['L7']['start']);
        $this->assertSame('2026-08-24', $ranges['L30']['start']);
        $this->assertSame('2026-09-22', $ranges['L30']['end']);
    }

    public function test_missing_campaign_date_falls_back_to_yesterday(): void
    {
        $this->assertSame(
            '2026-09-22',
            GoogleShoppingPushWindow::endDate(null, '2026-09-23 05:18:00')
        );
        $this->assertSame(
            '2026-09-22',
            GoogleShoppingPushWindow::endDate('  ', '2026-09-23 10:00:00')
        );
    }
}
