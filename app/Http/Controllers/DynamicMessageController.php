<?php

namespace App\Http\Controllers;

use App\Models\DynamicMessage;
use App\Models\DynamicMessageReply;
use App\Models\DynamicMessageRule;
use App\Models\User;
use App\Services\DynamicMessages\DynamicMessageEngine;
use App\Services\DynamicMessages\DynamicMessageEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DynamicMessageController extends Controller
{
    public function __construct(private readonly DynamicMessageEngine $engine) {}

    // ------------------------------------------------------------ access

    public static function canManage(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user !== null
            && strtolower(trim((string) $user->email)) === strtolower(trim((string) config('dynamic_messages.editor_email')));
    }

    /** Amarjit (the reply target) or an editor can read replies. */
    public static function canSeeReplies(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user !== null && (
            self::canManage($user)
            || strtolower(trim((string) $user->email)) === strtolower(trim((string) config('dynamic_messages.reply_to_email')))
        );
    }

    private function denyUnlessManager(): ?JsonResponse
    {
        return self::canManage()
            ? null
            : response()->json(['message' => 'Only '.config('dynamic_messages.editor_email').' can manage message rules.'], 403);
    }

    // ------------------------------------------------------------ page

    public function index(): View
    {
        $events = [];
        foreach (DynamicMessageEvents::definitions() as $key => $def) {
            $events[] = [
                'key' => $key,
                'page' => $def['page'],
                'label' => $def['label'],
                'scope' => $def['scope'],
                'manual' => ! empty($def['manual']),
                'metrics' => $def['metrics'],
                'placeholders' => DynamicMessageEvents::placeholdersFor($key),
            ];
        }

        $users = self::canManage()
            ? User::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
            : collect();

        return view('dynamic-messages.index', [
            'canManage' => self::canManage(),
            'canSeeReplies' => self::canSeeReplies(),
            'events' => $events,
            'users' => $users,
            'operators' => DynamicMessageRule::OPERATORS,
            'frequencies' => DynamicMessageRule::FREQUENCIES,
            'replyLabel' => config('dynamic_messages.reply_to_label'),
        ]);
    }

    // ------------------------------------------------------------ rules (president only)

    public function rules(): JsonResponse
    {
        if ($deny = $this->denyUnlessManager()) {
            return $deny;
        }

        $rules = DynamicMessageRule::query()->withCount([
            'messages',
            'messages as acknowledged_count' => fn ($q) => $q->whereNotNull('acknowledged_at'),
        ])->orderByDesc('id')->get();

        $userNames = User::withTrashed()->whereIn('id', $rules->flatMap->recipientIds()->unique()->all())->pluck('name', 'id');

        return response()->json(['data' => $rules->map(function (DynamicMessageRule $r) use ($userNames) {
            $def = DynamicMessageEvents::definition($r->event_key);

            return [
                'id' => $r->id,
                'name' => $r->name,
                'event_key' => $r->event_key,
                'event_label' => $def['label'] ?? $r->event_key,
                'page' => $def['page'] ?? '-',
                'metric' => $r->metric,
                'metric_label' => $def['metrics'][$r->metric] ?? null,
                'operator' => $r->operator,
                'threshold' => $r->threshold,
                'title' => $r->title,
                'body' => $r->body,
                'recipient_user_ids' => $r->recipientIds(),
                'recipient_names' => collect($r->recipientIds())->map(fn ($id) => $userNames[$id] ?? ('#'.$id))->values(),
                'frequency' => $r->frequency,
                'allow_reply' => $r->allow_reply,
                'is_active' => $r->is_active,
                'manual' => ! empty($def['manual']),
                'last_run_at' => $r->last_run_at?->timezone(config('dynamic_messages.timezone'))->format('d M Y H:i'),
                'messages_count' => $r->messages_count,
                'acknowledged_count' => $r->acknowledged_count,
            ];
        })->values()]);
    }

    public function storeRule(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessManager()) {
            return $deny;
        }
        $rule = new DynamicMessageRule;
        $rule->created_by = auth()->id();

        return $this->saveRule($request, $rule);
    }

    public function updateRule(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessManager()) {
            return $deny;
        }

        return $this->saveRule($request, DynamicMessageRule::query()->findOrFail($id));
    }

    private function saveRule(Request $request, DynamicMessageRule $rule): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'event_key' => ['required', 'string', Rule::in(array_keys(DynamicMessageEvents::definitions()))],
            'metric' => ['nullable', 'string', 'max:80'],
            'operator' => ['nullable', Rule::in(DynamicMessageRule::OPERATORS)],
            'threshold' => ['nullable', 'numeric'],
            'title' => ['required', 'string', 'max:190'],
            'body' => ['required', 'string', 'max:3000'],
            'recipient_user_ids' => ['required', 'array', 'min:1'],
            'recipient_user_ids.*' => ['integer', Rule::exists('users', 'id')],
            'frequency' => ['required', Rule::in(array_keys(DynamicMessageRule::FREQUENCIES))],
            'allow_reply' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $def = DynamicMessageEvents::definition($data['event_key']);
        $metric = $data['metric'] ?? null;
        if ($metric === '') {
            $metric = null;
        }
        if (! empty($def['manual'])) {
            $metric = null; // manual messages have no data condition
        }
        if ($metric !== null) {
            if (! array_key_exists($metric, $def['metrics'])) {
                return response()->json(['message' => 'The selected metric does not belong to this page event.', 'errors' => ['metric' => ['Invalid metric.']]], 422);
            }
            if (empty($data['operator']) || ! isset($data['threshold'])) {
                return response()->json(['message' => 'Choose an operator and a value for the condition.', 'errors' => ['threshold' => ['Required.']]], 422);
            }
        }

        $rule->fill([
            'name' => $data['name'],
            'event_key' => $data['event_key'],
            'metric' => $metric,
            'operator' => $metric !== null ? $data['operator'] : null,
            'threshold' => $metric !== null ? $data['threshold'] : null,
            'title' => $data['title'],
            'body' => $data['body'],
            'recipient_user_ids' => array_values(array_unique(array_map('intval', $data['recipient_user_ids']))),
            'frequency' => $data['frequency'],
            'allow_reply' => (bool) ($data['allow_reply'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'updated_by' => auth()->id(),
        ])->save();

        return response()->json(['success' => true, 'id' => $rule->id]);
    }

    public function toggleRule(int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessManager()) {
            return $deny;
        }
        $rule = DynamicMessageRule::query()->findOrFail($id);
        $rule->update(['is_active' => ! $rule->is_active, 'updated_by' => auth()->id()]);

        return response()->json(['success' => true, 'is_active' => $rule->is_active]);
    }

    public function destroyRule(int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessManager()) {
            return $deny;
        }
        DynamicMessageRule::query()->findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    /** Dry run: who would receive what right now (nothing is sent). */
    public function previewRule(int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessManager()) {
            return $deny;
        }
        $rule = DynamicMessageRule::query()->findOrFail($id);
        $def = DynamicMessageEvents::definition($rule->event_key);

        if (! empty($def['manual'])) {
            $recipients = $this->engine->recipients($rule);
            $matches = $recipients->map(fn (User $u) => [
                'user_id' => $u->id,
                'name' => $u->name,
                'title' => $this->engine->render($rule->title, $u, []),
                'body' => $this->engine->render($rule->body, $u, []),
                'duplicate' => false,
            ])->values()->all();

            return response()->json(['matches' => $matches, 'no_match' => 0, 'skipped_duplicates' => 0]);
        }

        return response()->json($this->engine->runRule($rule, true));
    }

    /** Evaluate immediately (scheduler also does this every 30 minutes). */
    public function runRule(int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessManager()) {
            return $deny;
        }
        $rule = DynamicMessageRule::query()->findOrFail($id);
        $def = DynamicMessageEvents::definition($rule->event_key);

        $result = ! empty($def['manual'])
            ? $this->engine->sendManualNow($rule)
            : $this->engine->runRule($rule);

        return response()->json([
            'success' => true,
            'created' => $result['created'],
            'skipped_duplicates' => $result['skipped_duplicates'],
            'no_match' => $result['no_match'],
        ]);
    }

    /** Delivery / acknowledgement log (president only). */
    public function log(): JsonResponse
    {
        if ($deny = $this->denyUnlessManager()) {
            return $deny;
        }

        $rows = DynamicMessage::query()
            ->with(['recipient:id,name', 'reply', 'rule:id,name'])
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->map(fn (DynamicMessage $m) => [
                'id' => $m->id,
                'rule' => $m->rule?->name ?? '(deleted rule)',
                'recipient' => $m->recipient?->name ?? ('#'.$m->recipient_user_id),
                'title' => $m->title,
                'body' => $m->body,
                'sent_at' => $this->fmt($m->created_at),
                'acknowledged_at' => $this->fmt($m->acknowledged_at),
                'allow_reply' => $m->allow_reply,
                'reply' => $m->reply?->body,
            ]);

        return response()->json(['data' => $rows]);
    }

    // ------------------------------------------------------------ replies (Amarjit)

    public function replies(): JsonResponse
    {
        if (! self::canSeeReplies()) {
            return response()->json(['message' => 'Not allowed.'], 403);
        }

        $rows = DynamicMessageReply::query()
            ->with(['user:id,name', 'message:id,title,body,created_at'])
            ->orderByRaw('seen_at is null desc')
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->map(fn (DynamicMessageReply $r) => [
                'id' => $r->id,
                'from' => $r->user?->name ?? ('#'.$r->user_id),
                'message_title' => $r->message?->title,
                'message_body' => $r->message?->body,
                'reply' => $r->body,
                'replied_at' => $this->fmt($r->created_at),
                'seen' => $r->seen_at !== null,
            ]);

        return response()->json([
            'data' => $rows,
            'unseen_count' => $rows->where('seen', false)->count(),
        ]);
    }

    public function markReplySeen(int $id): JsonResponse
    {
        if (! self::canSeeReplies()) {
            return response()->json(['message' => 'Not allowed.'], 403);
        }
        DynamicMessageReply::query()->whereKey($id)->whereNull('seen_at')->update(['seen_at' => now()]);

        return response()->json(['success' => true]);
    }

    // ------------------------------------------------------------ recipient side

    public function mine(): JsonResponse
    {
        $rows = DynamicMessage::query()
            ->with('reply')
            ->where('recipient_user_id', auth()->id())
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (DynamicMessage $m) => $this->serializeMessage($m) + [
                'sent_at' => $this->fmt($m->created_at),
                'acknowledged_at' => $this->fmt($m->acknowledged_at),
                'reply' => $m->reply?->body,
            ]);

        return response()->json(['data' => $rows]);
    }

    public function pending(): JsonResponse
    {
        $rows = DynamicMessage::query()
            ->where('recipient_user_id', auth()->id())
            ->whereNull('acknowledged_at')
            ->orderBy('id')
            ->limit(25)
            ->get()
            ->map(fn (DynamicMessage $m) => $this->serializeMessage($m) + ['sent_at' => $this->fmt($m->created_at)]);

        return response()->json(['data' => $rows]);
    }

    /**
     * Mandatory check-box ("confirm" must be true) + optional reply to Amarjit.
     */
    public function acknowledge(Request $request, int $id): JsonResponse
    {
        $message = DynamicMessage::query()
            ->where('recipient_user_id', auth()->id())
            ->findOrFail($id);

        $data = $request->validate([
            'confirm' => ['accepted'],
            'reply' => ['nullable', 'string', 'max:2000'],
        ], [
            'confirm.accepted' => 'Please tick the check-box to confirm you have read this message.',
        ]);

        $reply = trim((string) ($data['reply'] ?? ''));
        if ($reply !== '' && ! $message->allow_reply) {
            return response()->json(['message' => 'Replies are not enabled for this message.'], 422);
        }

        if ($message->acknowledged_at === null) {
            $message->forceFill(['acknowledged_at' => now()])->save();
        }
        if ($reply !== '') {
            DynamicMessageReply::query()->firstOrCreate(
                ['message_id' => $message->id],
                ['user_id' => auth()->id(), 'body' => $reply]
            );
        }

        return response()->json(['success' => true]);
    }

    // ------------------------------------------------------------ helpers

    private function serializeMessage(DynamicMessage $m): array
    {
        return [
            'id' => $m->id,
            'title' => $m->title,
            'body' => $m->body,
            'page' => $m->page_label,
            'allow_reply' => $m->allow_reply,
            'reply_label' => config('dynamic_messages.reply_to_label'),
        ];
    }

    private function fmt($date): ?string
    {
        return $date?->copy()->timezone(config('dynamic_messages.timezone'))->format('d M Y H:i');
    }
}
