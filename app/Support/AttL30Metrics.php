<?php

namespace App\Support;

class AttL30Metrics
{
    public const TARGET_HOURS = 200;

    public const WINDOW_DAYS = 30;

    /** One work day cannot count more than this (open/idle sessions inflate wall-clock). */
    public const MAX_DAY_HOURS = 12;

    /** Last-30-days total cannot exceed this — 300+ is not real work time. */
    public const MAX_WINDOW_HOURS = 300;

    /**
     * Only these people still track time in Team Logger.
     * Everyone else uses in-app attendance.
     *
     * @var list<string>
     */
    public const TEAM_LOGGER_NAME_NEEDLES = ['shobha', 'mariya'];

    public static function usesTeamLogger(?string $name, ?string $email = null): bool
    {
        $haystack = strtolower(trim((string) $name).' '.trim((string) $email));
        foreach (self::TEAM_LOGGER_NAME_NEEDLES as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * One day's hours: prefer active/productive time, never wall-clock above the day cap.
     */
    public static function dayHoursFromSeconds(int $activeSeconds, int $workSeconds = 0): float
    {
        $seconds = $activeSeconds > 0 ? $activeSeconds : max(0, $workSeconds);
        if ($seconds <= 0) {
            return 0.0;
        }

        return min($seconds / 3600, self::MAX_DAY_HOURS);
    }

    public static function clampWindowHours(float $hours): float
    {
        if ($hours < 0) {
            return 0.0;
        }

        return min($hours, self::MAX_WINDOW_HOURS);
    }

    public static function percent(float $hours): int
    {
        $hours = self::clampWindowHours($hours);

        return (int) round(($hours / self::TARGET_HOURS) * 100);
    }

    /**
     * Same bands as DAR: >90% pink, 80–90% green, otherwise red.
     */
    public static function band(int $percent): string
    {
        return DarL30Metrics::band($percent);
    }

    /**
     * @return array{att_l30_hours: float, att_l30_target: int, att_l30_pct: int, att_l30_band: string}
     */
    public static function forHours(float $hours): array
    {
        $hours = round(self::clampWindowHours($hours), 1);
        $pct = self::percent($hours);

        return [
            'att_l30_hours' => $hours,
            'att_l30_target' => self::TARGET_HOURS,
            'att_l30_pct' => $pct,
            'att_l30_band' => self::band($pct),
        ];
    }
}
