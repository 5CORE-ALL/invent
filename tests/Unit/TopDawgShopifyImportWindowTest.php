<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\TopDawgOrderSyncService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class TopDawgShopifyImportWindowTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_cutoff_is_two_pst_days_before_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 15:00:00', 'America/Los_Angeles'));

        $this->assertSame(
            '2026-09-13',
            TopDawgOrderSyncService::shopifyImportCutoffDate()->toDateString()
        );
    }

    public function test_window_includes_today_and_past_two_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 15:00:00', 'America/Los_Angeles'));

        $this->assertTrue(TopDawgOrderSyncService::orderDateIsWithinShopifyWindow('2026-09-15'));
        $this->assertTrue(TopDawgOrderSyncService::orderDateIsWithinShopifyWindow('2026-09-14'));
        $this->assertTrue(TopDawgOrderSyncService::orderDateIsWithinShopifyWindow('2026-09-13'));
        $this->assertFalse(TopDawgOrderSyncService::orderDateIsWithinShopifyWindow('2026-09-12'));
        $this->assertFalse(TopDawgOrderSyncService::orderDateIsWithinShopifyWindow(null));
    }

    public function test_parses_topdawg_date_aliases(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 15:00:00', 'America/Los_Angeles'));

        $fromGmt = TopDawgOrderSyncService::parseOrderDate(['gmt_create' => '2026-09-14 18:22:00']);
        $fromTxnTime = TopDawgOrderSyncService::parseOrderDate(['create_time' => '2026-09-13']);

        $this->assertSame('2026-09-14', $fromGmt?->toDateString());
        $this->assertSame('2026-09-13', $fromTxnTime?->toDateString());
        $this->assertTrue(TopDawgOrderSyncService::orderDateIsWithinShopifyWindow($fromGmt));
    }
}
