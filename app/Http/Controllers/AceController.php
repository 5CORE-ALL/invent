<?php

namespace App\Http\Controllers;

use App\Models\AutoChatEvent;
use App\Models\AutoChatEventRun;
use App\Models\ChatChannel;
use App\Models\User;
use App\Support\Ace\AceEngine;
use App\Support\Ace\AcePages;
use App\Support\SuperAdminAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * ACE (Automated Chat Event) rules: create / list / pause / run / delete.
 * Everyone logged in can view the rules; managing them is limited to task admins.
 */
class AceController extends Controller
{
    private function canManage(): bool
    {
        return SuperAdminAccess::isTaskAdmin(auth()->user());
    }

    private function notReady(): ?JsonResponse
    {
        if (! AceEngine::ready()) {
            return response()->json(['ok' => false, 'message' => 'ACE tables are missing. Run php artisan migrate.'], 503);
        }

        return null;
    }

    /**
     * Form options: columns for the page, operators, chat channels, users, placeholders.
     */
    public function meta(Request $request): JsonResponse
    {
        $page = (string) $request->query('page', '');
        abort_unless(isset(AcePages::PAGES[$page]), 422, 'Unknown page.');

        $columns = AcePages::columns($page);
        $placeholders = array_map(fn ($c) => $c['key'], $columns);
        $placeholders = array_merge($placeholders, ['row', 'message', 'channel', 'sender', 'date']);

        $channels = [];
        if (Schema::hasTable('chat_channels')) {
            $channels = ChatChannel::query()
                ->where('is_archived', false)
                ->whereNotNull('name')->where('name', '!=', '')
                ->orderBy('name')->limit(300)->get(['id', 'name', 'type'])
                ->map(fn ($c) => ['id' => (int) $c->id, 'name' => (string) $c->name, 'type' => (string) $c->type])->all();
        }

        return response()->json([
            'ok' => true,
            'can_manage' => $this->canManage(),
            'page_label' => AcePages::PAGES[$page],
            'columns' => $columns,
            'operators' => collect(AceEngine::OPERATORS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
            'channels' => $channels,
            'users' => User::query()->active()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($u) => ['id' => (int) $u->id, 'name' => (string) $u->name])->all(),
            'placeholders' => $placeholders,
        ]);
    }

