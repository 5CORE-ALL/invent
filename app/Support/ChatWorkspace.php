<?php

namespace App\Support;

use App\Models\ChatChannel;
use App\Models\ChatChannelMember;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ChatWorkspace
{
    public const BOT_NAME = '@invent';

    /**
     * @return list<array{name: string, slug: string, topic: string}>
     */
    public static function defaultPublicChannels(): array
    {
        return [
            ['name' => 'general', 'slug' => 'general', 'topic' => 'Company-wide chat'],
            ['name' => 'ops', 'slug' => 'ops', 'topic' => 'Operations and daily work'],
        ];
    }

    public static function tablesReady(): bool
    {
        return Schema::hasTable('chat_channels')
            && Schema::hasTable('chat_channel_members')
            && Schema::hasTable('chat_messages');
    }

    public static function activeUsersQuery()
    {
        $query = User::query()->where('is_active', true);
        if (Schema::hasColumn('users', 'deactivated_at')) {
            $query->whereNull('deactivated_at');
        }

        return $query;
    }

    public static function canManageChannels(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (SuperAdminAccess::is($user) || SuperAdminAccess::isRoleAdmin($user) || SuperAdminAccess::isTaskAdmin($user)) {
            return true;
        }

        return $user->isDirector();
    }

    public static function canInspectOther(?User $viewer): bool
    {
        if (! $viewer) {
            return false;
        }

        if (SuperAdminAccess::is($viewer) || SuperAdminAccess::isTaskAdmin($viewer) || $viewer->isDirector()) {
            return true;
        }

        $email = strtolower(trim((string) $viewer->email));

        return in_array($email, ['software5@5core.com', 'president@5core.com'], true);
    }

    public static function bootstrap(User $user): void
    {
        if (! self::tablesReady()) {
            return;
        }

        foreach (self::defaultPublicChannels() as $def) {
            $channel = ChatChannel::query()->firstOrCreate(
                ['slug' => $def['slug']],
                [
                    'type' => ChatChannel::TYPE_PUBLIC,
                    'name' => $def['name'],
                    'topic' => $def['topic'],
                    'created_by' => $user->id,
                ]
            );
            self::ensureMember($channel, (int) $user->id);
        }

        self::botInboxFor($user);
    }

    public static function syncPublicMembers(): void
    {
        if (! self::tablesReady()) {
            return;
        }

        $userIds = self::activeUsersQuery()->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($userIds === []) {
            return;
        }

        $channels = ChatChannel::query()
            ->where('type', ChatChannel::TYPE_PUBLIC)
            ->where('is_archived', false)
            ->get();

        foreach ($channels as $channel) {
            $existing = $channel->members()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
            $missing = array_values(array_diff($userIds, $existing));
            $now = now();
            $rows = [];
            foreach ($missing as $userId) {
                $rows[] = [
                    'channel_id' => $channel->id,
                    'user_id' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($rows !== []) {
                ChatChannelMember::query()->insert($rows);
            }
        }
    }

    public static function ensureMember(ChatChannel $channel, int $userId): ChatChannelMember
    {
        return ChatChannelMember::query()->firstOrCreate(
            [
                'channel_id' => $channel->id,
                'user_id' => $userId,
            ],
            []
        );
    }

    public static function botInboxFor(User $user): ChatChannel
    {
        $slug = 'invent-bot-'.$user->id;
        $channel = ChatChannel::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'type' => ChatChannel::TYPE_BOT,
                'name' => 'Invent Bot',
                'topic' => 'Daily DAR and overdue reminders, plus @invent commands',
                'created_by' => $user->id,
            ]
        );
        self::ensureMember($channel, (int) $user->id);

        $hasMessage = ChatMessage::query()->where('channel_id', $channel->id)->exists();
        if (! $hasMessage) {
            ChatMessage::query()->create([
                'channel_id' => $channel->id,
                'user_id' => null,
                'is_bot' => true,
                'bot_name' => self::BOT_NAME,
                'body' => InventChatBot::welcomeText(),
                'command' => 'welcome',
            ]);
        }

        return $channel;
    }

    public static function dmBetween(User $a, User $b): ChatChannel
    {
        $left = min((int) $a->id, (int) $b->id);
        $right = max((int) $a->id, (int) $b->id);
        $key = $left.':'.$right;

        $channel = ChatChannel::query()->firstOrCreate(
            ['dm_key' => $key],
            [
                'type' => ChatChannel::TYPE_DM,
                'name' => $a->name.' · '.$b->name,
                'created_by' => $a->id,
            ]
        );

        self::ensureMember($channel, (int) $a->id);
        self::ensureMember($channel, (int) $b->id);

        return $channel;
    }

    public static function memberOrFail(User $user, int $channelId): ChatChannel
    {
        $channel = ChatChannel::query()->findOrFail($channelId);
        $isMember = ChatChannelMember::query()
            ->where('channel_id', $channel->id)
            ->where('user_id', $user->id)
            ->exists();

        abort_unless($isMember, 403, 'You are not in this channel.');

        return $channel;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function inboxFor(User $user): array
    {
        self::bootstrap($user);
        Cache::remember('chat_public_member_sync', now()->addHour(), function () {
            self::syncPublicMembers();

            return 1;
        });

        $memberChannelIds = ChatChannelMember::query()
            ->where('user_id', $user->id)
            ->pluck('channel_id');

        $channels = ChatChannel::query()
            ->whereIn('id', $memberChannelIds)
            ->where('is_archived', false)
            ->orderByRaw("FIELD(type, 'bot', 'public', 'private', 'dm')")
            ->orderBy('name')
            ->get();

        $unread = self::unreadByChannel($user);
        $lastByChannel = ChatMessage::query()
            ->selectRaw('channel_id, MAX(id) as last_id')
            ->whereIn('channel_id', $channels->pluck('id'))
            ->groupBy('channel_id')
            ->pluck('last_id', 'channel_id');

        $peerIds = [];
        foreach ($channels as $channel) {
            if ($channel->isDm() && $channel->dm_key) {
                [$left, $right] = array_map('intval', explode(':', (string) $channel->dm_key, 2) + [0, 0]);
                $peer = $left === (int) $user->id ? $right : $left;
                if ($peer > 0) {
                    $peerIds[] = $peer;
                }
            }
        }
        $peers = $peerIds === []
            ? collect()
            : User::query()->whereIn('id', $peerIds)->get(['id', 'name', 'email', 'avatar'])->keyBy('id');

        $out = [];
        foreach ($channels as $channel) {
            $label = (string) $channel->name;
            $peer = null;
            if ($channel->isDm() && $channel->dm_key) {
                [$left, $right] = array_map('intval', explode(':', (string) $channel->dm_key, 2) + [0, 0]);
                $peerId = $left === (int) $user->id ? $right : $left;
                $peer = $peers->get($peerId);
                $label = $peer?->name ?: $label;
            }

            $out[] = [
                'id' => (int) $channel->id,
                'type' => $channel->type,
                'name' => $label,
                'slug' => $channel->slug,
                'topic' => $channel->topic,
                'unread' => (int) ($unread[$channel->id] ?? 0),
                'last_id' => (int) ($lastByChannel[$channel->id] ?? 0),
                'peer_id' => $peer?->id,
                'avatar' => $channel->isBotInbox() ? null : self::avatarUrl($peer),
            ];
        }

        usort($out, function (array $a, array $b) {
            $rank = ['bot' => 0, 'public' => 1, 'private' => 2, 'dm' => 3];
            $ra = $rank[$a['type']] ?? 9;
            $rb = $rank[$b['type']] ?? 9;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }
            if (($b['unread'] > 0) !== ($a['unread'] > 0)) {
                return ($b['unread'] > 0) <=> ($a['unread'] > 0);
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return $out;
    }

    /**
     * @return array<int, int>
     */
    public static function unreadByChannel(User $user): array
    {
        if (! self::tablesReady()) {
            return [];
        }

        $rows = ChatMessage::query()
            ->selectRaw('chat_messages.channel_id, COUNT(*) as unread')
            ->join('chat_channel_members as m', function ($join) use ($user) {
                $join->on('m.channel_id', '=', 'chat_messages.channel_id')
                    ->where('m.user_id', '=', $user->id);
            })
            ->where(function ($q) {
                $q->whereNull('m.last_read_message_id')
                    ->orWhereColumn('chat_messages.id', '>', 'm.last_read_message_id');
            })
            ->where(function ($q) use ($user) {
                $q->whereNull('chat_messages.user_id')
                    ->orWhere('chat_messages.user_id', '!=', $user->id);
            })
            ->groupBy('chat_messages.channel_id')
            ->pluck('unread', 'channel_id');

        $out = [];
        foreach ($rows as $channelId => $count) {
            $out[(int) $channelId] = (int) $count;
        }

        return $out;
    }

    public static function unreadTotal(?User $user): int
    {
        if (! $user || ! self::tablesReady()) {
            return 0;
        }

        $cacheKey = 'chat_unread_total_'.$user->id;

        return (int) Cache::remember($cacheKey, now()->addSeconds(15), function () use ($user) {
            return (int) array_sum(self::unreadByChannel($user));
        });
    }

    public static function forgetUnreadCache(int $userId): void
    {
        Cache::forget('chat_unread_total_'.$userId);
    }

    public static function markRead(User $user, ChatChannel $channel, ?int $messageId = null): void
    {
        $maxId = $messageId ?: (int) ChatMessage::query()
            ->where('channel_id', $channel->id)
            ->max('id');

        if ($maxId < 1) {
            return;
        }

        ChatChannelMember::query()
            ->where('channel_id', $channel->id)
            ->where('user_id', $user->id)
            ->update([
                'last_read_message_id' => $maxId,
                'last_read_at' => now(),
            ]);

        self::forgetUnreadCache((int) $user->id);
    }

    /**
     * @return Collection<int, User>
     */
    public static function resolveMentions(string $text): Collection
    {
        preg_match_all('/@([A-Za-z0-9._-]+)/', $text, $matches);
        $tokens = array_values(array_unique(array_map('strtolower', $matches[1] ?? [])));
        $tokens = array_values(array_filter($tokens, fn ($t) => $t !== 'invent'));
        if ($tokens === []) {
            return collect();
        }

        $users = self::activeUsersQuery()->get(['id', 'name', 'email']);
        $found = collect();

        foreach ($tokens as $token) {
            $match = $users->first(function (User $u) use ($token) {
                $email = strtolower(trim((string) $u->email));
                $local = (string) (strstr($email, '@', true) ?: $email);
                $name = strtolower(trim((string) $u->name));
                $first = strtolower((string) (explode(' ', $name)[0] ?? ''));
                $slug = strtolower((string) preg_replace('/[^a-z0-9]+/', '', $name));

                return $local === $token
                    || $email === $token
                    || $first === $token
                    || $slug === $token
                    || ($name !== '' && str_starts_with($name, $token));
            });
            if ($match) {
                $found->put($match->id, $match);
            }
        }

        return $found->values();
    }

    /**
     * @return list<int>
     */
    public static function mentionIds(string $text): array
    {
        return self::resolveMentions($text)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public static function avatarUrl(?User $user): ?string
    {
        if (! $user || ! $user->avatar) {
            return asset('images/users/avatar-2.jpg');
        }

        $avatar = (string) $user->avatar;
        if (Str::startsWith($avatar, ['http://', 'https://', '/'])) {
            return $avatar;
        }

        return asset('storage/'.$avatar);
    }

    public static function formatBody(?string $body): string
    {
        $text = e((string) $body);
        $text = preg_replace(
            '~(https?://[^\s<]+)~i',
            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
            $text
        ) ?? $text;
        $text = preg_replace(
            '/@([A-Za-z0-9._-]+)/',
            '<span class="invent-chat-mention">@$1</span>',
            $text
        ) ?? $text;

        return nl2br($text);
    }

    public static function isImageName(?string $name): bool
    {
        $ext = strtolower((string) pathinfo((string) $name, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public static function serializeMessage(ChatMessage $message): array
    {
        $user = $message->relationLoaded('user') ? $message->user : $message->user()->first();
        $tz = TaskBusinessTime::tz();

        return [
            'id' => (int) $message->id,
            'channel_id' => (int) $message->channel_id,
            'user_id' => $message->user_id ? (int) $message->user_id : null,
            'is_bot' => (bool) $message->is_bot,
            'bot_name' => $message->bot_name ?: self::BOT_NAME,
            'name' => $message->is_bot
                ? ($message->bot_name ?: self::BOT_NAME)
                : ($user->name ?? 'Member'),
            'avatar' => $message->is_bot ? null : self::avatarUrl($user),
            'body' => $message->body,
            'html' => self::formatBody($message->body),
            'attachment_url' => $message->attachment_path ? route('chat.file', $message->id) : null,
            'attachment_name' => $message->attachment_name,
            'attachment_is_image' => self::isImageName($message->attachment_name),
            'command' => $message->command,
            'created_at' => optional($message->created_at)->toIso8601String(),
            'created_label' => optional($message->created_at)->timezone($tz)->format('g:i A'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function directory(User $viewer): array
    {
        return self::activeUsersQuery()
            ->where('id', '!=', $viewer->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'avatar', 'designation', 'org_level'])
            ->map(fn (User $u) => [
                'id' => (int) $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'avatar' => self::avatarUrl($u),
                'designation' => $u->designation,
                'org_level' => $u->org_level,
            ])
            ->all();
    }
}
