<?php

namespace App\Support;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class UserOverdueNudge
{
    public const NUDGE_MIN_OVERDUE_DAYS = 3;

    /**
     * @return list<string>
     */
    public static function messages(): array
    {
        return [
            'Clear these overdues today. People who stay on time are first in line for promotions and incentives.',
            'Every overdue task slows your growth. Close them now to stay eligible for promotions and incentive rewards.',
            'Don\'t let overdues block your next promotion. A clean task list is how incentives get earned.',
            'Promotions and incentives follow consistency. Bring this overdue count down and keep it down.',
            'Avoid overdues to stay in the running for promotions and the incentive bag. Start with the oldest ones today.',
        ];
    }

    public static function countForUser(?User $user, int $minOverdueDays = 0): int
    {
        if (! $user) {
            return 0;
        }

        $email = strtolower(trim((string) $user->email));
        if ($email === '') {
            return 0;
        }

        $minOverdueDays = max(0, $minOverdueDays);
        $cacheKey = 'overdue_nudge_count_v2_'.$user->id.'_'.$minOverdueDays.'_'.TaskBusinessTime::today()->toDateString();

        return (int) Cache::remember($cacheKey, now()->addMinutes(2), function () use ($email, $minOverdueDays) {
            TaskBusinessTime::applyDatabaseSession();
            $days = (int) TaskBusinessTime::weeklyMonthlyOverdueDays();
            if ($minOverdueDays > 0) {
                $weekly = "DATE_ADD(DATE(COALESCE(created_at, start_date)), INTERVAL {$days} DAY) < DATE_SUB(CURDATE(), INTERVAL {$minOverdueDays} DAY)";
                $regular = "DATE(DATE_ADD(DATE(start_date), INTERVAL 1 DAY)) < DATE_SUB(CURDATE(), INTERVAL {$minOverdueDays} DAY)";
            } else {
                $weekly = "DATE_ADD(DATE(COALESCE(created_at, start_date)), INTERVAL {$days} DAY) <= CURDATE()";
                $regular = "DATE(DATE_ADD(DATE(start_date), INTERVAL 1 DAY)) < CURDATE()";
            }

            return (int) Task::query()
                ->where('status', '!=', 'Archived')
                ->whereNotNull('start_date')
                ->where(function ($q) use ($email) {
                    $q->whereRaw('LOWER(TRIM(assign_to)) = ?', [$email])
                        ->orWhereRaw('LOWER(assign_to) LIKE ?', [$email.',%'])
                        ->orWhereRaw('LOWER(assign_to) LIKE ?', ['%,'.$email])
                        ->orWhereRaw('LOWER(assign_to) LIKE ?', ['%,'.$email.',%']);
                })
                ->whereRaw(
                    "(CASE
                        WHEN COALESCE(is_automate_task, 0) = 1
                             AND LOWER(COALESCE(schedule_type, '')) IN ('weekly', 'monthly')
                        THEN {$weekly}
                        ELSE {$regular}
                    END)"
                )
                ->count();
        });
    }

    public static function countForNudge(?User $user): int
    {
        return self::countForUser($user, self::NUDGE_MIN_OVERDUE_DAYS);
    }

    public static function shouldShow(?User $user): bool
    {
        return self::countForNudge($user) > 0;
    }
}
