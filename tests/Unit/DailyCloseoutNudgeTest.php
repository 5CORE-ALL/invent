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

    public function test_fire_times_are_stable_after_midnight_and_ordered(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-22 01:00:00', DailyCloseoutNudge::TZ));

        $task = DailyCloseoutNudge::fireAt(DailyCloseoutNudge::SLOT_TASK, 7);
        $this->assertTrue($task->equalTo(DailyCloseoutNudge::fireAt(DailyCloseoutNudge::SLOT_TASK, 7)));
        $this->assertTrue($task->greaterThanOrEqualTo(Carbon::parse('2026-09-22 00:00:00', DailyCloseoutNudge::TZ)));
        $this->assertTrue($task->lessThan(Carbon::parse('2026-09-22 09:00:00', DailyCloseoutNudge::TZ)));

        $first = DailyCloseoutNudge::fireAt(DailyCloseoutNudge::SLOT_DAR_FIRST, 7);
        $second = DailyCloseoutNudge::fireAt(DailyCloseoutNudge::SLOT_DAR_SECOND, 7);
        $this->assertTrue($task->lessThanOrEqualTo($first));
        $this->assertTrue($first->lessThanOrEqualTo($second));
    }

    public function test_does_not_fire_before_that_users_random_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-22 00:00:00', DailyCloseoutNudge::TZ));
        $fire = DailyCloseoutNudge::fireAt(DailyCloseoutNudge::SLOT_TASK, 7);

        Carbon::setTestNow($fire->copy()->subMinute());
        $this->assertFalse(DailyCloseoutNudge::isDue(DailyCloseoutNudge::SLOT_TASK, 7));
        $this->assertGreaterThan(0, DailyCloseoutNudge::waitMs(DailyCloseoutNudge::SLOT_TASK, 7));

        Carbon::setTestNow($fire);
        $this->assertTrue(DailyCloseoutNudge::isDue(DailyCloseoutNudge::SLOT_TASK, 7));
        $this->assertSame(0, DailyCloseoutNudge::waitMs(DailyCloseoutNudge::SLOT_TASK, 7));
    }

    public function test_does_not_fire_in_the_evening_after_the_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-21 23:16:00', DailyCloseoutNudge::TZ));

        $this->assertFalse(DailyCloseoutNudge::isDue(DailyCloseoutNudge::SLOT_TASK, 7));
        $this->assertFalse(DailyCloseoutNudge::isDue(DailyCloseoutNudge::SLOT_DAR_FIRST, 7));
        $this->assertFalse(DailyCloseoutNudge::isDue(DailyCloseoutNudge::SLOT_DAR_SECOND, 7));
        $this->assertGreaterThan(0, DailyCloseoutNudge::waitMs(DailyCloseoutNudge::SLOT_TASK, 7));
        $this->assertSame('2026-09-22', DailyCloseoutNudge::checkDate());
    }

    public function test_check_date_uses_the_upcoming_ist_morning(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-22 03:15:00', DailyCloseoutNudge::TZ));
        $this->assertSame('2026-09-22', DailyCloseoutNudge::checkDate());

        Carbon::setTestNow(Carbon::parse('2026-09-22 10:00:00', DailyCloseoutNudge::TZ));
        $this->assertSame('2026-09-23', DailyCloseoutNudge::checkDate());
    }
}
