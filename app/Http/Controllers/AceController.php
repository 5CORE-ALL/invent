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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * ATE (Auto Task Event) rules: create / list / edit / pause / run / delete.
 *
 * Everyone logged in can create rules and view ALL rules of a page; editing, pausing, running
 * and deleting is limited to the rule's owner (the user who created it).
 */
class AceController extends Controller
{
    private function isAdmin(): bool
    {
        return SuperAdminAccess::isTaskAdmin(auth()->user());
    }

    private function notReady(): ?JsonResponse
    {
        if (! AceEngine::ready()) {
            return response()->json(['ok' => false, 'message' => 'Auto Task Event tables are missing. Run php artisan migrate.'], 503);
        }

        return null;
    }

    private function ownRule(int $id): AutoChatEvent
    {
        $ace = AutoChatEvent::query()->findOrFail($id);
        abort_unless((int) $ace->created_by === (int) auth()->id(), 403, 'You can only change your own Auto Task Events.');

        return $ace;
    }

    /**
     * Resolve + validate the page a request is about.
     *
     * @return array{0: string, 1: ?string}  [page key, data source path (dynamic pages only)]
     */
    private function resolvePage(Request $request, bool $needSource): array
    {
        $page = (string) ($request->input('page') ?? $request->query('page', ''));
        if (AcePages::isStatic($page)) {
            return [$page, null];
        }
        abort_unless(AcePages::isDynamicKey($page), 422, 'Unknown page.');

        $source = null;
        if ($needSource) {
            $source = (string) ($request->input('source') ?? $request->query('source', ''));
            abort_unless(AcePages::validSource($source), 422, 'This page\'s data table could not be found, so Auto Task Events cannot read it.');
        }

        return [$page, $source];
    }

