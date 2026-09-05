<?php

namespace App\Support;

class AttL30Metrics
{
    public const TARGET_HOURS = 200;

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

    public static function percent(float $hours): int
    {
        if ($hours < 0) {
            $hours = 0;
        }

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
        $pct = self::percent($hours);

        return [
            'att_l30_hours' => round($hours, 1),
            'att_l30_target' => self::TARGET_HOURS,
            'att_l30_pct' => $pct,
            'att_l30_band' => self::band($pct),
        ];
    }
}
