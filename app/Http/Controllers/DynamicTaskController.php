<?php

namespace App\Http\Controllers;

use App\Models\DynamicTaskCreation;
use App\Models\DynamicTaskRule;
use App\Models\User;
use App\Services\DynamicMessages\DynamicMessageEvents;
use App\Services\DynamicMessages\DynamicTaskEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Dynamic task creator: pre-saved tasks for pre-saved users from page data / events.
 * Create / edit / run is restricted to president@5core.com; tasks are assigned by Amarjit.
 */
class DynamicTaskController extends Controller
{
    public function __construct(private readonly DynamicTaskEngine $engine) {}

    private function deny(): ?JsonResponse
    {
        return DynamicMessageController::canManage()
            ? null
            : response()->json(['message' => 'Only '.config('dynamic_messages.editor_email').' can manage task rules.'], 403);
    }

    public function index(): View
    {
        abort_unless(DynamicMessageController::canManage(), 403, 'Only '.config('dynamic_messages.editor_email').' can manage task rules.');

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

        return view('dynamic-tasks.index', [
            'events' => $events,
            'users' => User::query()
                ->where(function ($q) {
                    $q->whereNull('is_active')->orWhere('is_active', 1);
                })
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'operators' => \App\Models\DynamicMessageRule::OPERATORS,
            'frequencies' => \App\Models\DynamicMessageRule::FREQUENCIES,
            'priorities' => DynamicTaskRule::PRIORITIES,
            'assignorName' => config('dynamic_messages.reply_to_label'),
            'assignorEmail' => DynamicTaskEngine::assignorEmail(),
        ]);
    }

    public function rules(): JsonResponse
    {
        if ($r = $this->deny()) {
            return $r;
        }

        $rules = DynamicTaskRule::query()->withCount('creations')->orderByDesc('id')->get();
        $names = User::withTrashed()->whereIn('id', $rules->flatMap->recipientIds()->unique()->all())->pluck('name', 'id');

        return response()->json(['data' => $rules->map(function (DynamicTaskRule $r) use ($names) {
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
                'description' => $r->description,
                'task_group' => $r->task_group,
                'priority' => $r->priority,
                'eta_minutes' => $r->eta_minutes,
                'tid_in_days' => $r->tid_in_days,
                'link' => $r->link,
                'recipient_user_ids' => $r->recipientIds(),
                'recipient_names' => collect($r->recipientIds())->map(fn ($id) => $names[$id] ?? ('#'.$id))->values(),
                'frequency' => $r->frequency,
                'skip_if_open' => $r->skip_if_open,
                'is_active' => $r->is_active,
                'manual' => ! empty($def['manual']),
                'last_run_at' => $r->last_run_at?->timezone(config('dynamic_messages.timezone'))->format('d M Y H:i'),
                'creations_count' => $r->creations_count,
            ];
        })->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($r = $this->deny()) {
            return $r;
        }
        $rule = new DynamicTaskRule;
        $rule->created_by = auth()->id();

        return $this->save($request, $rule);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($r = $this->deny()) {
            return $r;
        }

        return $this->save($request, DynamicTaskRule::query()->findOrFail($id));
    }

    private function save(Request $request, DynamicTaskRule $rule): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'event_key' => ['required', 'string', Rule::in(array_keys(DynamicMessageEvents::definitions()))],
            'metric' => ['nullable', 'string', 'max:80'],
            'operator' => ['nullable', Rule::in(\App\Models\DynamicMessageRule::OPERATORS)],
            'threshold' => ['nullable', 'numeric'],
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:5000'],
            'task_group' => ['nullable', 'string', 'max:120'],
            'priority' => ['required', Rule::in(array_keys(DynamicTaskRule::PRIORITIES))],
            'eta_minutes' => ['required', 'integer', 'min:1', 'max:100000'],
            'tid_in_days' => ['required', 'integer', 'min:0', 'max:365'],
            'link' => ['nullable', 'string', 'max:2048'],
            'recipient_user_ids' => ['required', 'array', 'min:1'],
            'recipient_user_ids.*' => ['integer', Rule::exists('users', 'id')],
            'frequency' => ['required', Rule::in(array_keys(\App\Models\DynamicMessageRule::FREQUENCIES))],
            'skip_if_open' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $def = DynamicMessageEvents::definition($data['event_key']);
        $metric = ($data['metric'] ?? '') === '' ? null : $data['metric'];
        if (! empty($def['manual'])) {
            $metric = null;
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
            'description' => $data['description'] ?? null,
            'task_group' => ($data['task_group'] ?? '') !== '' ? $data['task_group'] : null,
            'priority' => $data['priority'],
            'eta_minutes' => $data['eta_minutes'],
            'tid_in_days' => $data['tid_in_days'],
            'link' => ($data['link'] ?? '') !== '' ? $data['link'] : null,
            'recipient_user_ids' => array_values(array_unique(array_map('intval', $data['recipient_user_ids']))),
            'frequency' => $data['frequency'],
            'skip_if_open' => (bool) ($data['skip_if_open'] ?? true),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'updated_by' => auth()->id(),
        ])->save();

        return response()->json(['success' => true, 'id' => $rule->id]);
    }

    public function toggle(int $id): JsonResponse
    {
        if ($r = $this->deny()) {
            return $r;
        }
        $rule = DynamicTaskRule::query()->findOrFail($id);
        $rule->update(['is_active' => ! $rule->is_active, 'updated_by' => auth()->id()]);

        return response()->json(['success' => true, 'is_active' => $rule->is_active]);
    }

    public function destroy(int $id): JsonResponse
    {
        if ($r = $this->deny()) {
            return $r;
        }
        DynamicTaskRule::query()->findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    /** Dry run: which tasks would be created right now (nothing is created). */
    public function preview(int $id): JsonResponse
    {
        if ($r = $this->deny()) {
            return $r;
        }

        return response()->json($this->engine->runRule(DynamicTaskRule::query()->findOrFail($id), true));
    }

    /** Evaluate immediately (the scheduler also does this every 30 minutes). */
    public function run(int $id): JsonResponse
    {
        if ($r = $this->deny()) {
            return $r;
        }
        $result = $this->engine->runRule(DynamicTaskRule::query()->findOrFail($id));

        return response()->json([
            'success' => true,
            'created' => $result['created'],
            'skipped_duplicates' => $result['skipped_duplicates'],
            'skipped_open' => $result['skipped_open'],
            'no_match' => $result['no_match'],
        ]);
    }

    /** Tasks created by rules (latest 300). */
    public function log(): JsonResponse
    {
        if ($r = $this->deny()) {
            return $r;
        }

        $rows = DynamicTaskCreation::query()
            ->with(['assignee:id,name', 'rule:id,name', 'task:id,status,title'])
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->map(fn (DynamicTaskCreation $c) => [
                'id' => $c->id,
                'created_at' => $c->created_at?->timezone(config('dynamic_messages.timezone'))->format('d M Y H:i'),
                'rule' => $c->rule?->name ?? '(deleted rule)',
                'assignee' => $c->assignee?->name ?? ('#'.$c->assignee_user_id),
                'title' => $c->task?->title ?? $c->title,
                'task_id' => $c->task_id,
                'status' => $c->task ? $c->task->status : ($c->task_id ? 'Deleted' : 'Not created'),
            ]);

        return response()->json(['data' => $rows]);
    }
}
