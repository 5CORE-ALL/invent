<?php

namespace Tests\Unit;

use App\Support\AutomatedTaskSchedule;
use PHPUnit\Framework\TestCase;

class AutomatedTaskScheduleTest extends TestCase
{
    public function test_empty_weekly_days_default_to_monday(): void
    {
        $this->assertSame('Mon', AutomatedTaskSchedule::resolveWeeklyDays(''));
        $this->assertSame('Mon', AutomatedTaskSchedule::resolveWeeklyDays(null));
        $this->assertSame('Mon', AutomatedTaskSchedule::resolveWeeklyDays('   '));
        $this->assertSame('Mon', AutomatedTaskSchedule::applyDefaultDays('weekly', ''));
        $this->assertSame(['mon'], AutomatedTaskSchedule::weeklyDayTokens(''));
    }

    public function test_explicit_weekly_days_are_kept(): void
    {
        $this->assertSame('Tue,Fri', AutomatedTaskSchedule::resolveWeeklyDays('Tue,Fri'));
        $this->assertSame(['tue', 'fri'], AutomatedTaskSchedule::weeklyDayTokens('Tuesday,Friday'));
        $this->assertSame('Thu', AutomatedTaskSchedule::applyDefaultDays('WEEKLY', 'Thu'));
    }

    public function test_non_weekly_empty_days_stay_empty(): void
    {
        $this->assertSame('', AutomatedTaskSchedule::applyDefaultDays('daily', ''));
        $this->assertSame('', AutomatedTaskSchedule::applyDefaultDays('monthly', null));
        $this->assertSame('1,15', AutomatedTaskSchedule::applyDefaultDays('monthly', '1,15'));
    }
}
