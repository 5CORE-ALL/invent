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
        return (string) config('tasks.business_timezone', 'America/Los_Angeles');
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
