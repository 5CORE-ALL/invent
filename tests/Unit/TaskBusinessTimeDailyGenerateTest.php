<?php

namespace Tests\Unit;

use App\Support\TaskBusinessTime;
use Carbon\Carbon;
use Tests\TestCase;

class TaskBusinessTimeDailyGenerateTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_daily_generation_is_noon_india_time(): void
    {
        $this->assertSame('Asia/Kolkata', TaskBusinessTime::tz());
        $this->assertSame('12:00:00', TaskBusinessTime::dailyGenerateTime());
    }

    public function test_generation_window_opens_at_noon_ist(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-17 11:59:59', 'Asia/Kolkata'));
        $this->assertFalse(TaskBusinessTime::isDailyGenerateWindow());

        Carbon::setTestNow(Carbon::parse('2026-09-17 12:00:00', 'Asia/Kolkata'));
        $this->assertTrue(TaskBusinessTime::isDailyGenerateWindow());
        $this->assertSame('2026-09-17 12:00:00', TaskBusinessTime::dailyGenerateAt()->format('Y-m-d H:i:s'));
    }

    public function test_generation_date_follows_india_calendar_not_pacific(): void
    {
        // 23:30 PDT on Sep 16 is 12:00 IST on Sep 17.
        Carbon::setTestNow(Carbon::parse('2026-09-16 23:30:00', 'America/Los_Angeles'));

        $now = TaskBusinessTime::now();
        $this->assertSame('Asia/Kolkata', $now->timezoneName);
        $this->assertSame('2026-09-17', $now->toDateString());
        $this->assertTrue(TaskBusinessTime::isDailyGenerateWindow($now));
        $this->assertSame('2026-09-17 12:00:00', TaskBusinessTime::dailyGenerateAt($now)->format('Y-m-d H:i:s'));
    }
}
