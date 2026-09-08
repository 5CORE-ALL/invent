<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single source of truth for Task Manager calendar (office timezone).
 */
class TaskBusinessTime
{
    public static function tz(): string
    {
        $configured = static::configValue('tasks.business_timezone');

        return (string) ($configured ?: 'America/Los_Angeles');
    }

    public static function label(): string
    {
        return (string) config('tasks.timezone_label', 'California (PT)');
    }

    public static function shortLabel(): string
    {
        return (string) config('tasks.timezone_short', 'PT');
    }

    public static function now(): Carbon
    {
        return Carbon::now(static::tz());
    }

    public static function today(): Carbon
    {
        return Carbon::today(static::tz());
    }

    public static function todayStart(): Carbon
    {
        return static::today()->startOfDay();
    }

    public static function todayEnd(): Carbon
    {
        return static::today()->endOfDay();
    }

    public static function parse(mixed $value): Carbon
    {
        // Task datetimes (start_date, due_date, deleted_at, …) are stored as office
        // "wall clock" times — the numbers in DB literally are the business-TZ clock.
        // When the value reaches us as a DateTimeInterface (e.g. via Eloquent's
        // 'datetime' cast applied in the app TZ = Asia/Kolkata), Carbon::parse(
        // $carbon, biz_tz) would CONVERT the instant across timezones and roll the
        // calendar day for wall-clock times near midnight in either zone — that's
        // how a TID typed as "2026-06-25 11:00" was rendering / filtering as
        // 2026-06-24 right after an edit. Strip any incoming TZ first so the
        // wall-clock numbers are reinterpreted AS the business TZ.
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        }

        return Carbon::parse($value, static::tz());
    }

    public static function autoDeleteTime(): string
    {
        return (string) config('tasks.auto_delete_time', '00:05:00');
    }

    public static function dailyGenerateTime(): string
    {
        return (string) config('tasks.daily_generate_time', '00:01:00');
    }

    public const WEEKLY_MONTHLY_OVERDUE_DAYS = 6;

    public static function isWeeklyOrMonthly(?string $scheduleType): bool
    {
        return in_array(strtolower(trim((string) $scheduleType)), ['weekly', 'monthly'], true);
    }

    /**
     * Calendar days after created_at when a weekly/monthly automated task is overdue.
     */
    public static function weeklyMonthlyOverdueDays(): int
    {
        $fromConfig = static::configValue('tasks.weekly_monthly_overdue_days');
        if ($fromConfig !== null && $fromConfig !== '') {
            return (int) $fromConfig;
        }

        return self::WEEKLY_MONTHLY_OVERDUE_DAYS;
    }

    /**
     * Grace days before a task counts as overdue.
     * Weekly/monthly automated tasks: 6 days from created_at. Everything else: 1 day from TID.
     */
    public static function overdueGraceDays(?string $scheduleType, bool $isAutomateTask = false): int
    {
        if ($isAutomateTask && static::isWeeklyOrMonthly($scheduleType)) {
            return static::weeklyMonthlyOverdueDays();
        }

        return 1;
    }

    /** due_date / completion_date window when an automated instance is created. */
    public static function completionWindowDays(?string $scheduleType): int
    {
        return static::isWeeklyOrMonthly($scheduleType)
            ? static::weeklyMonthlyOverdueDays()
            : 5;
    }

    /**
     * Hours after the generated start (or created_at for weekly/monthly) before it is "missed".
     * daily=24, weekly/monthly=144 (6 days). Auto tasks only.
     */
    public static function missedAfterHours(?string $scheduleType): int
    {
        $map = (array) (static::configValue('tasks.missed_after_hours') ?? []);
        $key = strtolower(trim((string) $scheduleType));
        $defaults = ['daily' => 24, 'weekly' => 144, 'monthly' => 144];

        return (int) ($map[$key] ?? $defaults[$key] ?? 24);
    }

    /**
     * The moment an automated task becomes missed.
     * Weekly/monthly are anchored to created_at (falls back to start_date).
     */
    public static function missedAtFor(mixed $startDate, ?string $scheduleType, mixed $createdAt = null): ?Carbon
    {
        $anchor = (static::isWeeklyOrMonthly($scheduleType) && $createdAt !== null && $createdAt !== '')
            ? $createdAt
            : $startDate;
        if ($anchor === null || $anchor === '') {
            return null;
        }

        try {
            return static::parse($anchor)->addHours(static::missedAfterHours($scheduleType));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Y-m-d when a weekly/monthly auto task becomes overdue (created date + 6 days).
     */
    public static function weeklyMonthlyOverdueOnDate(mixed $createdAt, mixed $startDate = null): ?string
    {
        $ymd = static::businessDateFromStart($createdAt ?: $startDate);
        if ($ymd === null) {
            return null;
        }

        try {
            return static::parse($ymd)->addDays(static::weeklyMonthlyOverdueDays())->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * PST (or configured) cutoff when an automated instance is marked missed and deleted.
     *   daily   = created/start calendar day at cutoff_time
     *   weekly  = created_at calendar day + 6 days at cutoff_time
     *   monthly = created_at calendar day + 6 days at cutoff_time
     */
    public static function missedCutoffFor(
        mixed $createdAt,
        mixed $startDate,
        ?string $scheduleType,
        string $tz,
        string $cutoffTime
    ): ?Carbon {
        $type = strtolower(trim((string) $scheduleType));
        if (! in_array($type, ['daily', 'weekly', 'monthly'], true)) {
            return null;
        }

        $anchor = (static::isWeeklyOrMonthly($type) && $createdAt !== null && $createdAt !== '')
            ? $createdAt
            : $startDate;
        $ymd = static::businessDateFromStart($anchor);
        if ($ymd === null) {
            return null;
        }

        $parts = array_map('intval', explode(':', $cutoffTime));
        $cutoff = Carbon::parse($ymd, $tz)->setTime($parts[0] ?? 23, $parts[1] ?? 59, $parts[2] ?? 0);

        if (static::isWeeklyOrMonthly($type)) {
            return $cutoff->addDays(static::weeklyMonthlyOverdueDays());
        }

        return $cutoff;
    }

    private static function configValue(string $key): mixed
    {
        if (! function_exists('config')) {
            return null;
        }

        try {
            return config($key);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Incomplete daily auto-task for $startDay is removed at this moment (next calendar day). */
    public static function autoDeleteAtForStartDay(Carbon $startDay): Carbon
    {
        $parts = array_map('intval', explode(':', static::autoDeleteTime()));

        return $startDay->copy()->startOfDay()->addDay()
            ->setTime($parts[0] ?? 0, $parts[1] ?? 5, $parts[2] ?? 0);
    }

    /**
     * Calendar date (Y-m-d) of start_date in the business timezone — use for TID display/overdue.
     */
    public static function businessDateFromStart(mixed $startDate): ?string
    {
        if ($startDate === null || $startDate === '') {
            return null;
        }

        try {
            return static::parse($startDate)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function applyDatabaseSession(): void
    {
        $offset = static::now()->format('P');

        try {
            DB::statement('SET time_zone = ?', [$offset]);
        } catch (\Throwable $e) {
            Log::warning('TaskBusinessTime: could not set session time_zone to '.$offset.': '.$e->getMessage());
        }
    }

    public static function formatDisplay(?Carbon $dt): string
    {
        if ($dt === null) {
            return '';
        }

        return $dt->copy()->setTimezone(static::tz())->format('d M, h:i A').' '.static::shortLabel();
    }
}
