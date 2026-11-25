<?php

namespace App\Http\Controllers;

use App\Models\ChatRule;
use App\Models\User;
use App\Support\ChatRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Chat Rules modal on /tasks/summary (directors only).
 */
class ChatRuleController extends Controller
{
    protected function guard(): ?JsonResponse
    {
        if (! Auth::user()?->isDirector()) {
            return response()->json(['success' => false, 'message' => 'Only directors can manage chat rules.'], 403);
        }
        if (! ChatRules::tablesReady()) {
            return response()->json(['success' => false, 'message' => 'Chat rules are not set up yet. Run the migrations.'], 503);
        }

        return null;
    }

    public function index(): JsonResponse
    {
        if ($blocked = $this->guard()) {
            return $blocked;
        }

        $metrics = ChatRules::metrics();
        $rules = ChatRule::query()->orderByDesc('is_active')->orderBy('name')->get()->map(function (ChatRule $r) use ($metrics) {
            return [
                'id' => $r->id,
                'name' => $r->name,
                'is_active' => $r->is_active,
                'metric' => $r->metric,
                'metric_label' => $metrics[$r->metric]['label'] ?? $r->metric,
                'operator' => $r->operator,
                'operator_label' => ChatRules::OPERATORS[$r->operator] ?? $r->operator,
                'threshold' => $r->threshold,
                'audience' => $r->audience,
                'user_ids' => $r->user_ids ?? [],
                'message' => $r->message,
                'send_time' => $r->send_time,
                'weekdays_only' => $r->weekdays_only,
                'frequency' => $r->frequency,
                'last_run_at' => $r->last_run_at?->timezone(\App\Support\TaskBusinessTime::tz())->format('d M, h:i A'),
                'last_sent_count' => $r->last_sent_count,
            ];
        })->values();

        $users = User::query()
            ->where('is_active', true)
            ->whereNull('deactivated_at')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => (string) $u->name])
            ->values();

        return response()->json([
            'success' => true,
            'rules' => $rules,
            'metrics' => collect($metrics)->map(fn ($m, $key) => ['key' => $key] + $m)->values(),
            'operators' => collect(ChatRules::OPERATORS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'users' => $users,
            'timezone' => \App\Support\TaskBusinessTime::shortLabel(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($blocked = $this->guard()) {
            return $blocked;
        }

        [$fields, $error] = ChatRules::sanitize($request->all());
        if ($fields === null) {
            return response()->json(['success' => false, 'message' => $error], 422);
        }

        $id = (int) $request->input('id', 0);
        if ($id > 0) {
            $rule = ChatRule::query()->findOrFail($id);
            $rule->fill($fields)->save();
        } else {
            $rule = ChatRule::query()->create($fields + ['created_by' => Auth::id()]);
        }

        return response()->json(['success' => true, 'id' => $rule->id]);
    }

    public function toggle(int $id): JsonResponse
    {
        if ($blocked = $this->guard()) {
            return $blocked;
        }

        $rule = ChatRule::query()->findOrFail($id);
        $rule->is_active = ! $rule->is_active;
        $rule->save();

        return response()->json(['success' => true, 'is_active' => $rule->is_active]);
    }

    public function destroy(int $id): JsonResponse
    {
        if ($blocked = $this->guard()) {
            return $blocked;
        }

        ChatRule::query()->whereKey($id)->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Preview the form as typed (nothing is saved or sent): who matches and the message each would get.
     */
    public function preview(Request $request): JsonResponse
    {
        if ($blocked = $this->guard()) {
            return $blocked;
        }

        [$fields, $error] = ChatRules::sanitize($request->all());
        if ($fields === null) {
            return response()->json(['success' => false, 'message' => $error], 422);
        }

        @set_time_limit(120);
        $rule = new ChatRule($fields);
        $rule->id = (int) $request->input('id', 0) ?: null;
        $result = ChatRules::run($rule, true);

        return response()->json(['success' => true] + $this->trim($result));
    }

    /**
     * Send a saved rule now to everyone who matches and has not had it in this period.
     */
    public function run(int $id): JsonResponse
    {
        if ($blocked = $this->guard()) {
            return $blocked;
        }

        @set_time_limit(180);
        $rule = ChatRule::query()->findOrFail($id);
        $result = ChatRules::run($rule, false);

        return response()->json(['success' => true] + $this->trim($result));
    }

    /**
     * @param  array{matched: int, sent: int, already: int, people: list<array<string, mixed>>}  $result
     * @return array<string, mixed>
     */
    protected function trim(array $result): array
    {
        $result['people_total'] = count($result['people']);
        $result['people'] = array_slice($result['people'], 0, 60);

        return $result;
    }
}
