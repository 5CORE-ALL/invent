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
        $this->assertSame(0, TaskBusinessTime::overdueGraceDays('daily', true));
        $this->assertSame(1, TaskBusinessTime::overdueGraceDays('daily', false));
        $this->assertSame(1, TaskBusinessTime::overdueGraceDays('weekly', false));

        $this->assertSame('2026-09-07', TaskBusinessTime::weeklyMonthlyOverdueOnDate('2026-09-01 12:01:00'));
        $this->assertSame('2026-09-07', TaskBusinessTime::weeklyMonthlyOverdueOnDate('2026-09-01 00:00:09', '2026-09-01 12:01:00'));
    }

    public function test_daily_automated_is_overdue_the_day_after_tid_others_a_day_later(): void
    {
        $tz = TaskBusinessTime::tz();
        $start = '2026-09-01 10:00:00';

        try {
            // Same day as TID: not overdue.
            \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-01 20:00:00', $tz));
            $this->assertFalse(TaskBusinessTime::isTaskOverdue('Todo', $start, $start, 'daily', true));

            // Next day: daily automated is overdue, a normal task is still inside its grace day.
            \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-02 09:00:00', $tz));
            $this->assertTrue(TaskBusinessTime::isTaskOverdue('Todo', $start, $start, 'daily', true));
            $this->assertFalse(TaskBusinessTime::isTaskOverdue('Todo', $start, $start, null, false));

            // Two days later the normal task is overdue as well.
            \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-03 09:00:00', $tz));
            $this->assertTrue(TaskBusinessTime::isTaskOverdue('Todo', $start, $start, null, false));

            // Done is never overdue.
            $this->assertFalse(TaskBusinessTime::isTaskOverdue('Done', $start, $start, 'daily', true));
        } finally {
            \Carbon\Carbon::setTestNow();
        }
    }

    public function test_done_task_not_deleted_by_assignor_becomes_overdue_with_same_timing(): void
    {
        $tz = TaskBusinessTime::tz();
        $start = '2026-09-01 10:00:00';

        try {
            // TID day: nothing is overdue yet.
            \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-01 20:00:00', $tz));
            $this->assertFalse(TaskBusinessTime::isDoneUncheckedOverdue('Done', $start, $start, 'daily', true));

            // Next day: Done daily automated is overdue, a normal Done task is still in its grace day.
            \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-02 09:00:00', $tz));
            $this->assertTrue(TaskBusinessTime::isDoneUncheckedOverdue('Done', $start, $start, 'daily', true));
            $this->assertFalse(TaskBusinessTime::isDoneUncheckedOverdue('Done', $start, $start, null, false));

            // Two days later the normal Done task is overdue too.
            \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-03 09:00:00', $tz));
            $this->assertTrue(TaskBusinessTime::isDoneUncheckedOverdue('Done', $start, $start, null, false));

            // Only Done counts; open and archived tasks are handled elsewhere / never.
            $this->assertFalse(TaskBusinessTime::isDoneUncheckedOverdue('Todo', $start, $start, null, false));
            $this->assertFalse(TaskBusinessTime::isDoneUncheckedOverdue('Archived', $start, $start, null, false));

            // The assignee side still never sees a Done task as overdue.
            $this->assertFalse(TaskBusinessTime::isTaskOverdue('Done', $start, $start, null, false));

            // Sourcing / Purchase get 15 days.
            \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-15 09:00:00', $tz));
            $this->assertFalse(TaskBusinessTime::isDoneUncheckedOverdue('Done', $start, $start, null, false, 'PURCHASE'));
            \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-16 09:00:00', $tz));
            $this->assertTrue(TaskBusinessTime::isDoneUncheckedOverdue('Done', $start, $start, null, false, 'PURCHASE'));
        } finally {
            \Carbon\Carbon::setTestNow();
        }
    }

    public function test_sourcing_and_purchase_groups_get_fifteen_days(): void
    {
        $tz = TaskBusinessTime::tz();
        $start = '2026-09-01 10:00:00';

        $this->assertSame(15, TaskBusinessTime::longOverdueDays());
        $this->assertTrue(TaskBusinessTime::isLongOverdueGroup('PURCHASE'));
        $this->assertTrue(TaskBusinessTime::isLongOverdueGroup(' Sourcing '));
        $this->assertFalse(TaskBusinessTime::isLongOverdueGroup('Purchasing Power'));
        $this->assertFalse(TaskBusinessTime::isLongOverdueGroup(''));

        try {
            // Day 14 after TID: still allowed (a normal task would be overdue long ago).
            \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-15 09:00:00', $tz));
            $this->assertFalse(TaskBusinessTime::isTaskOverdue('Todo', $start, $start, null, false, 'PURCHASE'));
            $this->assertFalse(TaskBusinessTime::isTaskOverdue('Todo', $start, $start, 'daily', true, 'Sourcing'));
            $this->assertTrue(TaskBusinessTime::isTaskOverdue('Todo', $start, $start, null, false, 'Amazon'));

            // Day 15: overdue.
            \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-16 09:00:00', $tz));
            $this->assertTrue(TaskBusinessTime::isTaskOverdue('Todo', $start, $start, null, false, 'PURCHASE'));
            $this->assertTrue(TaskBusinessTime::isTaskOverdue('Todo', $start, $start, 'daily', true, 'Sourcing'));

            // Done is never overdue.
            $this->assertFalse(TaskBusinessTime::isTaskOverdue('Done', $start, $start, null, false, 'PURCHASE'));

            // Weekly automated task in a long group counts 15 days from created_at, not 6.
            \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-10 09:00:00', $tz));
            $this->assertFalse(TaskBusinessTime::isTaskOverdue('Todo', '2026-09-08 10:00:00', $start, 'weekly', true, 'Sourcing'));
            $this->assertTrue(TaskBusinessTime::isTaskOverdue('Todo', '2026-09-08 10:00:00', $start, 'weekly', true, 'Amazon'));
        } finally {
            \Carbon\Carbon::setTestNow();
        }
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
