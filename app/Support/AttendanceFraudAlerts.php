<?php

namespace App\Support;

use App\Models\AttendanceAiFlag;
use Illuminate\Support\Collection;

/**
 * Turns open fraud flags into the alert shown as a red triangle beside an executive's name.
 *
 * Read-only and cheap (one query for any number of users), so every screen that lists executives
 * can call it.
 */
class AttendanceFraudAlerts
{
    private const RANK = ['low' => 1, 'medium' => 2, 'high' => 3];

    /**
     * @param  array<int, int>  $userIds
     * @return array<int, array<string, mixed>> user_id => alert (only users who reach the triangle threshold)
     */
    public static function forUsers(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $flags = self::openFraudFlags($userIds)->groupBy('user_id');

        $alerts = [];
        foreach ($flags as $userId => $userFlags) {
            $alert = self::build($userFlags);
            if (self::meetsThreshold($alert['level'])) {
                $alerts[(int) $userId] = $alert;
            }
        }

        return $alerts;
    }

    /**
     * Full picture for one user, including low-severity findings that do not raise the triangle.
     *
     * @return array<string, mixed>|null
     */
    public static function detailForUser(int $userId): ?array
    {
        $flags = self::openFraudFlags([$userId]);

        return $flags->isEmpty() ? null : self::build($flags);
    }

    /**
     * @param  array<int, int>  $userIds
     * @return Collection<int, AttendanceAiFlag>
     */
    public static function openFraudFlags(array $userIds): Collection
    {
        $days = max(1, (int) config('attendance.checkup.lookback_days', 7));
        $cutoff = now()->subDays($days - 1)->toDateString();

        return AttendanceAiFlag::query()
            ->whereIn('user_id', $userIds)
            ->where('status', 'open')
            ->whereIn('flag_type', AttendanceAiFlag::FRAUD_TYPES)
            ->whereDate('flag_date', '>=', $cutoff)
            ->orderByDesc('flag_date')
            ->get();
    }

    public static function meetsThreshold(string $level): bool
    {
        $min = (string) config('attendance.checkup.alert_min_severity', 'medium');

        return (self::RANK[$level] ?? 0) >= (self::RANK[$min] ?? 2);
    }

    /**
     * @param  Collection<int, AttendanceAiFlag>  $flags
     * @return array<string, mixed>
     */
    private static function build(Collection $flags): array
    {
        $level = 'low';
        foreach ($flags as $flag) {
            if ((self::RANK[$flag->severity] ?? 1) > self::RANK[$level]) {
                $level = $flag->severity;
            }
        }

        // One line per kind of finding, with the days it happened on.
        $reasons = $flags->groupBy('flag_type')->map(function (Collection $group, string $type) {
            $severity = $group->sortByDesc(fn ($f) => self::RANK[$f->severity] ?? 1)->first()->severity;

            return [
                'type' => $type,
                'label' => AttendanceAiFlag::TYPES[$type] ?? $type,
                'severity' => $severity,
                'dates' => $group->map(fn ($f) => $f->flag_date?->format('M j'))->filter()->unique()->values()->all(),
                'detail' => (string) $group->first()->description,
                'flag_ids' => $group->pluck('id')->all(),
            ];
        })->sortByDesc(fn ($r) => self::RANK[$r['severity']] ?? 1)->values()->all();

        $lines = array_map(
            fn (array $r) => '• ['.strtoupper($r['severity']).'] '.$r['label'].' ('.implode(', ', $r['dates']).')',
            array_slice($reasons, 0, 6)
        );

        return [
            'level' => $level,
            'count' => $flags->count(),
            'reasons' => $reasons,
            'tooltip' => 'FRAUD CHECK — '.$flags->count().' open alert'.($flags->count() === 1 ? '' : 's')
                ."\n".implode("\n", $lines)."\nClick to review.",
        ];
    }
}
