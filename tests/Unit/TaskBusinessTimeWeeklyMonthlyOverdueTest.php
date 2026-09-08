<?php

namespace Tests\Unit;

use App\Support\TaskBusinessTime;
use PHPUnit\Framework\TestCase;

class TaskBusinessTimeWeeklyMonthlyOverdueTest extends TestCase
{
    public function test_weekly_and_monthly_are_overdue_six_days_after_created_date(): void
    {
        $this->assertSame(6, TaskBusinessTime::weeklyMonthlyOverdueDays());
        $this->assertSame(6, TaskBusinessTime::overdueGraceDays('weekly', true));
        $this->assertSame(6, TaskBusinessTime::overdueGraceDays('monthly', true));
        $this->assertSame(1, TaskBusinessTime::overdueGraceDays('daily', true));
        $this->assertSame(1, TaskBusinessTime::overdueGraceDays('weekly', false));

        $this->assertSame('2026-09-07', TaskBusinessTime::weeklyMonthlyOverdueOnDate('2026-09-01 12:01:00'));
        $this->assertSame('2026-09-07', TaskBusinessTime::weeklyMonthlyOverdueOnDate('2026-09-01 00:00:09', '2026-09-01 12:01:00'));
    }

    public function test_weekly_and_monthly_due_window_is_six_days(): void
    {
        $this->assertSame(6, TaskBusinessTime::completionWindowDays('weekly'));
        $this->assertSame(6, TaskBusinessTime::completionWindowDays('monthly'));
        $this->assertSame(5, TaskBusinessTime::completionWindowDays('daily'));
    }

    public function test_missed_cutoff_is_six_days_after_created_at_for_weekly_and_monthly(): void
    {
        $tz = 'America/Los_Angeles';
        $weekly = TaskBusinessTime::missedCutoffFor(
            '2026-09-01 12:01:00',
            '2026-09-01 12:01:00',
            'weekly',
            $tz,
            '23:59:00'
        );
        $monthly = TaskBusinessTime::missedCutoffFor(
            '2026-09-01 12:01:00',
            '2026-09-01 12:01:00',
            'monthly',
            $tz,
            '23:59:00'
        );
        $daily = TaskBusinessTime::missedCutoffFor(
            '2026-09-01 00:00:09',
            '2026-09-01 14:00:00',
            'daily',
            $tz,
            '23:59:00'
        );

        $this->assertSame('2026-09-07 23:59:00', $weekly->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-07 23:59:00', $monthly->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-01 23:59:00', $daily->format('Y-m-d H:i:s'));
    }

    public function test_weekly_cutoff_uses_created_at_not_start_date(): void
    {
        $cutoff = TaskBusinessTime::missedCutoffFor(
            '2026-09-01 00:00:09',
            '2026-09-03 12:01:00',
            'weekly',
            'America/Los_Angeles',
            '23:59:00'
        );

        $this->assertSame('2026-09-07 23:59:00', $cutoff->format('Y-m-d H:i:s'));
    }
}
