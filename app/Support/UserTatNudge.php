<?php

namespace App\Support;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

final class UserTatNudge
{
    public const DELAY_SECONDS = 3600;

    public const WINDOW_DAYS = 30;

    /**
     * @return list<string>
     */
    public static function messages(): array
    {
        return [
            'Finish each task in the time allotted. A strong TAT is how promotions and incentives get earned.',
            'Don\'t let work sit past the allotted time. Completing tasks on the clock keeps your TAT strong and you in line for promotions and incentives.',
            'Promotions and incentives follow people who deliver on time. Bring your TAT down by closing work within the allotted time.',
            'Every task finished on time improves your TAT score. Stay consistent to stay eligible for promotions and incentive rewards.',
            'Aim to accomplish work inside the allotted time. A tight TAT score is how promotions and incentives stay within reach.',
        ];
    }

    public static function markLogin(): void
    {
        Session::put('tat_nudge_anchor_at', now()->timestamp);
        Session::put('tat_nudge_anchor_date', TaskBusinessTime::today()->toDateString());
    }

    /**
     * Start (or keep) today's 1-hour clock. Resets on a new business day.
     */
    public static function ensureAnchor(): int
    {
        $today = TaskBusinessTime::today()->toDateString();
        $date = (string) Session::get('tat_nudge_anchor_date', '');
        $at = (int) Session::get('tat_nudge_anchor_at', 0);
        if ($date !== $today || $at <= 0) {
            $at = now()->timestamp;
            Session::put('tat_nudge_anchor_at', $at);
            Session::put('tat_nudge_anchor_date', $today);
        }

        return $at;
    }

    public static function waitMs(): int
    {
        $readyAt = self::ensureAnchor() + self::DELAY_SECONDS;

        return (int) max(0, ($readyAt - now()->timestamp) * 1000);
    }

    /**
     * Same L30 TAT as /tasks/summary: average days from start → completion
     * for Done tasks closed in the last 30 days.
     *
     * @return array{tat_l30_days: float|null, tat_l30_count: int, tat_display: string, tat_band: string}
     */
    public static function forUser(?User $user): array
    {
        $empty = [
            'tat_l30_days' => null,
            'tat_l30_count' => 0,
            'tat_display' => '—',
            'tat_band' => 'none',
        ];
        if (! $user) {
            return $empty;
        }

        $email = strtolower(trim((string) $user->email));
        if ($email === '' || ! Schema::hasTable('tasks')) {
            return $empty;
        }

        $cacheKey = 'tat_nudge_'.$user->id.'_'.TaskBusinessTime::today()->toDateString();

        return Cache::remember($cacheKey, now()->addMinutes(2), function () use ($email, $empty) {
            $cutoff = Carbon::now()->subDays(self::WINDOW_DAYS);
            $tasks = Task::query()
                ->where('status', 'Done')
                ->whereNotNull('start_date')
                ->whereNotNull('completion_date')
                ->where('completion_date', '>=', $cutoff)
                ->where(function ($q) use ($email) {
                    $q->whereRaw('LOWER(TRIM(assign_to)) = ?', [$email])
                        ->orWhereRaw('LOWER(assign_to) LIKE ?', [$email.',%'])
                        ->orWhereRaw('LOWER(assign_to) LIKE ?', ['%,'.$email])
                        ->orWhereRaw('LOWER(assign_to) LIKE ?', ['%,'.$email.',%']);
                })
                ->get(['start_date', 'completion_date']);

            $sum = 0.0;
            $count = 0;
            foreach ($tasks as $task) {
                try {
                    $start = Carbon::parse($task->start_date);
                    $end = Carbon::parse($task->completion_date);
                    if ($end->lessThan($start)) {
                        continue;
                    }
                    $days = ($end->getTimestamp() - $start->getTimestamp()) / 86400.0;
                    if ($days < 0) {
                        $days = 0.0;
                    }
                    $sum += $days;
                    $count++;
                } catch (\Throwable $e) {
                    // Malformed timestamp — skip this row.
                }
            }

            if ($count < 1) {
                return $empty;
            }

            $avg = $sum / $count;

            return [
                'tat_l30_days' => $avg,
                'tat_l30_count' => $count,
                'tat_display' => self::display($avg),
                'tat_band' => self::band($avg),
            ];
        });
    }

    /**
     * Task Summary TAT cell: > 2 days as a rounded integer, otherwise one decimal.
     */
    public static function display(float $days): string
    {
        return $days > 2
            ? ((string) (int) round($days)).'d'
            : number_format($days, 1).'d';
    }

    /**
     * Task Summary TAT colours: ≥3 red, 1–3 green, <1 pink.
     */
    public static function band(float $days): string
    {
        if ($days >= 3) {
            return 'high';
        }
        if ($days < 1) {
            return 'low';
        }

        return 'mid';
    }
}
