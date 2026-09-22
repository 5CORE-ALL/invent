<?php

namespace App\Http\Controllers;

use App\Models\ChatBookmark;
use App\Models\ChatChannel;
use App\Models\ChatChannelMember;
use App\Models\ChatMessage;
use App\Models\ChatNotificationPref;
use App\Models\ChatReaction;
use App\Models\Task;
use App\Models\User;
use App\Support\ChatAudit;
use App\Support\ChatPresence;
use App\Support\ChatWorkspace;
use App\Support\InventChatBot;
use App\Support\TaskBusinessTime;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        abort_unless($user, 403);

        if (ChatWorkspace::tablesReady()) {
            ChatWorkspace::bootstrap($user);
            ChatPresence::touch($user);
        }

        return view('chat.index', [
            'meId' => (int) $user->id,
            'meName' => (string) $user->name,
            'canManageChannels' => ChatWorkspace::canManageChannels($user),
            'canAnnounce' => ChatWorkspace::canAnnounce($user),
            'canPin' => ChatWorkspace::canPin($user),
            'canInspect' => ChatWorkspace::canInspectOther($user),
            'notifyMode' => ChatWorkspace::notifyMode($user),
            'notifyTone' => ChatWorkspace::notifyTone($user),
            'presenceStatus' => ChatPresence::statusFor((int) $user->id),
            'broadcastDriver' => (string) config('broadcasting.default'),
        ]);
    }

    public function inbox(): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        if (! ChatWorkspace::tablesReady()) {
            return response()->json(['channels' => [], 'unread' => 0]);
        }

        $this->releaseSessionLock();
        ChatPresence::touch($user, false);
        $channels = ChatWorkspace::inboxFor($user);

        return response()->json([
            'channels' => $channels,
            'unread' => (int) array_sum(array_column($channels, 'unread')),
            'directory' => ChatWorkspace::directory($user),
        ]);
    }

    public function unread(): JsonResponse
    {
        $user = Auth::user();
        $this->releaseSessionLock();

        return response()->json([
            'unread' => ChatWorkspace::unreadTotal($user),
        ]);
    }

    public function messages(Request $request, int $channel): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $this->releaseSessionLock();
        $row = ChatWorkspace::memberOrFail($user, $channel);

        $after = (int) $request->query('after', 0);
        $before = (int) $request->query('before', 0);
        $around = (int) $request->query('around', 0);
        $parentId = (int) $request->query('parent_id', 0);
        $query = ChatMessage::query()
            ->with($this->messageRelations())
            ->where('channel_id', $row->id);

        if ($parentId > 0 && Schema::hasColumn('chat_messages', 'parent_id')) {
            $query->where(function ($q) use ($parentId) {
                $q->where('id', $parentId)->orWhere('parent_id', $parentId);
            });
        } elseif (Schema::hasColumn('chat_messages', 'parent_id') && ! $request->boolean('include_threads')) {
            $query->whereNull('parent_id');
        }

        $hasMore = false;
        if ($around > 0) {
            $beforeRows = (clone $query)->where('id', '<', $around)->orderByDesc('id')->limit(25)->get()->reverse()->values();
            $afterRows = (clone $query)->where('id', '>=', $around)->orderBy('id')->limit(26)->get();
            $messages = $beforeRows->concat($afterRows)->values();
        } elseif ($after > 0) {
            $messages = (clone $query)->where('id', '>', $after)->orderBy('id')->limit(200)->get();
        } elseif ($before > 0) {
            $messages = (clone $query)->where('id', '<', $before)->orderByDesc('id')->limit(50)->get()->reverse()->values();
            $hasMore = $messages->count() === 50;
        } else {
            $messages = (clone $query)->orderByDesc('id')->limit(80)->get()->reverse()->values();
            $hasMore = ChatMessage::query()->where('channel_id', $row->id)->count() > 80;
        }

        if ($before < 1) {
            ChatWorkspace::markRead($user, $row, (int) ($messages->max('id') ?: 0));
        }
        ChatPresence::touch($user, false);

        $pinned = [];
        if (Schema::hasColumn('chat_messages', 'pinned_at')) {
            $pins = ChatMessage::query()
                ->with($this->messageRelations())
                ->where('channel_id', $row->id)
                ->whereNotNull('pinned_at')
                ->orderByDesc('pinned_at')
                ->limit(20)
                ->get();
            $pinned = $this->serializeMessages($row, $pins, (int) $user->id, false);
        }

        return response()->json([
            'channel' => $this->channelPayload($row, $user),
            'messages' => $this->serializeMessages($row, $messages, (int) $user->id),
            'receipts' => $this->recentReceipts($row, (int) $user->id),
            'pinned' => $pinned,
            'has_more' => $hasMore,
            'typing' => ChatPresence::typingIn((int) $row->id, (int) $user->id),
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $this->releaseSessionLock();
        ChatPresence::touch($user, false);

        if (! ChatWorkspace::tablesReady()) {
            return response()->json(['channels' => [], 'messages' => [], 'unread' => 0]);
        }

        $channelId = (int) $request->query('channel', 0);
        $after = (int) $request->query('after', 0);
        $wantInbox = $request->boolean('inbox');
        $payload = [];
        $receipts = [];
        $channel = null;

        if ($channelId > 0) {
            $row = ChatWorkspace::memberOrFail($user, $channelId);
            if ($after > 0) {
                $messages = ChatMessage::query()
                    ->with($this->messageRelations())
                    ->where('channel_id', $row->id)
                    ->where('id', '>', $after)
                    ->orderBy('id')
                    ->limit(80)
                    ->get();
                $payload = $this->serializeMessages($row, $messages, (int) $user->id, false);
                $incomingMax = (int) ($messages->where('user_id', '!=', $user->id)->max('id') ?: 0);
                if ($incomingMax > 0) {
                    ChatWorkspace::markRead($user, $row, $incomingMax);
                }
            }
            if ($wantInbox) {
                $channel = $this->channelPayload($row, $user);
                $receipts = $this->recentReceipts($row, (int) $user->id);
            }
        }

        $out = [
            'messages' => $payload,
            'receipts' => $receipts,
            'channel' => $channel,
            'unread' => ChatWorkspace::unreadTotal($user),
            'alerts' => ChatWorkspace::pullAlerts($user),
            'typing' => $channelId > 0 ? ChatPresence::typingIn($channelId, (int) $user->id) : [],
            'online' => true,
            'driver' => (string) config('broadcasting.default'),
        ];

        if ($wantInbox) {
            $channels = Cache::remember('chat_inbox_'.$user->id, now()->addSeconds(2), function () use ($user) {
                return ChatWorkspace::inboxFor($user, false);
            });
            $out['channels'] = $channels;
            $out['unread'] = (int) array_sum(array_column($channels, 'unread'));
        }

        return response()->json($out);
    }

    public function presence(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $this->releaseSessionLock();

        if ($request->filled('status')) {
            ChatPresence::setStatus($user, (string) $request->input('status'));
        } else {
            ChatPresence::touch($user);
        }

        if ($request->exists('typing_channel_id')) {
            $cid = (int) $request->input('typing_channel_id');
            ChatPresence::setTyping($user, $cid > 0 ? $cid : null);
        }

        return response()->json([
            'ok' => true,
            'status' => ChatPresence::statusFor((int) $user->id),
        ]);
    }

    public function storeMessage(Request $request, int $channel): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $this->releaseSessionLock();
        $row = ChatWorkspace::memberOrFail($user, $channel);

        $validated = $request->validate([
            'body' => 'nullable|string|max:8000',
            'file' => 'nullable|file|max:'.ChatWorkspace::ATTACH_MAX_KB.'|mimes:'.implode(',', ChatWorkspace::ATTACH_EXT),
            'client_id' => 'nullable|string|max:64',
            'parent_id' => 'nullable|integer',
        ]);

        $body = trim((string) ($validated['body'] ?? ''));
        $file = $request->file('file');
        $clientId = trim((string) ($validated['client_id'] ?? ''));

        if ($body === '' && ! $file) {
            return response()->json(['message' => 'Type a message or attach a file.'], 422);
        }

        if ($clientId !== '' && Schema::hasColumn('chat_messages', 'client_id')) {
            $existing = ChatMessage::query()->where('client_id', $clientId)->first();
            if ($existing) {
                abort_unless((int) $existing->channel_id === (int) $row->id && (int) $existing->user_id === (int) $user->id, 409);
                $existing->setRelation('user', $user);

                return response()->json([
                    'messages' => $this->serializeMessages($row, collect([$existing]), (int) $user->id, false),
                    'duplicate' => true,
                ]);
            }
        }

        $parentId = (int) ($validated['parent_id'] ?? 0);
        if ($parentId > 0) {
            $parent = ChatMessage::query()->where('channel_id', $row->id)->where('id', $parentId)->first();
            abort_unless($parent, 422, 'Thread parent was not found.');
        }

        $lower = strtolower($body);
        if ((str_contains($lower, '@everyone') || str_contains($lower, '@channel')) && ! ChatWorkspace::canAnnounce($user)) {
            return response()->json(['message' => '@everyone and @channel are limited to channel managers.'], 403);
        }

        try {
            $created = DB::transaction(function () use ($user, $row, $body, $file, $clientId, $parentId) {
                $path = null;
                $orig = null;
                $mime = null;
                $size = null;
                if ($file) {
                    $orig = $file->getClientOriginalName();
                    $safe = Str::limit(Str::slug(pathinfo($orig, PATHINFO_FILENAME)) ?: 'file', 80, '');
                    $ext = strtolower((string) $file->getClientOriginalExtension());
                    abort_unless(in_array($ext, ChatWorkspace::ATTACH_EXT, true), 422, 'That file type is not allowed.');
                    $stored = $safe.'_'.Str::lower(Str::random(8)).($ext !== '' ? '.'.$ext : '');
                    $path = $file->storeAs('chat/'.$row->id, $stored, 'local');
                    $mime = $file->getMimeType();
                    $size = (int) $file->getSize();
                }

                $mentions = str_contains($body, '@')
                    ? ChatWorkspace::mentionUserIds($body, $row, $user)
                    : [];

                $attrs = [
                    'channel_id' => $row->id,
                    'user_id' => $user->id,
                    'is_bot' => false,
                    'body' => $body !== '' ? $body : null,
                    'attachment_path' => $path,
                    'attachment_name' => $orig,
                    'mentions' => $mentions,
                ];
                if ($clientId !== '' && Schema::hasColumn('chat_messages', 'client_id')) {
                    $attrs['client_id'] = $clientId;
                }
                if ($parentId > 0 && Schema::hasColumn('chat_messages', 'parent_id')) {
                    $attrs['parent_id'] = $parentId;
                }
                if ($mime && Schema::hasColumn('chat_messages', 'attachment_mime')) {
                    $attrs['attachment_mime'] = $mime;
                    $attrs['attachment_size'] = $size;
                }

                $message = ChatMessage::query()->create($attrs);
                $message->setRelation('user', $user);

                ChatWorkspace::markRead($user, $row, (int) $message->id);
                ChatWorkspace::forgetUnreadCache((int) $user->id);
                ChatPresence::touch($user, false);
                ChatAudit::record($user, 'message.sent', 'chat_message', (int) $message->id, (int) $row->id, [
                    'parent_id' => $parentId ?: null,
                    'has_file' => (bool) $path,
                ]);
                ChatWorkspace::queueAlerts($message, $row, $user, $mentions);

                $out = collect([$message]);

                if ($body !== '' && InventChatBot::looksLikeCommand($body)) {
                    $bot = InventChatBot::reply($user, $row, $body, false);
                    if ($bot) {
                        $out->push($bot);
                        ChatWorkspace::markRead($user, $row, (int) $bot->id);
                    }
                }

                return $out;
            });
        } catch (\Throwable $e) {
            ChatAudit::health('failed_delivery', $user, (int) $row->id, ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Could not send. Try again.'], 500);
        }

        $this->forgetPeerUnread($row, (int) $user->id);

        return response()->json([
            'messages' => $this->serializeMessages($row, $created, (int) $user->id, false),
        ]);
    }

    public function storeChannel(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user && ChatWorkspace::canManageChannels($user), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'topic' => 'nullable|string|max:255',
            'type' => 'required|in:public,private',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'integer|exists:users,id',
        ]);

        $name = trim($validated['name']);
        $slug = Str::slug($name);
        if ($slug === '') {
            return response()->json(['message' => 'Enter a valid channel name.'], 422);
        }

        if (ChatChannel::query()->where('slug', $slug)->exists()) {
            return response()->json(['message' => 'That channel already exists.'], 422);
        }

        $channel = ChatChannel::query()->create([
            'type' => $validated['type'],
            'name' => $name,
            'slug' => $slug,
            'topic' => $validated['topic'] ?? null,
            'created_by' => $user->id,
        ]);

        $memberIds = array_unique(array_map('intval', array_merge(
            [(int) $user->id],
            $validated['member_ids'] ?? []
        )));

        if ($validated['type'] === ChatChannel::TYPE_PUBLIC) {
            $memberIds = ChatWorkspace::activeUsersQuery()->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (! in_array((int) $user->id, $memberIds, true)) {
                $memberIds[] = (int) $user->id;
            }
        }

        $now = now();
        $rows = [];
        foreach ($memberIds as $memberId) {
            $rows[] = [
                'channel_id' => $channel->id,
                'user_id' => $memberId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($rows !== []) {
            ChatChannelMember::query()->insert($rows);
        }

        ChatAudit::record($user, 'channel.created', 'chat_channel', (int) $channel->id, (int) $channel->id, [
            'type' => $channel->type,
            'name' => $channel->name,
        ]);

        ChatMessage::query()->create([
            'channel_id' => $channel->id,
            'user_id' => null,
            'is_bot' => true,
            'bot_name' => ChatWorkspace::BOT_NAME,
            'body' => '#'.$channel->name.' created by '.$user->name.'.',
            'command' => 'channel',
        ]);

        return response()->json(['channel_id' => (int) $channel->id]);
    }

    public function storeDm(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $other = User::query()->findOrFail($validated['user_id']);
        abort_if((int) $other->id === (int) $user->id, 422, 'Pick someone else for a DM.');

        $channel = ChatWorkspace::dmBetween($user, $other);

        return response()->json(['channel_id' => (int) $channel->id]);
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $validated = $request->validate([
            'name' => 'nullable|string|max:80',
            'member_ids' => 'required|array|min:1',
            'member_ids.*' => 'integer|exists:users,id',
        ]);

        $channel = ChatWorkspace::createGroup(
            $user,
            $validated['member_ids'],
            $validated['name'] ?? null
        );
        ChatAudit::record($user, $channel->isGroup() ? 'group.created' : 'dm.created', 'chat_channel', (int) $channel->id, (int) $channel->id);

        return response()->json(['channel_id' => (int) $channel->id]);
    }

    public function forward(Request $request, int $message): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $source = ChatMessage::query()->findOrFail($message);
        ChatWorkspace::memberOrFail($user, (int) $source->channel_id);

        $validated = $request->validate([
            'channel_id' => 'required|integer|exists:chat_channels,id',
        ]);

        $dest = ChatWorkspace::memberOrFail($user, (int) $validated['channel_id']);

        $attrs = [
            'channel_id' => $dest->id,
            'user_id' => $user->id,
            'is_bot' => false,
            'body' => $source->body,
            'attachment_path' => $source->attachment_path,
            'attachment_name' => $source->attachment_name,
            'mentions' => [],
        ];
        if (Schema::hasColumn('chat_messages', 'forwarded_from_id')) {
            $attrs['forwarded_from_id'] = $source->id;
        }

        $copy = ChatMessage::query()->create($attrs);
        $copy->setRelation('user', $user);
        if ($copy->forwarded_from_id) {
            $copy->setRelation('forwardedFrom', $source);
        }

        ChatWorkspace::markRead($user, $dest, (int) $copy->id);
        $this->forgetPeerUnread($dest, (int) $user->id);
        ChatAudit::record($user, 'message.forwarded', 'chat_message', (int) $copy->id, (int) $dest->id, [
            'from_message_id' => (int) $source->id,
            'from_channel_id' => (int) $source->channel_id,
        ]);
        ChatWorkspace::queueAlerts($copy, $dest, $user, []);

        return response()->json([
            'channel_id' => (int) $dest->id,
            'messages' => $this->serializeMessages($dest, collect([$copy]), (int) $user->id),
        ]);
    }

    public function file(int $message): StreamedResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $row = ChatMessage::query()->findOrFail($message);
        ChatWorkspace::memberOrFail($user, (int) $row->channel_id);
        abort_if($row->deleted_at, 404);
        abort_unless($row->attachment_path && Storage::disk('local')->exists($row->attachment_path), 404);

        $name = $row->attachment_name ?: basename($row->attachment_path);
        $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        abort_unless(in_array($ext, ChatWorkspace::ATTACH_EXT, true) || $ext === '', 403);

        $headers = [];
        if (ChatWorkspace::isImageName($name)) {
            $headers['Content-Disposition'] = 'inline; filename="'.$name.'"';
        }

        return Storage::disk('local')->response(
            $row->attachment_path,
            $name,
            $headers
        );
    }

    public function updateMessage(Request $request, int $message): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $row = ChatMessage::query()->findOrFail($message);
        $channel = ChatWorkspace::memberOrFail($user, (int) $row->channel_id);
        abort_unless(ChatWorkspace::canEditMessage($user, $row), 403);

        $validated = $request->validate(['body' => 'required|string|max:8000']);
        $row->body = trim($validated['body']);
        if (Schema::hasColumn('chat_messages', 'edited_at')) {
            $row->edited_at = now();
        }
        if (str_contains((string) $row->body, '@')) {
            $row->mentions = ChatWorkspace::mentionUserIds((string) $row->body, $channel, $user);
        }
        $row->save();
        ChatAudit::record($user, 'message.edited', 'chat_message', (int) $row->id, (int) $channel->id);
        $row->setRelation('user', $row->user ?: $user);

        return response()->json(['messages' => $this->serializeMessages($channel, collect([$row]), (int) $user->id, false)]);
    }

    public function destroyMessage(int $message): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $row = ChatMessage::query()->findOrFail($message);
        $channel = ChatWorkspace::memberOrFail($user, (int) $row->channel_id);
        abort_unless(ChatWorkspace::canDeleteMessage($user, $row), 403);

        $row->body = null;
        if (Schema::hasColumn('chat_messages', 'deleted_at')) {
            $row->deleted_at = now();
        }
        $row->save();
        ChatAudit::record($user, 'message.deleted', 'chat_message', (int) $row->id, (int) $channel->id);

        return response()->json(['ok' => true, 'id' => (int) $row->id]);
    }

    public function react(Request $request, int $message): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);
        abort_unless(Schema::hasTable('chat_reactions'), 422);
        $row = ChatMessage::query()->findOrFail($message);
        $channel = ChatWorkspace::memberOrFail($user, (int) $row->channel_id);
        $validated = $request->validate(['emoji' => 'required|string|max:32']);
        $emoji = $validated['emoji'];

        $existing = ChatReaction::query()
            ->where('message_id', $row->id)
            ->where('user_id', $user->id)
            ->where('emoji', $emoji)
            ->first();
        if ($existing) {
            $existing->delete();
        } else {
            ChatReaction::query()->create([
                'message_id' => $row->id,
                'user_id' => $user->id,
                'emoji' => $emoji,
            ]);
        }
        $row->setRelation('user', $row->user);

        return response()->json(['messages' => $this->serializeMessages($channel, collect([$row]), (int) $user->id, false)]);
    }

    public function pin(int $message): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user && ChatWorkspace::canPin($user), 403);
        $row = ChatMessage::query()->findOrFail($message);
        $channel = ChatWorkspace::memberOrFail($user, (int) $row->channel_id);
        abort_unless(Schema::hasColumn('chat_messages', 'pinned_at'), 422);

        $row->pinned_at = $row->pinned_at ? null : now();
        $row->pinned_by = $row->pinned_at ? $user->id : null;
        $row->save();
        ChatAudit::record($user, $row->pinned_at ? 'message.pinned' : 'message.unpinned', 'chat_message', (int) $row->id, (int) $channel->id);

        return response()->json(['ok' => true, 'pinned' => (bool) $row->pinned_at]);
    }

    public function bookmark(int $message): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);
        abort_unless(Schema::hasTable('chat_bookmarks'), 422);
        $row = ChatMessage::query()->findOrFail($message);
        ChatWorkspace::memberOrFail($user, (int) $row->channel_id);

        $existing = ChatBookmark::query()->where('user_id', $user->id)->where('message_id', $row->id)->first();
        if ($existing) {
            $existing->delete();
            $saved = false;
        } else {
            ChatBookmark::query()->create(['user_id' => $user->id, 'message_id' => $row->id]);
            $saved = true;
        }

        return response()->json(['ok' => true, 'bookmarked' => $saved]);
    }

    public function markRead(int $channel): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $row = ChatWorkspace::memberOrFail($user, $channel);
        $maxId = (int) (ChatMessage::query()->where('channel_id', $row->id)->max('id') ?: 0);
        ChatWorkspace::markRead($user, $row, $maxId);

        return response()->json(['ok' => true, 'unread' => ChatWorkspace::unreadTotal($user)]);
    }

    public function markAllRead(): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);
        ChatWorkspace::markAllRead($user);

        return response()->json(['ok' => true, 'unread' => 0]);
    }

    public function search(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $this->releaseSessionLock();

        return response()->json([
            'results' => ChatWorkspace::search($user, $request->all()),
        ]);
    }

    public function prefs(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'mode' => 'required|in:all,mentions,dms,none',
                'tone' => 'nullable|in:default,soft,bright,knock,off',
            ]);
            $tone = $validated['tone'] ?? ChatWorkspace::notifyTone($user);
            if (Schema::hasTable('chat_notification_prefs')) {
                $attrs = ['mode' => $validated['mode']];
                if (Schema::hasColumn('chat_notification_prefs', 'tone')) {
                    $attrs['tone'] = $tone;
                }
                ChatNotificationPref::query()->updateOrCreate(
                    ['user_id' => $user->id],
                    $attrs
                );
            }

            return response()->json(['ok' => true, 'mode' => $validated['mode'], 'tone' => $tone]);
        }

        return response()->json([
            'mode' => ChatWorkspace::notifyMode($user),
            'tone' => ChatWorkspace::notifyTone($user),
        ]);
    }

    public function members(Request $request, int $channel): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $row = ChatWorkspace::memberOrFail($user, $channel);
        abort_unless(ChatWorkspace::canManageMembers($user, $row), 403);

        $validated = $request->validate([
            'add' => 'nullable|array',
            'add.*' => 'integer|exists:users,id',
            'remove' => 'nullable|array',
            'remove.*' => 'integer|exists:users,id',
        ]);

        foreach ($validated['add'] ?? [] as $uid) {
            ChatChannelMember::query()->firstOrCreate(
                ['channel_id' => $row->id, 'user_id' => (int) $uid],
                ['created_at' => now(), 'updated_at' => now()]
            );
            ChatAudit::record($user, 'member.added', 'user', (int) $uid, (int) $row->id);
        }
        foreach ($validated['remove'] ?? [] as $uid) {
            if ((int) $uid === (int) $user->id && ! ChatWorkspace::canManageChannels($user)) {
                continue;
            }
            ChatChannelMember::query()->where('channel_id', $row->id)->where('user_id', (int) $uid)->delete();
            ChatAudit::record($user, 'member.removed', 'user', (int) $uid, (int) $row->id);
        }
        ChatWorkspace::forgetUnreadCache((int) $user->id);

        return response()->json(['ok' => true, 'member_count' => $row->members()->count()]);
    }

    public function createTask(Request $request, int $message): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $row = ChatMessage::query()->findOrFail($message);
        $channel = ChatWorkspace::memberOrFail($user, (int) $row->channel_id);
        $validated = $request->validate([
            'assign_user_id' => 'nullable|integer|exists:users,id',
            'due_date' => 'nullable|date',
        ]);

        $assignee = ! empty($validated['assign_user_id'])
            ? User::query()->find($validated['assign_user_id'])
            : $user;
        abort_unless($assignee, 422);
        if ((int) $assignee->id !== (int) $user->id && ! ChatWorkspace::canInspectOther($user)) {
            abort(403, 'You can only assign this task to yourself.');
        }

        $due = ! empty($validated['due_date'])
            ? \Carbon\Carbon::parse($validated['due_date'], TaskBusinessTime::tz())
            : TaskBusinessTime::now()->addDays(5);

        $permalink = url('/chat?channel='.$channel->id.'&message='.$row->id);
        $task = new Task([
            'title' => Str::limit(trim((string) ($row->body ?: 'Chat task')), 1000, ''),
            'description' => trim((string) $row->body)."\n\nFrom chat: ".$permalink,
            'priority' => 'normal',
            'assignor' => $user->email,
            'assign_to' => $assignee->email ?: $assignee->name,
            'status' => 'Todo',
            'eta_time' => 10,
            'start_date' => TaskBusinessTime::now(),
            'completion_date' => $due,
            'due_date' => $due,
            'completion_day' => 0,
            'etc_done' => 0,
            'is_missed' => 0,
            'is_missed_track' => 0,
            'workspace' => 0,
            'order' => 0,
            'task_id' => '',
            'is_data_from' => 0,
            'is_automate_task' => 0,
            'task_type' => 'manual',
            'rework_reason' => '',
            'delete_rating' => 0,
            'delete_feedback' => '',
            'reference_link' => $permalink,
        ]);
        $nextId = $this->nextTaskIdIfNeeded();
        if ($nextId) {
            $task->incrementing = false;
            $task->id = $nextId;
        }
        $task->save();

        if (Schema::hasColumn('chat_messages', 'task_id')) {
            $row->task_id = $task->id;
            $row->save();
        }
        ChatAudit::record($user, 'task.created_from_message', 'task', (int) $task->id, (int) $channel->id, [
            'message_id' => (int) $row->id,
        ]);

        return response()->json([
            'ok' => true,
            'task_id' => (int) $task->id,
            'task_url' => url('/tasks?highlight='.$task->id),
        ]);
    }

    public function healthEvent(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $validated = $request->validate([
            'type' => 'required|in:reconnect,failed_delivery,offline',
            'channel_id' => 'nullable|integer',
        ]);
        ChatAudit::health($validated['type'], $user, $validated['channel_id'] ?? null);

        return response()->json(['ok' => true]);
    }

    private function nextTaskIdIfNeeded(): ?int
    {
        try {
            $col = DB::selectOne("SHOW COLUMNS FROM `tasks` WHERE Field = 'id'");
            $extra = strtolower((string) ($col->Extra ?? ''));
            if (str_contains($extra, 'auto_increment')) {
                return null;
            }
        } catch (\Throwable) {
        }

        return ((int) (DB::table('tasks')->max('id') ?? 0)) + 1;
    }

    /**
     * @return list<string>
     */
    private function messageRelations(): array
    {
        $with = ['user:id,name,email,avatar'];
        if (Schema::hasColumn('chat_messages', 'forwarded_from_id')) {
            $with[] = 'forwardedFrom.user:id,name';
        }

        return $with;
    }

    /**
     * @return array<int, array{seen: bool, seen_by: list<array{id: int, name: string}>, seen_label: string}>
     */
    private function recentReceipts(?ChatChannel $channel, int $viewerId): array
    {
        if (! $channel) {
            return [];
        }

        $ids = ChatMessage::query()
            ->where('channel_id', $channel->id)
            ->where('user_id', $viewerId)
            ->latest('id')
            ->limit(40)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return ChatWorkspace::receiptsFor($channel, $ids, $viewerId);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ChatMessage>|\Illuminate\Database\Eloquent\Collection<int, ChatMessage>  $messages
     * @return list<array<string, mixed>>
     */
    private function serializeMessages(ChatChannel $channel, $messages, int $viewerId, bool $withReceipts = true): array
    {
        $receipts = [];
        if ($withReceipts) {
            $ownIds = [];
            foreach ($messages as $message) {
                if ($message->user_id && (int) $message->user_id === $viewerId) {
                    $ownIds[] = (int) $message->id;
                }
            }
            $receipts = ChatWorkspace::receiptsFor($channel, $ownIds, $viewerId);
        }

        $this->attachExtras($messages, $viewerId);

        return collect($messages)
            ->map(function (ChatMessage $message) use ($receipts, $viewerId, $withReceipts) {
                $receipt = null;
                if ($message->user_id && (int) $message->user_id === $viewerId) {
                    $receipt = $withReceipts
                        ? ($receipts[(int) $message->id] ?? ['seen' => false, 'seen_by' => [], 'seen_label' => 'Sent'])
                        : ['seen' => false, 'seen_by' => [], 'seen_label' => 'Sent'];
                }

                return ChatWorkspace::serializeMessage($message, $receipt);
            })
            ->values()
            ->all();
    }

    private function releaseSessionLock(): void
    {
        if (! app()->bound('session') || ! session()->isStarted()) {
            return;
        }

        session()->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function channelPayload(ChatChannel $channel, User $user): array
    {
        $name = $this->displayName($channel, $user);
        $peerId = null;
        $presence = null;
        if ($channel->isDm() && $channel->dm_key) {
            [$left, $right] = array_map('intval', explode(':', (string) $channel->dm_key, 2) + [0, 0]);
            $peerId = $left === (int) $user->id ? $right : $left;
            $presence = ChatPresence::forUserId($peerId);
        }

        $member = ChatChannelMember::query()
            ->where('channel_id', $channel->id)
            ->where('user_id', $user->id)
            ->first();

        return [
            'id' => (int) $channel->id,
            'type' => $channel->type,
            'name' => $name,
            'topic' => $channel->topic,
            'peer_id' => $peerId,
            'online' => (bool) ($presence['online'] ?? false),
            'status' => $presence['status'] ?? 'active',
            'last_seen_label' => $presence['last_seen_label'] ?? ($channel->isGroup() ? $channel->members()->count().' members' : null),
            'member_count' => (int) $channel->members()->count(),
            'last_read_message_id' => (int) ($member->last_read_message_id ?? 0),
            'notify_pref' => $member->notify_pref ?? 'all',
            'can_manage_members' => ChatWorkspace::canManageMembers($user, $channel),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ChatMessage>|\Illuminate\Database\Eloquent\Collection<int, ChatMessage>|iterable<int, ChatMessage>  $messages
     */
    private function attachExtras($messages, int $viewerId): void
    {
        $ids = collect($messages)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            return;
        }

        $counts = [];
        if (Schema::hasColumn('chat_messages', 'parent_id')) {
            $counts = ChatMessage::query()
                ->selectRaw('parent_id, COUNT(*) as c')
                ->whereIn('parent_id', $ids)
                ->groupBy('parent_id')
                ->pluck('c', 'parent_id')
                ->all();
        }

        $rx = collect();
        if (Schema::hasTable('chat_reactions')) {
            $rx = ChatReaction::query()->whereIn('message_id', $ids)->get()->groupBy('message_id');
        }
        $bookmarks = collect();
        if (Schema::hasTable('chat_bookmarks')) {
            $bookmarks = ChatBookmark::query()
                ->where('user_id', $viewerId)
                ->whereIn('message_id', $ids)
                ->pluck('message_id');
        }

        foreach ($messages as $message) {
            $message->reply_count = (int) ($counts[$message->id] ?? 0);
            $summary = [];
            foreach ($rx->get($message->id, collect()) as $reaction) {
                $summary[$reaction->emoji] = ($summary[$reaction->emoji] ?? 0) + 1;
            }
            $message->reaction_summary = collect($summary)
                ->map(fn ($count, $emoji) => ['emoji' => $emoji, 'count' => $count])
                ->values()
                ->all();
            $message->bookmarked = $bookmarks->contains($message->id);
        }
    }

    private function displayName(ChatChannel $channel, User $user): string
    {
        if ($channel->isBotInbox()) {
            return ChatWorkspace::BOT_NAME;
        }

        if ($channel->isDm() && $channel->dm_key) {
            [$left, $right] = array_map('intval', explode(':', (string) $channel->dm_key, 2) + [0, 0]);
            $peerId = $left === (int) $user->id ? $right : $left;
            $peer = User::query()->find($peerId);

            return $peer?->name ?: (string) $channel->name;
        }

        return (string) $channel->name;
    }

    private function forgetPeerUnread(ChatChannel $channel, int $authorId): void
    {
        $peerIds = ChatChannelMember::query()
            ->where('channel_id', $channel->id)
            ->where('user_id', '!=', $authorId)
            ->pluck('user_id');

        foreach ($peerIds as $peerId) {
            ChatWorkspace::forgetUnreadCache((int) $peerId);
        }
    }
}
