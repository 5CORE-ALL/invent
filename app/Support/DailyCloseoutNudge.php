<?php

namespace App\Support;

use App\Models\DailyCloseout;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

final class DailyCloseoutNudge
{
    public const TZ = 'Asia/Kolkata';

    public const TASK_TIME = '04:30';

    public const DAR_FIRST_TIME = '05:00';

    public const DAR_SECOND_TIME = '05:30';

    public static function now(): Carbon
    {
        return Carbon::now(self::TZ);
    }

    public static function checkDate(?Carbon $now = null): string
    {
        return ($now ?? self::now())->toDateString();
    }

    public static function waitMs(string $hhmm, ?Carbon $now = null): int
    {
        $now ??= self::now();
        $target = $now->copy()->setTimeFromTimeString($hhmm.':00');
        if ($now->greaterThanOrEqualTo($target)) {
            return 0;
        }

        return (int) max(0, ($target->getTimestamp() - $now->getTimestamp()) * 1000);
    }

    public static function forUser(?User $user, ?Carbon $now = null): array
    {
        $now ??= self::now();
        $date = self::checkDate($now);
        $row = null;
        if ($user && Schema::hasTable('daily_closeouts')) {
            $row = DailyCloseout::query()
                ->where('user_id', $user->id)
                ->whereDate('check_date', $date)
                ->first();
        }

        $taskDone = $row && $row->tasks_answered_at;
        $darFirstDone = $row && $row->dar_nudge_5am_at;
        $darSecondDone = $row && $row->dar_nudge_530am_at;

        return [
            'check_date' => $date,
            'timezone' => self::TZ,
            'tasks' => [
                'needed' => ! $taskDone,
                'wait_ms' => $taskDone ? null : self::waitMs(self::TASK_TIME, $now),
                'answered' => (bool) $taskDone,
            ],
            'dar_first' => [
                'needed' => ! $darFirstDone,
                'wait_ms' => $darFirstDone ? null : self::waitMs(self::DAR_FIRST_TIME, $now),
                'shown' => (bool) $darFirstDone,
            ],
            'dar_second' => [
                'needed' => ! $darSecondDone,
                'wait_ms' => $darSecondDone ? null : self::waitMs(self::DAR_SECOND_TIME, $now),
                'shown' => (bool) $darSecondDone,
            ],
        ];
    }

    public static function rowFor(User $user, string $date): DailyCloseout
    {
        return DailyCloseout::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'check_date' => $date,
            ],
            []
        );
    }
}
