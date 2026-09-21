<?php

namespace App\Support;

use App\Models\DailyCloseout;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

final class DailyCloseoutNudge
{
    public const TZ = 'Asia/Kolkata';

    public const SLOT_TASK = 'tasks';

    public const SLOT_DAR_FIRST = 'dar_first';

    public const SLOT_DAR_SECOND = 'dar_second';

    /** Eligible IST window: after midnight, before this time. */
    public const WINDOW_END = '09:00';

    public static function now(): Carbon
    {
        return Carbon::now(self::TZ);
    }

    /**
     * Midnight IST of the closeout day we are targeting.
     * After 09:00 IST, that is tomorrow (do not fire in the evening).
     */
    public static function windowStart(?Carbon $now = null): Carbon
    {
        $now ??= self::now();
        $end = $now->copy()->startOfDay()->setTimeFromTimeString(self::WINDOW_END.':00');
        if ($now->lessThan($end)) {
            return $now->copy()->startOfDay();
        }

        return $now->copy()->addDay()->startOfDay();
    }

    public static function checkDate(?Carbon $now = null): string
    {
        return self::windowStart($now)->toDateString();
    }

    /**
     * Stable random fire time after 12:00 AM IST for this user + day + slot.
     * Task, then DAR 1, then DAR 2 — each once, never before midnight.
     */
    public static function fireAt(string $slot, int $userId, ?Carbon $now = null): Carbon
    {
        $start = self::windowStart($now);
        $minutes = self::slotMinutes($userId, $start->toDateString())[$slot] ?? 0;

        return $start->copy()->addMinutes($minutes);
    }

    /**
     * @return array{tasks: int, dar_first: int, dar_second: int}
     */
    public static function slotMinutes(int $userId, string $date): array
    {
        $max = (8 * 60) + 30;
        $times = [
            self::stableMinute($userId, $date, 'a', 0, $max),
            self::stableMinute($userId, $date, 'b', 0, $max),
            self::stableMinute($userId, $date, 'c', 0, $max),
        ];
        sort($times, SORT_NUMERIC);
        if ($times[1] === $times[0]) {
            $times[1] = min($max, $times[1] + 15);
        }
        if ($times[2] <= $times[1]) {
            $times[2] = min($max, $times[1] + 15);
        }

        return [
            self::SLOT_TASK => $times[0],
            self::SLOT_DAR_FIRST => $times[1],
            self::SLOT_DAR_SECOND => $times[2],
        ];
    }

    public static function waitMs(string $slot, int $userId, ?Carbon $now = null): int
    {
        $now ??= self::now();
        $target = self::fireAt($slot, $userId, $now);

        return (int) max(0, ($target->getTimestamp() - $now->getTimestamp()) * 1000);
    }

    public static function isDue(string $slot, int $userId, ?Carbon $now = null): bool
    {
        $now ??= self::now();
        $todayEnd = $now->copy()->startOfDay()->setTimeFromTimeString(self::WINDOW_END.':00');
        if ($now->greaterThanOrEqualTo($todayEnd)) {
            return false;
        }

        return $now->greaterThanOrEqualTo(self::fireAt($slot, $userId, $now));
    }

    public static function forUser(?User $user, ?Carbon $now = null): array
    {
        $now ??= self::now();
        $date = self::checkDate($now);
        $userId = (int) ($user?->id ?? 0);
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
            'tasks' => self::slotPayload($userId, self::SLOT_TASK, ! $taskDone, $now),
            'dar_first' => self::slotPayload($userId, self::SLOT_DAR_FIRST, ! $darFirstDone, $now),
            'dar_second' => self::slotPayload($userId, self::SLOT_DAR_SECOND, ! $darSecondDone, $now),
        ];
    }

    /**
     * @return array{needed: bool, due: bool, wait_ms: int|null, fire_at: string|null}
     */
    private static function slotPayload(int $userId, string $slot, bool $stillNeeded, Carbon $now): array
    {
        if ($userId < 1 || ! $stillNeeded) {
            return [
                'needed' => false,
                'due' => false,
                'wait_ms' => null,
                'fire_at' => null,
            ];
        }

        $wait = self::waitMs($slot, $userId, $now);

        return [
            'needed' => true,
            'due' => self::isDue($slot, $userId, $now),
            'wait_ms' => $wait,
            'fire_at' => self::fireAt($slot, $userId, $now)->format('H:i'),
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

    private static function stableMinute(int $userId, string $date, string $salt, int $min, int $max): int
    {
        $raw = hexdec(substr(hash('sha256', $userId.'|'.$date.'|'.$salt), 0, 8));

        return $min + ($raw % ($max - $min + 1));
    }
}