    /**
     * ACE details for a page: the rules, their status and what they have created.
     */
    public function index(Request $request): JsonResponse
    {
        if ($resp = $this->notReady()) {
            return $resp;
        }
        $page = (string) $request->query('page', '');
        abort_unless(isset(AcePages::PAGES[$page]), 422, 'Unknown page.');

        $columns = collect(AcePages::columns($page))->keyBy('key');
        $ops = AceEngine::OPERATORS;
        $users = User::query()->pluck('name', 'id');
        $channels = Schema::hasTable('chat_channels') ? ChatChannel::query()->pluck('name', 'id') : collect();

        $rules = AutoChatEvent::query()->where('page', $page)->orderByDesc('is_active')->orderByDesc('id')->get();
        $runs = AutoChatEventRun::query()->whereIn('ace_id', $rules->pluck('id'))->orderByDesc('id')->get()->groupBy('ace_id');

        $data = $rules->map(function (AutoChatEvent $r) use ($columns, $ops, $users, $channels, $runs) {
            $list = $runs->get($r->id, collect());

            $condition = '';
            if ($r->column_key) {
                $col = $columns->get($r->column_key)['label'] ?? $r->column_key;
                $op = $ops[$r->operator] ?? $r->operator;
                $condition = $col.' '.$op.(in_array($r->operator, ['empty', 'not_empty'], true) ? '' : ' “'.$r->value.'”');
            }
            $chat = '';
            if ($r->chat_keyword) {
                $chat = 'Chat '.($r->chat_channel_id ? '#'.($channels[$r->chat_channel_id] ?? $r->chat_channel_id) : '(any channel)').' contains “'.$r->chat_keyword.'”';
            }
            $assignee = match ($r->assignee_mode) {
                'row_user' => 'the person in the row',
                'chat_sender' => 'the chat message sender',
                default => (string) ($users[$r->assignee_id] ?? '—'),
            };

            return [
                'id' => (int) $r->id,
                'name' => $r->name,
                'trigger_type' => $r->trigger_type,
                'condition' => $condition,
                'chat' => $chat,
                'task_title' => $r->task_title,
                'priority' => $r->priority,
                'due_in_days' => (int) $r->due_in_days,
                'assignee' => $assignee,
                'is_active' => (bool) $r->is_active,
                'created_by' => (string) ($users[$r->created_by] ?? ''),
                'created_at' => optional($r->created_at)->format('d M Y'),
                'last_run_at' => optional($r->last_run_at)->format('d M Y H:i'),
                'tasks_created' => $list->where('status', 'created')->whereNotNull('task_id')->count(),
                'failed' => $list->where('status', 'failed')->count(),
                'recent' => $list->take(5)->map(fn ($x) => [
                    'label' => (string) $x->subject_label,
                    'task_id' => $x->task_id ? (int) $x->task_id : null,
                    'status' => $x->status,
                    'error' => $x->error,
                    'at' => optional($x->created_at)->format('d M H:i'),
                ])->values()->all(),
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'can_manage' => $this->canManage(),
            'rules' => $data,
        ]);
    }

    /**
     * How many rows a column condition matches right now (no tasks created).
     */
    public function preview(Request $request): JsonResponse
    {
        $page = (string) $request->input('page', '');
        abort_unless(isset(AcePages::PAGES[$page]), 422, 'Unknown page.');

        $key = (string) $request->input('column_key', '');
        $rows = AcePages::rows($page);
        $hit = array_values(array_filter($rows, fn ($r) => AceEngine::matches(
            $r,
            $key !== '' ? $key : null,
            (string) $request->input('operator', ''),
            (string) $request->input('value', '')
        )));

        return response()->json([
            'ok' => true,
            'total' => count($rows),
            'matches' => count($hit),
            'sample' => array_map(fn ($r) => (string) $r['label'], array_slice($hit, 0, 8)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($this->canManage(), 403, 'Only task admins can create ACE rules.');
        if ($resp = $this->notReady()) {
            return $resp;
        }

        $page = (string) $request->input('page', '');
        abort_unless(isset(AcePages::PAGES[$page]), 422, 'Unknown page.');
        $columnKeys = array_column(AcePages::columns($page), 'key');

        $v = $request->validate([
            'name' => 'required|string|max:120',
            'page' => 'required|string',
            'column_key' => ['nullable', 'string', Rule::in($columnKeys)],
            'operator' => ['nullable', 'string', Rule::in(array_keys(AceEngine::OPERATORS))],
            'value' => 'nullable|string|max:500',
            'chat_channel_id' => 'nullable|integer|exists:chat_channels,id',
            'chat_keyword' => 'nullable|string|max:255',
            'task_title' => 'required|string|max:1000',
            'task_description' => 'nullable|string|max:5000',
            'task_group' => 'nullable|string|max:255',
            'priority' => 'required|in:low,normal,high',
            'due_in_days' => 'required|integer|min:0|max:365',
            'assignee_mode' => 'required|in:fixed,row_user,chat_sender',
            'assignee_id' => 'nullable|integer|exists:users,id',
        ]);

        $hasColumn = ! empty($v['column_key']);
        $keyword = trim((string) ($v['chat_keyword'] ?? ''));
        $hasChat = $keyword !== '';

        if (! $hasColumn && ! $hasChat) {
            return response()->json(['ok' => false, 'message' => 'Pick a column condition, a chat keyword, or both.'], 422);
        }
        if ($hasColumn && empty($v['operator'])) {
            return response()->json(['ok' => false, 'message' => 'Choose an operator for the column condition.'], 422);
        }
        if ($hasColumn && ! in_array($v['operator'], ['empty', 'not_empty'], true) && trim((string) ($v['value'] ?? '')) === '') {
            return response()->json(['ok' => false, 'message' => 'Enter the value to compare the column with.'], 422);
        }
        if ($v['assignee_mode'] === 'fixed' && empty($v['assignee_id'])) {
            return response()->json(['ok' => false, 'message' => 'Choose who the task is assigned to.'], 422);
        }
        if ($v['assignee_mode'] === 'row_user' && $page !== 'users') {
            return response()->json(['ok' => false, 'message' => '"The person in the row" only works on the Users page.'], 422);
        }
        if ($v['assignee_mode'] === 'chat_sender' && ! $hasChat) {
            return response()->json(['ok' => false, 'message' => '"The chat message sender" needs a chat keyword trigger.'], 422);
        }

        $trigger = $hasColumn && $hasChat ? 'chat_column' : ($hasChat ? 'chat' : 'column');

        $ace = AutoChatEvent::query()->create([
            'name' => $v['name'],
            'page' => $page,
            'trigger_type' => $trigger,
            'column_key' => $hasColumn ? $v['column_key'] : null,
            'operator' => $hasColumn ? $v['operator'] : null,
            'value' => $hasColumn ? ($v['value'] ?? null) : null,
            'chat_channel_id' => $hasChat ? ($v['chat_channel_id'] ?? null) : null,
            'chat_keyword' => $hasChat ? $keyword : null,
            'task_title' => $v['task_title'],
            'task_description' => $v['task_description'] ?? null,
            'task_group' => $v['task_group'] ?? null,
            'priority' => $v['priority'],
            'due_in_days' => $v['due_in_days'],
            'assignee_mode' => $v['assignee_mode'],
            'assignee_id' => $v['assignee_mode'] === 'fixed' ? $v['assignee_id'] : null,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        return response()->json(['ok' => true, 'id' => (int) $ace->id]);
    }

    public function toggle(int $id): JsonResponse
    {
        abort_unless($this->canManage(), 403, 'Only task admins can change ACE rules.');
        $ace = AutoChatEvent::query()->findOrFail($id);
        $ace->is_active = ! $ace->is_active;
        $ace->save();

        return response()->json(['ok' => true, 'is_active' => (bool) $ace->is_active]);
    }

    public function runNow(int $id): JsonResponse
    {
        abort_unless($this->canManage(), 403, 'Only task admins can run ACE rules.');
        $ace = AutoChatEvent::query()->findOrFail($id);
        if ($ace->trigger_type !== 'column') {
            return response()->json(['ok' => false, 'message' => 'Chat-triggered rules fire when a matching message is sent.'], 422);
        }

        return response()->json(['ok' => true, 'created' => AceEngine::runNow($ace)]);
    }

    public function destroy(int $id): JsonResponse
    {
        abort_unless($this->canManage(), 403, 'Only task admins can delete ACE rules.');
        $ace = AutoChatEvent::query()->findOrFail($id);
        AutoChatEventRun::query()->where('ace_id', $ace->id)->delete();
        $ace->delete();

        return response()->json(['ok' => true]);
    }
}
