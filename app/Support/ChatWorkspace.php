<?php

namespace App\Support;

use App\Models\ChatChannel;
use App\Models\ChatChannelMember;
use App\Models\ChatMessage;
use App\Models\ChatNotificationPref;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ChatWorkspace
{
    public const BOT_NAME = '5 Core Bot';

    public const BOT_HANDLE = '@invent';

    public const BOT_AVATAR = 'assets/images/5core-bot-logo.png';

    public static function botDisplayName(?string $stored = null): string
    {
        $stored = trim((string) $stored);
        if ($stored === '' || in_array(strtolower($stored), ['@invent', 'invent', 'invent bot', 'inventbot'], true)) {
            return self::BOT_NAME;
        }

        return $stored;
    }

    public static function botAvatarUrl(): string
    {
        return asset(self::BOT_AVATAR);
    }

    public const ATTACH_MAX_KB = 10240;

    /** @var list<string> */
    public const ATTACH_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'csv', 'xlsx', 'xls', 'doc', 'docx', 'zip'];

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

        $defaultSlugs = array_values(array_filter(array_map(
            static fn ($def) => (string) ($def['slug'] ?? ''),
            self::defaultPublicChannels()
        )));
        $channels = ChatChannel::query()
            ->where('type', ChatChannel::TYPE_PUBLIC)
            ->where('is_archived', false)
            ->when($defaultSlugs !== [], fn ($q) => $q->whereIn('slug', $defaultSlugs))
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
                'name' => self::BOT_NAME,
                'topic' => 'Daily DAR and overdue reminders, plus @invent commands',
                'created_by' => $user->id,
            ]
        );
        if ($channel->name !== self::BOT_NAME) {
            $channel->name = self::BOT_NAME;
            $channel->save();
        }
        self::ensureMember($channel, (int) $user->id);

        ChatMessage::query()
            ->where('channel_id', $channel->id)
            ->where('is_bot', true)
            ->where(function ($q) {
                $q->where('body', 'like', '%/task Buy packing tape%')
                    ->orWhere('body', 'like', "Hi — I'm @invent%")
                    ->orWhere('body', 'like', 'Unknown command%')
                    ->orWhere('body', 'like', 'Commands:%');
            })
            ->delete();

        $welcome = ChatMessage::query()
            ->where('channel_id', $channel->id)
            ->where('command', 'welcome')
            ->first();
        if ($welcome) {
            if ($welcome->body !== InventChatBot::welcomeText()) {
                $welcome->body = InventChatBot::welcomeText();
                $welcome->save();
            }
        } else {
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

    /**
     * @param  list<int>  $memberIds
     */
    public static function createGroup(User $creator, array $memberIds, ?string $name = null): ChatChannel
    {
        $ids = array_values(array_unique(array_merge([(int) $creator->id], array_map('intval', $memberIds))));
        $ids = array_values(array_filter($ids, fn ($id) => $id > 0));
        if (count($ids) < 2) {
            abort(422, 'Pick at least one teammate.');
        }

        if (count($ids) === 2) {
            $other = User::query()->findOrFail($ids[0] === (int) $creator->id ? $ids[1] : $ids[0]);

            return self::dmBetween($creator, $other);
        }

        $people = User::query()->whereIn('id', $ids)->get(['id', 'name']);
        $label = trim((string) $name);
        if ($label === '') {
            $label = $people->pluck('name')->map(fn ($n) => explode(' ', trim((string) $n))[0] ?? $n)->take(4)->implode(', ');
        }

        $channel = ChatChannel::query()->create([
            'type' => ChatChannel::TYPE_GROUP,
            'name' => $label,
            'created_by' => $creator->id,
        ]);

        $now = now();
        $rows = [];
        foreach ($ids as $id) {
            $rows[] = [
                'channel_id' => $channel->id,
                'user_id' => $id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        ChatChannelMember::query()->insert($rows);

        $others = array_values(array_filter($ids, static fn ($id) => $id !== (int) $creator->id));
        $added = self::formatNameList(
            User::query()->whereIn('id', $others)->orderBy('name')->pluck('name')->all()
        );
        self::postSystemNotice(
            $channel,
            $creator->name.' created this group'.($added !== '' ? ' and added '.$added : ''),
            'group'
        );

        return $channel;
    }

    /**
     * @param  list<string|null>  $names
     */
    public static function formatNameList(array $names): string
    {
        $names = array_values(array_filter(array_map(static fn ($n) => trim((string) $n), $names)));
        $count = count($names);
        if ($count === 0) {
            return '';
        }
        if ($count === 1) {
            return $names[0];
        }
        if ($count === 2) {
            return $names[0].' and '.$names[1];
        }

        return implode(', ', array_slice($names, 0, -1)).' and '.$names[$count - 1];
    }

    public static function postSystemNotice(ChatChannel $channel, string $body, string $command): ChatMessage
    {
        return ChatMessage::query()->create([
            'channel_id' => $channel->id,
            'user_id' => null,
            'is_bot' => true,
            'bot_name' => self::BOT_NAME,
            'body' => $body,
            'command' => $command,
        ]);
    }

    /**
     * @param  list<int>  $userIds
     */
    public static function announceAdded(User $actor, ChatChannel $channel, array $userIds): ?ChatMessage
    {
        $userIds = array_values(array_unique(array_filter(
            array_map('intval', $userIds),
            static fn ($id) => $id > 0 && $id !== (int) $actor->id
        )));
        if ($userIds === []) {
            return null;
        }
        $list = self::formatNameList(
            User::query()->whereIn('id', $userIds)->orderBy('name')->pluck('name')->all()
        );
        if ($list === '') {
            return null;
        }

        return self::postSystemNotice($channel, $actor->name.' added '.$list, 'member');
    }

    public static function pruneImplicitPublicMembersOnce(): void
    {
        if (! self::tablesReady()) {
            return;
        }

        Cache::remember('chat_public_member_prune.v2', now()->addYear(), function () {
            self::pruneImplicitPublicMembers();

            return 1;
        });
    }

    public static function pruneImplicitPublicMembers(): void
    {
        if (! self::tablesReady()) {
            return;
        }

        $defaultSlugs = array_values(array_filter(array_map(
            static fn ($def) => (string) ($def['slug'] ?? ''),
            self::defaultPublicChannels()
        )));
        $channels = ChatChannel::query()
            ->where('type', ChatChannel::TYPE_PUBLIC)
            ->where('is_archived', false)
            ->when($defaultSlugs !== [], fn ($q) => $q->whereNotIn('slug', $defaultSlugs))
            ->get();

        foreach ($channels as $channel) {
            self::pruneImplicitPublicChannel($channel);
        }
    }

    public static function pruneImplicitPublicChannel(ChatChannel $channel): void
    {
        if ($channel->type !== ChatChannel::TYPE_PUBLIC || $channel->is_archived) {
            return;
        }
        $defaultSlugs = array_values(array_filter(array_map(
            static fn ($def) => (string) ($def['slug'] ?? ''),
            self::defaultPublicChannels()
        )));
        if (in_array((string) $channel->slug, $defaultSlugs, true)) {
            return;
        }

        $keep = [(int) $channel->created_by];
        $authors = ChatMessage::query()
            ->where('channel_id', $channel->id)
            ->where('is_bot', false)
            ->whereNotNull('user_id')
            ->pluck('user_id');
        foreach ($authors as $id) {
            $keep[] = (int) $id;
        }
        $keep = array_values(array_unique(array_filter($keep)));
        ChatChannelMember::query()
            ->where('channel_id', $channel->id)
            ->whereNotIn('user_id', $keep)
            ->delete();
    }

    /**
     * @return array{ids: list<int>, names: list<string>, count: int}
     */
    public static function memberRoster(ChatChannel $channel, int $limit = 8): array
    {
        $ids = $channel->members()->pluck('user_id')->map(fn ($id) => (int) $id)->values()->all();
        $names = User::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->pluck('name')
            ->map(static fn ($n) => trim((string) $n))
            ->filter()
            ->values()
            ->all();
        $total = count($names);
        $shown = array_slice($names, 0, $limit);
        if ($total > $limit) {
            $shown[] = ($total - $limit).' more';
        }

        return [
            'ids' => $ids,
            'names' => $shown,
            'count' => $total,
        ];
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
    public static function inboxFor(User $user, bool $bootstrap = true): array
    {
        if ($bootstrap) {
            self::bootstrap($user);
            Cache::remember('chat_public_member_sync', now()->addHour(), function () {
                self::syncPublicMembers();

                return 1;
            });
        }

        self::pruneImplicitPublicMembersOnce();

        $memberChannelIds = ChatChannelMember::query()
            ->where('user_id', $user->id)
            ->pluck('channel_id');

        $channels = ChatChannel::query()
            ->whereIn('id', $memberChannelIds)
            ->where('is_archived', false)
            ->orderByRaw("FIELD(type, 'bot', 'public', 'private', 'group', 'dm')")
            ->orderBy('name')
            ->get();

        $unread = self::unreadByChannel($user);
        $lastByChannel = ChatMessage::query()
            ->selectRaw('channel_id, MAX(id) as last_id')
            ->whereIn('channel_id', $channels->pluck('id'))
            ->groupBy('channel_id')
            ->pluck('last_id', 'channel_id');

        $reads = ChatChannelMember::query()
            ->where('user_id', $user->id)
            ->whereIn('channel_id', $channels->pluck('id'))
            ->pluck('last_read_message_id', 'channel_id');

        $memberCounts = ChatChannelMember::query()
            ->selectRaw('channel_id, COUNT(*) as c')
            ->whereIn('channel_id', $channels->pluck('id'))
            ->groupBy('channel_id')
            ->pluck('c', 'channel_id');
        $memberIdsByChannel = ChatChannelMember::query()
            ->whereIn('channel_id', $channels->pluck('id'))
            ->get(['channel_id', 'user_id'])
            ->groupBy('channel_id')
            ->map(fn ($rows) => $rows->pluck('user_id')->map(fn ($id) => (int) $id)->values()->all());

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
        $presence = ChatPresence::map($peerIds);

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

            $lastRead = (int) ($reads[$channel->id] ?? 0);
            $lastId = (int) ($lastByChannel[$channel->id] ?? 0);
            $peerPresence = $peer ? ($presence[(int) $peer->id] ?? null) : null;

            $out[] = [
                'id' => (int) $channel->id,
                'type' => $channel->type,
                'name' => $channel->isBotInbox() ? self::BOT_NAME : $label,
                'slug' => $channel->slug,
                'topic' => $channel->topic,
                'unread' => (int) ($unread[$channel->id] ?? 0),
                'last_id' => $lastId,
                'last_read_message_id' => $lastRead,
                'first_unread_id' => $lastRead > 0 ? $lastRead + 1 : null,
                'peer_id' => $peer?->id,
                'avatar' => $channel->isBotInbox() ? self::botAvatarUrl() : self::avatarUrl($peer),
                'member_count' => (int) ($memberCounts[$channel->id] ?? 0),
                'member_ids' => $memberIdsByChannel[$channel->id] ?? [],
                'online' => (bool) ($peerPresence['online'] ?? false),
                'status' => $peerPresence['status'] ?? 'active',
                'last_seen_label' => $peerPresence['last_seen_label'] ?? null,
                'can_manage_members' => self::canManageMembers($user, $channel),
                'can_delete' => self::canDeleteChannel($user, $channel),
            ];
        }

        usort($out, function (array $a, array $b) {
            $rank = ['bot' => 0, 'public' => 1, 'private' => 2, 'group' => 3, 'dm' => 4];
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

        return (int) Cache::remember($cacheKey, now()->addSeconds(3), function () use ($user) {
            return (int) array_sum(self::unreadByChannel($user));
        });
    }

    public static function forgetUnreadCache(int $userId): void
    {
        Cache::forget('chat_unread_total_'.$userId);
        Cache::forget('chat_inbox_'.$userId);
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
            '/@(channel|everyone|[A-Za-z0-9._-]+)/',
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
     * @param  list<int>  $messageIds
     * @return array<int, array{seen: bool, seen_by: list<array{id: int, name: string}>, seen_label: string}>
     */
    public static function receiptsFor(ChatChannel $channel, array $messageIds, int $viewerId): array
    {
        $messageIds = array_values(array_filter(array_map('intval', $messageIds)));
        if ($messageIds === []) {
            return [];
        }

        $members = ChatChannelMember::query()
            ->where('channel_id', $channel->id)
            ->where('user_id', '!=', $viewerId)
            ->get(['user_id', 'last_read_message_id']);

        $users = User::query()
            ->whereIn('id', $members->pluck('user_id'))
            ->get(['id', 'name'])
            ->keyBy('id');

        $out = [];
        foreach ($messageIds as $mid) {
            $seenBy = [];
            foreach ($members as $member) {
                if ((int) $member->last_read_message_id >= $mid) {
                    $person = $users->get($member->user_id);
                    if ($person) {
                        $seenBy[] = [
                            'id' => (int) $person->id,
                            'name' => (string) $person->name,
                        ];
                    }
                }
            }
            $label = 'Sent';
            if ($seenBy !== []) {
                $label = $channel->isDm() || $channel->isBotInbox()
                    ? 'Seen'
                    : 'Seen by '.collect($seenBy)->pluck('name')->take(3)->implode(', ');
            }
            $out[$mid] = [
                'seen' => $seenBy !== [],
                'seen_by' => $seenBy,
                'seen_label' => $label,
            ];
        }

        return $out;
    }

    /**
     * @param  array{seen?: bool, seen_by?: list<array{id: int, name: string}>, seen_label?: string}|null  $receipt
     * @return array<string, mixed>
     */
    public static function serializeMessage(ChatMessage $message, ?array $receipt = null): array
    {
        $user = $message->relationLoaded('user') ? $message->user : $message->user()->first();
        $tz = TaskBusinessTime::tz();
        $forwarded = null;
        if ($message->forwarded_from_id) {
            $orig = $message->relationLoaded('forwardedFrom')
                ? $message->forwardedFrom
                : $message->forwardedFrom()->with('user:id,name')->first();
            if ($orig) {
                $origUser = $orig->user;
                $forwarded = [
                    'id' => (int) $orig->id,
                    'name' => $orig->is_bot ? self::botDisplayName($orig->bot_name) : ($origUser->name ?? 'Member'),
                    'preview' => Str::limit(trim((string) ($orig->body ?: $orig->attachment_name ?: 'Attachment')), 140),
                ];
            }
        }

        $deleted = $message->deleted_at !== null;

        return [
            'id' => (int) $message->id,
            'client_id' => $message->client_id,
            'channel_id' => (int) $message->channel_id,
            'parent_id' => $message->parent_id ? (int) $message->parent_id : null,
            'user_id' => $message->user_id ? (int) $message->user_id : null,
            'is_bot' => (bool) $message->is_bot,
            'bot_name' => $message->is_bot ? self::botDisplayName($message->bot_name) : ($message->bot_name ?: self::BOT_NAME),
            'name' => $message->is_bot
                ? self::botDisplayName($message->bot_name)
                : ($user->name ?? 'Member'),
            'avatar' => $message->is_bot ? self::botAvatarUrl() : self::avatarUrl($user),
            'body' => $deleted ? null : $message->body,
            'html' => $deleted ? '<em>This message was deleted.</em>' : self::formatBody($message->body),
            'attachment_url' => (! $deleted && $message->attachment_path) ? route('chat.file', $message->id) : null,
            'attachment_name' => $deleted ? null : $message->attachment_name,
            'attachment_is_image' => self::isImageName($message->attachment_name),
            'command' => $message->command,
            'forwarded' => $forwarded,
            'edited' => (bool) $message->edited_at,
            'deleted' => $deleted,
            'pinned' => (bool) $message->pinned_at,
            'task_id' => $message->task_id ? (int) $message->task_id : null,
            'task_url' => $message->task_id ? url('/tasks?highlight='.$message->task_id) : null,
            'reply_count' => (int) ($message->reply_count ?? 0),
            'reactions' => $message->reaction_summary ?? [],
            'bookmarked' => (bool) ($message->bookmarked ?? false),
            'seen' => (bool) ($receipt['seen'] ?? false),
            'seen_by' => $receipt['seen_by'] ?? [],
            'seen_label' => $receipt['seen_label'] ?? ($message->user_id ? 'Sent' : ''),
            'created_at' => optional($message->created_at)->toIso8601String(),
            'created_label' => optional($message->created_at)->timezone($tz)->format('g:i A'),
            'permalink' => url('/chat?channel='.$message->channel_id.'&message='.$message->id),
        ];
    }

    public static function canEditMessage(?User $user, ChatMessage $message): bool
    {
        if (! $user || $message->is_bot || $message->deleted_at) {
            return false;
        }

        return (int) $message->user_id === (int) $user->id || self::canManageChannels($user);
    }

    public static function canDeleteMessage(?User $user, ChatMessage $message): bool
    {
        return self::canEditMessage($user, $message);
    }

    public static function canPin(?User $user): bool
    {
        return self::canManageChannels($user);
    }

    public static function canAnnounce(?User $user): bool
    {
        return self::canManageChannels($user);
    }

    public static function canManageMembers(?User $user, ChatChannel $channel): bool
    {
        if (! $user || $channel->isBotInbox() || $channel->isDm()) {
            return false;
        }
        if (self::canManageChannels($user)) {
            return true;
        }
        if ((int) $channel->created_by === (int) $user->id) {
            return true;
        }
        if (! ($channel->isGroup() || $channel->type === ChatChannel::TYPE_PRIVATE || $channel->type === ChatChannel::TYPE_PUBLIC)) {
            return false;
        }

        return ChatChannelMember::query()
            ->where('channel_id', $channel->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    public static function canDeleteChannel(?User $user, ChatChannel $channel): bool
    {
        if (! $user || $channel->isBotInbox() || $channel->isDm() || $channel->is_archived) {
            return false;
        }
        if (self::canManageChannels($user)) {
            return true;
        }

        return (int) $channel->created_by === (int) $user->id;
    }

    public static function notifyMode(User $user): string
    {
        if (! Schema::hasTable('chat_notification_prefs')) {
            return 'all';
        }
        $mode = ChatNotificationPref::query()->where('user_id', $user->id)->value('mode');

        return in_array($mode, ['all', 'mentions', 'dms', 'none'], true) ? $mode : 'all';
    }

    public static function notifyTone(User $user): string
    {
        if (! Schema::hasTable('chat_notification_prefs') || ! Schema::hasColumn('chat_notification_prefs', 'tone')) {
            return 'default';
        }
        $tone = ChatNotificationPref::query()->where('user_id', $user->id)->value('tone');

        return in_array($tone, ['default', 'soft', 'bright', 'knock', 'off'], true) ? $tone : 'default';
    }

    /**
     * @return list<int>
     */
    public static function mentionUserIds(string $text, ChatChannel $channel, User $actor): array
    {
        $ids = self::mentionIds($text);
        $lower = strtolower($text);
        $blast = str_contains($lower, '@everyone') || str_contains($lower, '@channel');
        if ($blast && self::canAnnounce($actor)) {
            $ids = array_values(array_unique(array_merge(
                $ids,
                $channel->members()->pluck('user_id')->map(fn ($id) => (int) $id)->all()
            )));
        }

        return array_values(array_filter($ids, fn ($id) => $id !== (int) $actor->id));
    }

    public static function markAllRead(User $user): void
    {
        $ids = ChatChannelMember::query()->where('user_id', $user->id)->pluck('channel_id');
        $maxByChannel = ChatMessage::query()
            ->selectRaw('channel_id, MAX(id) as max_id')
            ->whereIn('channel_id', $ids)
            ->groupBy('channel_id')
            ->pluck('max_id', 'channel_id');
        foreach ($maxByChannel as $channelId => $maxId) {
            ChatChannelMember::query()
                ->where('user_id', $user->id)
                ->where('channel_id', $channelId)
                ->update([
                    'last_read_message_id' => (int) $maxId,
                    'last_read_at' => now(),
                ]);
        }
        self::forgetUnreadCache((int) $user->id);
    }

    /**
     * Queue browser-notification payloads for other members (sync pulls them).
     */
    public static function queueAlerts(ChatMessage $message, ChatChannel $channel, User $actor, array $mentionIds = []): void
    {
        if ($message->deleted_at) {
            return;
        }

        $isDm = $channel->isDm() || $channel->isBotInbox();
        $isThread = (bool) $message->parent_id;
        $preview = Str::limit(trim((string) ($message->body ?: $message->attachment_name ?: 'New message')), 120);
        $title = $isDm
            ? ($actor->name ?: 'Direct message')
            : (($channel->isGroup() ? '' : '#').($channel->name ?: 'Chat'));

        $members = ChatChannelMember::query()
            ->where('channel_id', $channel->id)
            ->where('user_id', '!=', $actor->id)
            ->get(['user_id', 'muted']);

        foreach ($members as $member) {
            if ($member->muted) {
                continue;
            }
            if (ChatPresence::statusFor((int) $member->user_id) === 'dnd') {
                continue;
            }

            $user = User::query()->find($member->user_id);
            if (! $user) {
                continue;
            }

            $mode = self::notifyMode($user);
            $mentioned = in_array((int) $member->user_id, $mentionIds, true);
            $allow = match ($mode) {
                'none' => false,
                'dms' => $isDm,
                'mentions' => $mentioned || $isThread,
                default => true,
            };
            if (! $allow) {
                continue;
            }

            $kind = $mentioned ? 'mention' : ($isThread ? 'thread' : ($isDm ? 'dm' : 'message'));
            $alert = [
                'kind' => $kind,
                'title' => $mentioned ? $actor->name.' mentioned you' : $title,
                'body' => $preview,
                'channel_id' => (int) $channel->id,
                'message_id' => (int) $message->id,
            ];
            $key = 'chat_alerts_'.$member->user_id;
            $cur = Cache::get($key, []);
            if (! is_array($cur)) {
                $cur = [];
            }
            $cur[] = $alert;
            Cache::put($key, array_slice($cur, -20), now()->addMinutes(10));
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function pullAlerts(User $user): array
    {
        $key = 'chat_alerts_'.$user->id;
        $alerts = Cache::pull($key, []);

        return is_array($alerts) ? array_values($alerts) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function search(User $user, array $filters): array
    {
        $memberIds = ChatChannelMember::query()->where('user_id', $user->id)->pluck('channel_id');
        $q = ChatMessage::query()
            ->with('user:id,name,avatar')
            ->whereIn('channel_id', $memberIds)
            ->orderByDesc('id')
            ->limit(50);

        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
            $q->where(function ($w) use ($like) {
                $w->where('body', 'like', $like)
                    ->orWhere('attachment_name', 'like', $like);
            });
        }
        if (! empty($filters['user_id'])) {
            $q->where('user_id', (int) $filters['user_id']);
        }
        if (! empty($filters['channel_id'])) {
            $q->where('channel_id', (int) $filters['channel_id']);
        }
        if (! empty($filters['room_type'])) {
            $typeIds = ChatChannel::query()
                ->whereIn('id', $memberIds)
                ->where('type', $filters['room_type'])
                ->pluck('id');
            $q->whereIn('channel_id', $typeIds);
        }
        if (! empty($filters['from'])) {
            $q->whereDate('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $q->whereDate('created_at', '<=', $filters['to']);
        }
        if (! empty($filters['has_attachment'])) {
            $q->whereNotNull('attachment_path');
        }
        if (! empty($filters['has_mention'])) {
            $q->where(function ($w) use ($user) {
                $w->where('body', 'like', '%@%')
                    ->orWhereJsonContains('mentions', (int) $user->id);
            });
        }

        $channels = ChatChannel::query()->whereIn('id', $memberIds)->get(['id', 'name', 'type'])->keyBy('id');

        return $q->get()->map(function (ChatMessage $m) use ($channels) {
            $ch = $channels->get($m->channel_id);

            return [
                'id' => (int) $m->id,
                'channel_id' => (int) $m->channel_id,
                'channel_name' => $ch?->name,
                'channel_type' => $ch?->type,
                'name' => $m->user->name ?? ($m->is_bot ? self::botDisplayName($m->bot_name) : 'Member'),
                'preview' => Str::limit((string) ($m->body ?: $m->attachment_name), 160),
                'created_label' => optional($m->created_at)->timezone(TaskBusinessTime::tz())->format('M j, g:i A'),
                'has_attachment' => (bool) $m->attachment_path,
                'permalink' => url('/chat?channel='.$m->channel_id.'&message='.$m->id),
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function directory(User $viewer): array
    {
        $query = self::activeUsersQuery()
            ->where('id', '!=', $viewer->id)
            ->orderBy('name');
        $cols = ['id', 'name', 'email', 'avatar', 'designation', 'org_level'];
        if (Schema::hasColumn('users', 'resource_department_id')) {
            $cols[] = 'resource_department_id';
            if (Schema::hasTable('resource_departments')) {
                $query->with('resourceDepartment:id,name');
            }
        }
        $users = $query->get($cols);
        $presence = ChatPresence::map($users->pluck('id')->map(fn ($id) => (int) $id)->all());

        return $users
            ->map(function (User $u) use ($presence) {
                $p = $presence[(int) $u->id] ?? null;
                $department = trim((string) ($u->resourceDepartment->name ?? ''));
                if ($department === '') {
                    $department = trim((string) ($u->designation ?? ''));
                }
                if ($department === '') {
                    $department = 'Other';
                }

                return [
                    'id' => (int) $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'avatar' => self::avatarUrl($u),
                    'department' => $department,
                    'designation' => $u->designation,
                    'org_level' => $u->org_level,
                    'online' => (bool) ($p['online'] ?? false),
                    'status' => $p['status'] ?? 'active',
                    'last_seen_label' => $p['last_seen_label'] ?? 'Offline',
                ];
            })
            ->all();
    }
}
