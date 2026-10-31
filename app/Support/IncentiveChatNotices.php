<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserIncentive;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * 5 Core Bot DMs about incentives:
 *  - cutoff date passed and not achieved -> "incentive closed, ask for extension / new incentive"
 *  - marked achieved                     -> "congratulations, please claim it"
 * Each message is sent once per incentive (tracked on the row).
 */
class IncentiveChatNotices
{
    public const COMMAND_CLOSED = 'incentive_closed';

    public const COMMAND_ACHIEVED = 'incentive_achieved';

    public static function ready(): bool
    {
        return ChatWorkspace::tablesReady()
            && Schema::hasTable('user_incentives')
            && Schema::hasColumn('user_incentives', 'achieved_at')
            && Schema::hasColumn('user_incentives', 'closed_notified_at')
            && Schema::hasColumn('user_incentives', 'achieved_notified_at');
    }

    /**
     * @return array{closed: int, achieved: int}
     */
    public static function sweep(?int $userId = null): array
    {
        $sent = ['closed' => 0, 'achieved' => 0];
        if (! self::ready()) {
            return $sent;
        }

        $today = TaskBusinessTime::incentiveToday()->startOfDay();

        $rows = UserIncentive::query()
            ->where('is_active', true)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->where(function ($q) {
                $q->where(fn ($w) => $w->whereNotNull('achieved_at')->whereNull('achieved_notified_at'))
                    ->orWhere(fn ($w) => $w->whereNull('achieved_at')
                        ->whereNull('closed_notified_at')
                        ->whereNotNull('additional_condition')
                        ->where('additional_condition', '!=', ''));
            })
            ->orderBy('user_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return $sent;
        }

        $users = ChatWorkspace::activeUsersQuery()
            ->whereIn('id', $rows->pluck('user_id')->unique()->all())
            ->get()
            ->keyBy('id');

        foreach ($rows as $row) {
            $user = $users->get($row->user_id);
            if (! $user) {
                continue;
            }

            if ($row->achieved_at) {
                self::post($user, self::achievedBody($row), self::COMMAND_ACHIEVED);
                $row->forceFill(['achieved_notified_at' => now()])->saveQuietly();
                $sent['achieved']++;

                continue;
            }

            $cutoff = self::cutoffOf($row);
            if ($cutoff && $cutoff->lt($today)) {
                self::post($user, self::closedBody($row, $cutoff), self::COMMAND_CLOSED);
                $row->forceFill(['closed_notified_at' => now()])->saveQuietly();
                $sent['closed']++;
            }
        }

        return $sent;
    }

    public static function cutoffOf(UserIncentive $row): ?Carbon
    {
        $raw = trim((string) ($row->additional_condition ?? ''));
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function closedBody(UserIncentive $row, Carbon $cutoff): string
    {
        return "⏰ Incentive closed\n"
            .self::describe($row)."\n"
            .'The cutoff date ('.$cutoff->format('j M Y').") has passed, so this incentive is now closed.\n"
            .'If you would like an extension or a new incentive, just ask — message the President (president@5core.com) here in chat.';
    }

    public static function achievedBody(UserIncentive $row): string
    {
        return "🎉 Congratulations! You achieved your incentive.\n"
            .self::describe($row)."\n"
            .'Please claim it now — message the President (president@5core.com) here in chat to receive it.';
    }

    private static function describe(UserIncentive $row): string
    {
        $title = trim((string) $row->title);
        $line = $title !== '' ? "Target: {$title}" : 'Target: (untitled)';
        if ($row->amount !== null && (float) $row->amount > 0) {
            $line .= ' — Incentive: ₹'.number_format((float) $row->amount, 0);
        }

        return $line;
    }

    private static function post(User $user, string $body, string $command): void
    {
        $inbox = ChatWorkspace::botInboxFor($user);
        InventChatBot::postBot($inbox, $body, $command);
        ChatWorkspace::forgetUnreadCache((int) $user->id);
    }
}
