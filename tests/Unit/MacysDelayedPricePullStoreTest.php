<?php

namespace Tests\Unit;

use App\Services\Support\MacysDelayedPricePullStore;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MacysDelayedPricePullStoreTest extends TestCase
{
    protected function tearDown(): void
    {
        MacysDelayedPricePullStore::clear();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_full_pull_is_due_ten_minutes_after_schedule(): void
    {
        Carbon::setTestNow('2026-09-17 01:00:00');
        $state = MacysDelayedPricePullStore::schedule(['GSS BLU 2PCS']);

        $this->assertSame('2026-09-17 01:10:00', $state['due_at']);
        $this->assertFalse(MacysDelayedPricePullStore::due());

        Carbon::setTestNow('2026-09-17 01:09:59');
        $this->assertFalse(MacysDelayedPricePullStore::due());

        Carbon::setTestNow('2026-09-17 01:10:00');
        $this->assertTrue(MacysDelayedPricePullStore::due());

        $taken = MacysDelayedPricePullStore::takeDue();
        $this->assertSame(['GSS BLU 2PCS'], $taken['skus'] ?? []);
        $this->assertFalse(MacysDelayedPricePullStore::due());
    }
}
