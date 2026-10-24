<?php

namespace App\Http\Controllers;

use App\Models\DynamicMessageRule;
use App\Models\DynamicTaskRule;
use App\Models\User;
use App\Services\DynamicMessages\DynamicMessageEngine;
use App\Services\DynamicMessages\DynamicMessageEvents;
use App\Services\DynamicMessages\DynamicTaskEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Task Summary quick actions: "Send message" / "Create task" for one team member,
 * using the saved Dynamic Message / Dynamic Task rules as preset templates.
 * president@5core.com only; tasks are assigned by Amarjit.
 */
class DynamicQuickActionController extends Controller
{
    public function __construct(
        private readonly DynamicMessageEngine $messages,
        private readonly DynamicTaskEngine $tasks
    ) {}

    private function deny(): ?JsonResponse
    {
        return DynamicMessageController::canManage()
            ? null
            : response()->json(['message' => 'Only '.config('dynamic_messages.editor_email').' can use this.'], 403);
    }

    private function member(int $id): ?User
    {
        return User::query()
            ->whereKey($id)
            ->where(function ($q) {
                $q->whereNull('is_active')->orWhere('is_active', 1);
            })
            ->first();
    }

    /** Live values for the rule's page event, for this one person. @return array{0: array, 1: bool} [vars, data_available] */
    private function varsFor(string $eventKey, User $user, array &$cache): array
    {
        if (! isset($cache[$eventKey])) {
            $vars = [];
            $available = false;
            foreach (DynamicMessageEvents::resolve($eventKey, collect([$user->id => $user])) as $ctx) {
                if ($ctx['user_id'] === null || (int) $ctx['user_id'] === (int) $user->id) {
                    $vars = $ctx['vars'];
                    $available = true;
                    break;
                }
            }
            $cache[$eventKey] = [$vars, $available];
        }

        return $cache[$eventKey];
    }

    /** Presets for one member, already filled with that member's live data. */
    public function presets(Request $request): JsonResponse
    {
        if ($deny = $this->deny()) {
            return $deny;
        }
        $user = $this->member((int) $request->query('user_id'));
        if (! $user) {
            return response()->json(['message' => 'Team member not found.'], 404);
        }

        $cache = [];
        $messages = DynamicMessageRule::query()->orderBy('name')->get()->map(function (DynamicMessageRule $r) use ($user, &$cache) {
            [$vars, $ok] = $this->varsFor($r->event_key, $user, $cache);

            return [
                'id' => $r->id,
                'name' => $r->name,
                'is_active' => $r->is_active,
                'allow_reply' => $r->allow_reply,
                'data_available' => $ok,
                'title' => $this->messages->render($r->title, $user, $vars),
                'body' => $this->messages->render($r->body, $user, $vars),
            ];
        })->values();

        $tasks = DynamicTaskRule::query()->orderBy('name')->get()->map(function (DynamicTaskRule $r) use ($user, &$cache) {
            [$vars, $ok] = $this->varsFor($r->event_key, $user, $cache);

            return [
                'id' => $r->id,
                'name' => $r->name,
                'is_active' => $r->is_active,
                'data_available' => $ok,
                'title' => $this->messages->render($r->title, $user, $vars),
                'description' => $this->messages->render((string) $r->description, $user, $vars),
                'priority' => $r->priority,
                'eta_minutes' => $r->eta_minutes,
                'tid_in_days' => $r->tid_in_days,
                'task_group' => $r->task_group,
            ];
        })->values();

        return response()->json([
            'member' => ['id' => $user->id, 'name' => $user->name],
            'assignor' => config('dynamic_messages.reply_to_label'),
            'messages' => $messages,
            'tasks' => $tasks,
        ]);
    }

    public function sendMessage(Request $request): JsonResponse
    {
        if ($deny = $this->deny()) {
            return $deny;
        }
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'rule_id' => ['required', 'integer', 'exists:dynamic_message_rules,id'],
            'title' => ['required', 'string', 'max:190'],
            'body' => ['required', 'string', 'max:3000'],
        ]);
        $user = $this->member((int) $data['user_id']);
        if (! $user) {
            return response()->json(['message' => 'Team member not found.'], 404);
        }

        $rule = DynamicMessageRule::query()->findOrFail($data['rule_id']);
        $cache = [];
        [$vars] = $this->varsFor($rule->event_key, $user, $cache);
        $this->messages->sendQuick($rule, $user, $data['title'], $data['body'], $vars);

        return response()->json(['success' => true, 'message' => 'Message sent to '.$user->name.'.']);
    }

    public function createTask(Request $request): JsonResponse
    {
        if ($deny = $this->deny()) {
            return $deny;
        }
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'rule_id' => ['required', 'integer', 'exists:dynamic_task_rules,id'],
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
        $user = $this->member((int) $data['user_id']);
        if (! $user) {
            return response()->json(['message' => 'Team member not found.'], 404);
        }

        $rule = DynamicTaskRule::query()->findOrFail($data['rule_id']);
        $cache = [];
        [$vars] = $this->varsFor($rule->event_key, $user, $cache);
        $task = $this->tasks->createQuick($rule, $user, $data['title'], (string) ($data['description'] ?? ''), $vars);

        return response()->json([
            'success' => true,
            'task_id' => $task->id,
            'message' => 'Task created for '.$user->name.' (assigned by '.config('dynamic_messages.reply_to_label').').',
        ]);
    }
}
