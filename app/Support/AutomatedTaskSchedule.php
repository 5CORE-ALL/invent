<?php

namespace App\Support;

/**
 * Schedule defaults for automate_tasks templates.
 * Weekly tasks with no weekday never match the firer — default those to Monday.
 */
class AutomatedTaskSchedule
{
    public const DEFAULT_WEEKLY_DAYS = 'Mon';

    public static function resolveWeeklyDays(?string $scheduleDays): string
    {
        $days = trim((string) $scheduleDays);

        return $days !== '' ? $days : self::DEFAULT_WEEKLY_DAYS;
    }

    public static function applyDefaultDays(string $scheduleType, ?string $scheduleDays): string
    {
        $days = trim((string) $scheduleDays);
        if (strtolower(trim($scheduleType)) !== 'weekly') {
            return $days;
        }

        return self::resolveWeeklyDays($days);
    }

    /**
     * Whether a DAILY template should create an instance on the given weekday.
     *
     * Daily templates normally have an empty schedule_days and run every day.
     * If weekdays are listed (e.g. "Mon,Tue,Wed,Thu,Fri,Sat"), the template is
     * skipped on the other days (used for week offs such as Sunday).
     *
     * @param  string  $dayToken  three-letter lowercase weekday (mon, tue, …)
     */
    public static function dailyRunsOn(?string $scheduleDays, string $dayToken): bool
    {
        if (trim((string) $scheduleDays) === '') {
            return true;
        }

        $tokens = array_values(array_filter(array_map(static function ($day) {
            $day = strtolower(trim((string) $day));

            return strlen($day) >= 3 ? substr($day, 0, 3) : $day;
        }, explode(',', (string) $scheduleDays))));

        if ($tokens === []) {
            return true;
        }

        return in_array(strtolower($dayToken), $tokens, true);
    }

    /**
     * @return list<string> three-letter weekday tokens (mon, tue, …)
     */
    public static function weeklyDayTokens(?string $scheduleDays): array
    {
        return array_values(array_filter(array_map(static function ($day) {
            $day = strtolower(trim((string) $day));
            if ($day === '') {
                return '';
            }

            return strlen($day) >= 3 ? substr($day, 0, 3) : $day;
        }, explode(',', self::resolveWeeklyDays($scheduleDays)))));
    }
}
