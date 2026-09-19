<?php

namespace App\Support;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class UserOverdueNudge
{
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

    public static function countForUser(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        $email = strtolower(trim((string) $user->email));
        if ($email === '') {
            return 0;
        }

        $cacheKey = 'overdue_nudge_count_'.$user->id.'_'.TaskBusinessTime::today()->toDateString();

        return (int) Cache::remember($cacheKey, now()->addMinutes(2), function () use ($email) {
            TaskBusinessTime::applyDatabaseSession();
            $days = (int) TaskBusinessTime::weeklyMonthlyOverdueDays();

            return (int) Task::query()
                ->where('status', '!=', 'Archived')
                ->whereNotNull('start_date')
                ->where(function ($q) use ($email) {
                    $q->whereRaw('LOWER(TRIM(assign_to)) = ?', [$email])
                        ->orWhereRaw("LOWER(assign_to) LIKE ?", [$email.',%'])
                        ->orWhereRaw("LOWER(assign_to) LIKE ?", ['%,'.$email])
                        ->orWhereRaw("LOWER(assign_to) LIKE ?", ['%,'.$email.',%']);
                })
                ->whereRaw(
                    "(CASE
                        WHEN COALESCE(is_automate_task, 0) = 1
                             AND LOWER(COALESCE(schedule_type, '')) IN ('weekly', 'monthly')
                        THEN DATE_ADD(DATE(COALESCE(created_at, start_date)), INTERVAL {$days} DAY) <= CURDATE()
                        ELSE DATE(DATE_ADD(DATE(start_date), INTERVAL 1 DAY)) < CURDATE()
                    END)"
                )
                ->count();
        });
    }
}
