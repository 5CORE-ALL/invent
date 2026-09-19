<?php

namespace App\Support;

use App\Models\ChatPresence as ChatPresenceRow;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ChatPresence
{
    public const ONLINE_SECONDS = 45;

    public static function touch(User $user, bool $persist = true): void
    {
        $now = now();
        Cache::put('chat_presence_'.$user->id, $now->timestamp, now()->addMinutes(2));

        if (! $persist || ! Schema::hasTable('chat_presences')) {
            return;
        }

        $throttleKey = 'chat_presence_db_'.$user->id;
        if (Cache::has($throttleKey)) {
            return;
        }
        Cache::put($throttleKey, 1, now()->addSeconds(20));

        $attrs = ['last_seen_at' => $now];
        if (Schema::hasColumn('chat_presences', 'status') && $user->getAttribute('chat_status')) {
            $attrs['status'] = $user->getAttribute('chat_status');
        }

        ChatPresenceRow::query()->updateOrCreate(
            ['user_id' => $user->id],
            $attrs
        );
    }

    public static function setStatus(User $user, string $status): void
    {
        $status = in_array($status, ['active', 'away', 'dnd'], true) ? $status : 'active';
        Cache::put('chat_presence_status_'.$user->id, $status, now()->addHours(12));
        self::touch($user);
        if (Schema::hasTable('chat_presences') && Schema::hasColumn('chat_presences', 'status')) {
            ChatPresenceRow::query()->updateOrCreate(
                ['user_id' => $user->id],
                ['status' => $status, 'last_seen_at' => now()]
            );
        }
    }

    public static function setTyping(User $user, ?int $channelId): void
    {
        Cache::put('chat_typing_'.$user->id, $channelId ?: 0, now()->addSeconds(8));
        if (Schema::hasTable('chat_presences') && Schema::hasColumn('chat_presences', 'typing_channel_id')) {
            ChatPresenceRow::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'last_seen_at' => now(),
                    'typing_channel_id' => $channelId,
                    'typing_at' => $channelId ? now() : null,
                ]
            );
        }
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public static function typingIn(int $channelId, int $exceptUserId): array
    {
        if ($channelId < 1) {
            return [];
        }
        $out = [];
        if (Schema::hasTable('chat_presences') && Schema::hasColumn('chat_presences', 'typing_channel_id')) {
            $rows = ChatPresenceRow::query()
                ->where('typing_channel_id', $channelId)
                ->where('user_id', '!=', $exceptUserId)
                ->where('typing_at', '>=', now()->subSeconds(8))
                ->get();
            $users = User::query()->whereIn('id', $rows->pluck('user_id'))->get(['id', 'name'])->keyBy('id');
            foreach ($rows as $row) {
                $u = $users->get($row->user_id);
                if ($u) {
                    $out[] = ['id' => (int) $u->id, 'name' => (string) $u->name];
                }
            }
        }

        return $out;
    }

    public static function statusFor(int $userId): string
    {
        $cached = Cache::get('chat_presence_status_'.$userId);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }
        if (Schema::hasTable('chat_presences') && Schema::hasColumn('chat_presences', 'status')) {
            $row = ChatPresenceRow::query()->where('user_id', $userId)->first();
            if ($row && $row->status) {
                return (string) $row->status;
            }
        }

        return 'active';
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, array{online: bool, last_seen_at: ?string, last_seen_label: string}>
     */
    public static function map(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        $rows = [];
        if ($userIds !== [] && Schema::hasTable('chat_presences')) {
            $rows = ChatPresenceRow::query()
                ->whereIn('user_id', $userIds)
                ->get(['user_id', 'last_seen_at'])
                ->keyBy('user_id');
        }

        $tz = TaskBusinessTime::tz();
        $out = [];
        foreach ($userIds as $id) {
            $cached = Cache::get('chat_presence_'.$id);
            $rowAt = optional($rows[$id] ?? null)->last_seen_at;
            $ts = $cached ? (int) $cached : ($rowAt ? $rowAt->timestamp : 0);
            $online = $ts > 0 && (now()->timestamp - $ts) <= self::ONLINE_SECONDS;
            $label = 'Offline';
            if ($online) {
                $label = 'Active now';
            } elseif ($ts > 0) {
                $at = \Carbon\Carbon::createFromTimestamp($ts)->timezone($tz);
                if ($at->isToday()) {
                    $label = 'Last seen '.$at->format('g:i A');
                } elseif ($at->isYesterday()) {
                    $label = 'Last seen yesterday '.$at->format('g:i A');
                } else {
                    $label = 'Last seen '.$at->format('M j');
                }
            }

            $status = self::statusFor($id);
            if ($status === 'away' && $online) {
                $label = 'Away';
            } elseif ($status === 'dnd' && $online) {
                $label = 'Do not disturb';
            }
            $out[$id] = [
                'online' => $online,
                'status' => $status,
                'last_seen_at' => $ts > 0 ? date(DATE_ATOM, $ts) : null,
                'last_seen_label' => $label,
            ];
        }

        return $out;
    }

    /**
     * @return array{online: bool, last_seen_at: ?string, last_seen_label: string}
     */
    public static function forUserId(int $userId): array
    {
        return self::map([$userId])[$userId] ?? [
            'online' => false,
            'last_seen_at' => null,
            'last_seen_label' => 'Offline',
        ];
    }
}