    /**
     * Form options: columns for the page, operators, users, who the current user is.
     */
    public function meta(Request $request): JsonResponse
    {
        [$page, $source] = $this->resolvePage($request, true);

        try {
            $columns = AcePages::columns($page, $source, (int) auth()->id());
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => 'Could not read this page\'s data.'], 422);
        }

        $placeholders = array_map(fn ($c) => $c['key'], $columns);
        $placeholders = array_merge($placeholders, ['row', 'message', 'channel', 'sender', 'date']);

        return response()->json([
            'ok' => true,
            'is_admin' => $this->isAdmin(),
            'page_label' => AcePages::PAGES[$page] ?? null,
            'columns' => $columns,
            'operators' => collect(AceEngine::OPERATORS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
            'users' => User::query()->active()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($u) => ['id' => (int) $u->id, 'name' => (string) $u->name])->all(),
            'placeholders' => $placeholders,
            'me' => (int) auth()->id(),
        ]);
    }

    /**
     * Auto Task Event details for a page: ALL rules (everyone can view), their status and what
     * they have created. `mine` marks the ones the current user may edit / pause / run / delete.
     */
    public function index(Request $request): JsonResponse
    {
        if ($resp = $this->notReady()) {
            return $resp;
        }
        [$page] = $this->resolvePage($request, false);

        $columns = AcePages::isStatic($page) ? collect(AcePages::columns($page))->keyBy('key') : collect();
        $ops = AceEngine::OPERATORS;
        $users = User::query()->pluck('name', 'id');
        $me = (int) auth()->id();

        $rules = AutoChatEvent::query()->where('page', $page)->orderByDesc('is_active')->orderByDesc('id')->get();
        $runs = AutoChatEventRun::query()->whereIn('ace_id', $rules->pluck('id'))->orderByDesc('id')->get()->groupBy('ace_id');

        $data = $rules->map(function (AutoChatEvent $r) use ($columns, $ops, $users, $runs, $me) {
            $list = $runs->get($r->id, collect());
            $mine = (int) $r->created_by === $me;
            $conds = AceEngine::conditionsOf($r);

            $parts = array_map(function ($c) use ($columns, $ops) {
                $col = $c['label'] ?? ($columns->get($c['column_key'])['label'] ?? $c['column_key']);
                $op = $ops[$c['operator']] ?? $c['operator'];

                return $col.' '.$op.(in_array($c['operator'], ['empty', 'not_empty'], true) ? '' : ' “'.$c['value'].'”');
            }, $conds);
            $condition = implode($r->match_mode === 'any' ? '  OR  ' : '  AND  ', $parts);

            $opt = is_array($r->task_options ?? null) ? $r->task_options : [];
            $assignee = match ($r->assignee_mode) {
                'row_user' => 'the person in the row',
                'chat_sender' => 'the chat message sender',
                default => ! empty($opt['assignee_ids'])
                    ? implode(', ', array_map(fn ($id) => (string) ($users[$id] ?? $id), $opt['assignee_ids'])).(! empty($opt['split']) ? ' (split)' : (count($opt['assignee_ids']) > 1 ? ' (joint)' : ''))
                    : (string) ($users[$r->assignee_id] ?? '—'),
            };
            $taskFreq = implode(', ', array_map(
                fn ($f) => ['one_time' => 'One Time', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'][$f] ?? $f,
                $opt['task_frequency'] ?? ['one_time']
            ));
            $extras = [];
            if (count($opt['titles'] ?? []) > 1) {
                $extras[] = count($opt['titles']).' tasks';
            }
            if (! empty($opt['assignor_id'])) {
                $extras[] = 'assignor '.($users[$opt['assignor_id']] ?? $opt['assignor_id']);
            }
            $extras[] = 'ETC '.($opt['etc_minutes'] ?? 10).' min';
            if (! empty($opt['is_corrective_action'])) {
                $extras[] = 'corrective action';
            }

            return [
                'id' => (int) $r->id,
                'name' => $r->name,
                'mine' => $mine,
                'owner' => (string) ($users[$r->created_by] ?? ''),
                'condition' => $condition,
                'frequency' => $r->frequencies
                    ? implode(', ', array_map(fn ($f) => AceEngine::FREQUENCIES[$f] ?? $f, AceEngine::frequenciesOf($r)))
                    : 'Once per row',
                'task_title' => $r->task_title,
                'task_group' => $r->task_group,
                'task_link' => $r->task_link ?? null,
                'priority' => $r->priority,
                'due_in_days' => (int) $r->due_in_days,
                'assignee' => $assignee,
                'task_frequency' => $taskFreq,
                'task_extras' => implode(' · ', $extras),
                'is_active' => (bool) $r->is_active,
                'created_by' => (string) ($users[$r->created_by] ?? ''),
                'created_at' => optional($r->created_at)->format('d M Y'),
                'last_run_at' => optional($r->last_run_at)->format('d M Y H:i'),
                'tasks_created' => $list->where('status', 'created')->count(),
                'failed' => $list->where('status', 'failed')->count(),
                'recent' => $list->where('status', '!=', 'skipped')->take(5)->map(fn ($x) => [
                    'label' => (string) $x->subject_label,
                    'task_id' => $x->task_id ? (int) $x->task_id : null,
                    'status' => $x->status,
                    'error' => $x->error,
                    'at' => optional($x->created_at)->format('d M H:i'),
                ])->values()->all(),
                // What the edit form needs (owner only).
                'form' => $mine ? [
                    'name' => $r->name,
                    'conditions' => $conds,
                    'match_mode' => $r->match_mode === 'any' ? 'any' : 'all',
                    'frequencies' => AceEngine::frequenciesOf($r),
                    'task_title' => $r->task_title,
                    'task_description' => $r->task_description,
                    'task_group' => $r->task_group,
                    'task_link' => $r->task_link ?? null,
                    'priority' => $r->priority,
                    'due_in_days' => (int) $r->due_in_days,
                    'assignee_mode' => $r->assignee_mode,
                    'assignee_id' => $r->assignee_id ? (int) $r->assignee_id : null,
                    'task_options' => $opt,
                ] : null,
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'me' => $me,
            'rules' => $data,
        ]);
    }

    /**
     * How many rows a column condition matches right now (no tasks created).
     */
    public function preview(Request $request): JsonResponse
    {
        [$page, $source] = $this->resolvePage($request, true);

        $conditions = $this->cleanConditions($request->input('conditions', []), $page, true);
        $mode = $request->input('match_mode') === 'any' ? 'any' : 'all';
        try {
            $rows = AcePages::rows($page, $source, (int) auth()->id());
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        $hit = array_values(array_filter($rows, fn ($r) => AceEngine::rowMatches($r, $conditions, $mode)));

        return response()->json([
            'ok' => true,
            'total' => count($rows),
            'matches' => count($hit),
            'sample' => array_map(fn ($r) => AcePages::rowLabel($r), array_slice($hit, 0, 8)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->save($request, null);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->save($request, $this->ownRule($id));
    }

    /**
     * Create (or, with $existing, update) a rule. Updating never changes the page, source or owner.
     */
    private function save(Request $request, ?AutoChatEvent $existing): JsonResponse
    {
        if ($resp = $this->notReady()) {
            return $resp;
        }

        if ($existing) {
            $page = (string) $existing->page;
            $source = $existing->source_url ?: null;
        } else {
            [$page, $source] = $this->resolvePage($request, true);
        }
        $dynamic = AcePages::isDynamicKey($page);
        if ($dynamic && ! $existing && ! Schema::hasColumn('auto_chat_events', 'source_url')) {
            return response()->json(['ok' => false, 'message' => 'This page needs a database update. Run php artisan migrate.'], 503);
        }

        $colRule = ['required', 'string', 'max:200'];
        if (! $dynamic) {
            $colRule[] = Rule::in(array_column(AcePages::columns($page), 'key'));
        }

        $v = $request->validate([
            'name' => 'required|string|max:120',
            'page' => 'nullable|string',
            'page_label' => 'nullable|string|max:160',
            'conditions' => 'nullable|array|max:10',
            'conditions.*.column_key' => $colRule,
            'conditions.*.label' => 'nullable|string|max:160',
            'conditions.*.operator' => ['required', 'string', Rule::in(array_keys(AceEngine::OPERATORS))],
            'conditions.*.value' => 'nullable|string|max:500',
            'match_mode' => 'nullable|in:all,any',
            'frequencies' => 'nullable|array|max:3',
            'frequencies.*' => 'string|in:daily,weekly,monthly',
            'task_title' => 'required|string|max:1000',
            'task_description' => 'nullable|string|max:5000',
            'task_group' => 'nullable|string|max:255',
            'task_link' => 'nullable|string|max:2048',
            'priority' => 'required|in:low,normal,high',
            'due_in_days' => 'required|integer|min:0|max:365',
            'assignee_mode' => 'required|in:fixed,row_user',
            'assignee_id' => 'nullable|integer|exists:users,id',
            'task_options' => 'nullable|array',
            'task_options.titles' => 'nullable|array|max:20',
            'task_options.titles.*' => 'nullable|string|max:1000',
            'task_options.assignee_ids' => 'nullable|array|max:100',
            'task_options.assignee_ids.*' => 'integer|exists:users,id',
            'task_options.assignor_id' => 'nullable|integer|exists:users,id',
            'task_options.split' => 'nullable|boolean',
            'task_options.etc_minutes' => 'nullable|integer|min:0|max:100000',
            'task_options.is_corrective_action' => 'nullable|boolean',
            'task_options.task_frequency' => 'nullable|array|max:4',
            'task_options.task_frequency.*' => 'string|in:one_time,daily,weekly,monthly',
            'task_options.l2' => 'nullable|string|max:2048',
            'task_options.training_link' => 'nullable|string|max:2048',
            'task_options.video_link' => 'nullable|string|max:2048',
            'task_options.form_link' => 'nullable|string|max:2048',
            'task_options.form_report_link' => 'nullable|string|max:2048',
            'task_options.checklist_link' => 'nullable|string|max:2048',
            'task_options.pl' => 'nullable|string|max:2048',
            'task_options.process' => 'nullable|string|max:2048',
        ]);

        // Task-manager inputs (assignees, ETC, frequency, links ...) kept as one JSON object.
        $opts = $this->cleanTaskOptions($v['task_options'] ?? []);
        // Like the task manager's own form, only task admins may pick a different assignor.
        if (! $this->isAdmin() || empty($opts['assignor_id'])) {
            $opts['assignor_id'] = (int) auth()->id();
        }
        $fixedIds = $opts['assignee_ids'] ?? [];
        if (! empty($fixedIds) && empty($v['assignee_id'])) {
            $v['assignee_id'] = $fixedIds[0];
        }

        $conditions = $this->cleanConditions($v['conditions'] ?? [], $page, $dynamic);
        foreach ($conditions as $c) {
            if (! in_array($c['operator'], ['empty', 'not_empty'], true) && trim((string) $c['value']) === '') {
                return response()->json(['ok' => false, 'message' => 'Enter a value for every condition (or use "is empty" / "is not empty").'], 422);
            }
        }
        if ($conditions === []) {
            return response()->json(['ok' => false, 'message' => 'Add at least one condition.'], 422);
        }
        foreach (['conditions' => count($conditions) > 1, 'task_options' => true, 'frequencies' => true, 'task_link' => true] as $col => $needed) {
            if ($needed && ! Schema::hasColumn('auto_chat_events', $col)) {
                return response()->json(['ok' => false, 'message' => 'Auto Task Events need a database update. Run php artisan migrate.'], 503);
            }
        }
        if ($v['assignee_mode'] === 'fixed' && empty($v['assignee_id'])) {
            return response()->json(['ok' => false, 'message' => 'Choose who the task is assigned to.'], 422);
        }
        if ($v['assignee_mode'] === 'row_user' && $page !== 'users') {
            return response()->json(['ok' => false, 'message' => '"The person in the row" only works on the Users page.'], 422);
        }

        $first = $conditions[0];
        $freqs = array_values(array_unique($v['frequencies'] ?? []));
        $attrs = [
            'name' => $v['name'],
            'trigger_type' => 'column',
            'conditions' => $conditions,
            'match_mode' => ($v['match_mode'] ?? 'all') === 'any' ? 'any' : 'all',
            'frequencies' => $freqs ?: ['daily'],
            // The first condition is mirrored into the original single-condition columns.
            'column_key' => $first['column_key'],
            'operator' => $first['operator'],
            'value' => $first['value'],
            'chat_channel_id' => null,
            'chat_keyword' => null,
            'task_title' => $v['task_title'],
            'task_description' => $v['task_description'] ?? null,
            'task_group' => $v['task_group'] ?? null,
            'task_link' => trim((string) ($v['task_link'] ?? '')) ?: null,
            'task_options' => $opts ?: null,
            'priority' => $v['priority'],
            'due_in_days' => $v['due_in_days'],
            'assignee_mode' => $v['assignee_mode'],
            'assignee_id' => $v['assignee_mode'] === 'fixed' ? $v['assignee_id'] : null,
        ];

        if ($existing) {
            $existing->fill($attrs)->save();
            Cache::forget('ate:checked:'.$existing->id); // re-check soon with the new conditions
            $ace = $existing;
        } else {
            $attrs += ['page' => $page, 'is_active' => true, 'created_by' => auth()->id()];
            if ($dynamic) {
                $attrs['source_url'] = $source;
                $attrs['page_label'] = isset($v['page_label']) ? trim((string) $v['page_label']) ?: null : null;
            }
            $ace = AutoChatEvent::query()->create($attrs);
        }

        return response()->json(['ok' => true, 'id' => (int) $ace->id]);
    }

    /**
     * Keep only known task-option keys, trimmed. Returns [] when nothing is set.
     *
     * @param  mixed  $raw
     * @return array<string, mixed>
     */
    private function cleanTaskOptions($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        $titles = array_values(array_filter(array_map(fn ($t) => trim((string) $t), (array) ($raw['titles'] ?? [])), fn ($t) => $t !== ''));
        if ($titles !== []) {
            $out['titles'] = $titles;
        }
        $ids = array_values(array_unique(array_map('intval', (array) ($raw['assignee_ids'] ?? []))));
        if ($ids !== []) {
            $out['assignee_ids'] = $ids;
        }
        if (! empty($raw['assignor_id'])) {
            $out['assignor_id'] = (int) $raw['assignor_id'];
        }
        $out['split'] = ! empty($raw['split']) && count($ids) > 1;
        $out['etc_minutes'] = isset($raw['etc_minutes']) && $raw['etc_minutes'] !== '' ? (int) $raw['etc_minutes'] : 10;
        $out['is_corrective_action'] = ! empty($raw['is_corrective_action']);
        $freq = array_values(array_unique(array_filter((array) ($raw['task_frequency'] ?? []), fn ($f) => in_array($f, ['one_time', 'daily', 'weekly', 'monthly'], true))));
        $out['task_frequency'] = $freq ?: ['one_time'];
        foreach (['l2', 'training_link', 'video_link', 'form_link', 'form_report_link', 'checklist_link', 'pl', 'process'] as $k) {
            $val = trim((string) ($raw[$k] ?? ''));
            if ($val !== '') {
                $out[$k] = $val;
            }
        }

        return $out;
    }

    /**
     * Normalise posted conditions to [{column_key, operator, value, label}], dropping unknown
     * operators (and, on built-in pages, unknown columns).
     *
     * @param  mixed  $raw
     * @return list<array{column_key: string, operator: string, value: string|null, label: string|null}>
     */
    private function cleanConditions($raw, string $page, bool $anyColumn = false): array
    {
        $keys = $anyColumn ? [] : array_column(AcePages::columns($page), 'key');
        $out = [];
        foreach ((array) $raw as $c) {
            if (! is_array($c)) {
                continue;
            }
            $key = (string) ($c['column_key'] ?? '');
            $op = (string) ($c['operator'] ?? '');
            if ($key === '' || strlen($key) > 200 || ! isset(AceEngine::OPERATORS[$op])) {
                continue;
            }
            if (! $anyColumn && ! in_array($key, $keys, true)) {
                continue;
            }
            $label = trim((string) ($c['label'] ?? ''));
            $out[] = [
                'column_key' => $key,
                'operator' => $op,
                'value' => isset($c['value']) ? (string) $c['value'] : null,
                'label' => $label !== '' ? mb_substr($label, 0, 160) : null,
            ];
        }

        return array_slice($out, 0, 10);
    }

    public function toggle(int $id): JsonResponse
    {
        $ace = $this->ownRule($id);
        $ace->is_active = ! $ace->is_active;
        $ace->save();

        return response()->json(['ok' => true, 'is_active' => (bool) $ace->is_active]);
    }

    public function runNow(int $id): JsonResponse
    {
        $ace = $this->ownRule($id);
        if ($ace->trigger_type !== 'column') {
            return response()->json(['ok' => false, 'message' => 'Chat-triggered rules fire when a matching message is sent.'], 422);
        }

        try {
            $created = AceEngine::runNow($ace);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Run failed: '.$e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'created' => $created]);
    }

    public function destroy(int $id): JsonResponse
    {
        $ace = $this->ownRule($id);
        AutoChatEventRun::query()->where('ace_id', $ace->id)->delete();
        Cache::forget('ate:checked:'.$ace->id);
        $ace->delete();

        return response()->json(['ok' => true]);
    }
}
