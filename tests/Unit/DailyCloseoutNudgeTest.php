<?php

namespace Tests\Unit;

use App\Support\DailyCloseoutNudge;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class DailyCloseoutNudgeTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_wait_ms_is_zero_once_the_ist_slot_has_started(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-22 04:30:00', DailyCloseoutNudge::TZ));
        $this->assertSame(0, DailyCloseoutNudge::waitMs(DailyCloseoutNudge::TASK_TIME));

        Carbon::setTestNow(Carbon::parse('2026-09-22 05:00:00', DailyCloseoutNudge::TZ));
        $this->assertSame(0, DailyCloseoutNudge::waitMs(DailyCloseoutNudge::DAR_FIRST_TIME));

        Carbon::setTestNow(Carbon::parse('2026-09-22 05:30:00', DailyCloseoutNudge::TZ));
        $this->assertSame(0, DailyCloseoutNudge::waitMs(DailyCloseoutNudge::DAR_SECOND_TIME));
    }

    public function test_wait_ms_counts_down_to_the_ist_slot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-22 04:00:00', DailyCloseoutNudge::TZ));

        $this->assertSame(30 * 60 * 1000, DailyCloseoutNudge::waitMs(DailyCloseoutNudge::TASK_TIME));
        $this->assertSame(60 * 60 * 1000, DailyCloseoutNudge::waitMs(DailyCloseoutNudge::DAR_FIRST_TIME));
        $this->assertSame(90 * 60 * 1000, DailyCloseoutNudge::waitMs(DailyCloseoutNudge::DAR_SECOND_TIME));
    }

    public function test_check_date_uses_ist_calendar_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-22 03:15:00', DailyCloseoutNudge::TZ));
        $this->assertSame('2026-09-22', DailyCloseoutNudge::checkDate());
    }
}
