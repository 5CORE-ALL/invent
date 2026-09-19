<?php

namespace App\Http\Controllers;

use App\Models\ChatChannel;
use App\Models\ChatChannelMember;
use App\Models\ChatMessage;
use App\Models\User;
use App\Support\ChatWorkspace;
use App\Support\InventChatBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        }

        return view('chat.index', [
            'canManageChannels' => ChatWorkspace::canManageChannels($user),
            'directory' => ChatWorkspace::tablesReady() ? ChatWorkspace::directory($user) : [],
        ]);
    }

    public function inbox(): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        if (! ChatWorkspace::tablesReady()) {
            return response()->json(['channels' => [], 'unread' => 0]);
        }

        $channels = ChatWorkspace::inboxFor($user);

        return response()->json([
            'channels' => $channels,
            'unread' => (int) array_sum(array_column($channels, 'unread')),
        ]);
    }

    public function unread(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'unread' => ChatWorkspace::unreadTotal($user),
        ]);
    }

    public function messages(Request $request, int $channel): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $row = ChatWorkspace::memberOrFail($user, $channel);

        $after = (int) $request->query('after', 0);
        $query = ChatMessage::query()
            ->with('user:id,name,email,avatar')
            ->where('channel_id', $row->id)
            ->orderBy('id');

        if ($after > 0) {
            $query->where('id', '>', $after);
            $messages = $query->limit(200)->get();
        } else {
            $messages = $query->latest('id')->limit(80)->get()->reverse()->values();
        }

        ChatWorkspace::markRead($user, $row, (int) ($messages->max('id') ?: 0));

        return response()->json([
            'channel' => [
                'id' => (int) $row->id,
                'type' => $row->type,
                'name' => $this->displayName($row, $user),
                'topic' => $row->topic,
            ],
            'messages' => $messages->map(fn (ChatMessage $m) => ChatWorkspace::serializeMessage($m))->values(),
        ]);
    }

    public function storeMessage(Request $request, int $channel): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $row = ChatWorkspace::memberOrFail($user, $channel);

        $validated = $request->validate([
            'body' => 'nullable|string|max:8000',
            'file' => 'nullable|file|max:10240',
        ]);

        $body = trim((string) ($validated['body'] ?? ''));
        $file = $request->file('file');

        if ($body === '' && ! $file) {
            return response()->json(['message' => 'Type a message or attach a file.'], 422);
        }

        $path = null;
        $orig = null;
        if ($file) {
            $orig = $file->getClientOriginalName();
            $safe = Str::limit(Str::slug(pathinfo($orig, PATHINFO_FILENAME)) ?: 'file', 80, '');
            $ext = strtolower((string) $file->getClientOriginalExtension());
            $stored = $safe.'_'.Str::lower(Str::random(8)).($ext !== '' ? '.'.$ext : '');
            $path = $file->storeAs('chat/'.$row->id, $stored, 'local');
        }

        $message = ChatMessage::query()->create([
            'channel_id' => $row->id,
            'user_id' => $user->id,
            'is_bot' => false,
            'body' => $body !== '' ? $body : null,
            'attachment_path' => $path,
            'attachment_name' => $orig,
            'mentions' => ChatWorkspace::mentionIds($body),
        ]);
        $message->setRelation('user', $user);

        ChatWorkspace::markRead($user, $row, (int) $message->id);
        ChatWorkspace::forgetUnreadCache((int) $user->id);

        $payload = [ChatWorkspace::serializeMessage($message)];

        if ($body !== '' && InventChatBot::looksLikeCommand($body)) {
            $bot = InventChatBot::reply($user, $row, $body);
            if ($bot) {
                $payload[] = ChatWorkspace::serializeMessage($bot);
                ChatWorkspace::markRead($user, $row, (int) $bot->id);
            }
        }

        $this->forgetPeerUnread($row, (int) $user->id);

        return response()->json(['messages' => $payload]);
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

    public function file(int $message): StreamedResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $row = ChatMessage::query()->findOrFail($message);
        ChatWorkspace::memberOrFail($user, (int) $row->channel_id);

        abort_unless($row->attachment_path && Storage::disk('local')->exists($row->attachment_path), 404);

        return Storage::disk('local')->response(
            $row->attachment_path,
            $row->attachment_name ?: basename($row->attachment_path)
        );
    }

    private function displayName(ChatChannel $channel, User $user): string
    {
        if ($channel->isBotInbox()) {
            return 'Invent Bot';
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
