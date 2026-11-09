<?php

namespace App\Http\Controllers;

use App\Models\FeedbackAgRule;
use App\Services\FeedbackAgMessageService;
use App\Support\SuperAdminAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * AG-MSG on the Feedback page: manage rules and generate messages (from president@5core.com).
 */
class FeedbackAgMsgController extends Controller
{
    public function __construct(private readonly FeedbackAgMessageService $service) {}

    public static function allowed(): bool
    {
        return DynamicMessageController::canManage() || SuperAdminAccess::is();
    }

    private function guard(): ?JsonResponse
    {
        if (! self::allowed()) {
            return response()->json(['message' => 'You are not allowed to use AG-MSG.'], 403);
        }
        if (! FeedbackAgRule::tableReady()) {
            return response()->json(['message' => 'AG-MSG is not set up yet. Run the migrations (php artisan migrate).'], 503);
        }

        return null;
    }

    private function serialize(FeedbackAgRule $r): array
    {
        return [
            'id' => $r->id,
            'conditions' => $r->conditionList(),
            'match_mode' => $r->match_mode === 'any' ? 'any' : 'all',
            'message' => $r->message,
            'allow_reply' => $r->allow_reply,
            'is_active' => $r->is_active,
        ];
    }

    /** Validate one condition; returns an error message or null. */
    private function conditionError(array $c): ?string
    {
        if ($c['field'] === 'status') {
            if (! in_array($c['value'], ['given', 'none'], true)) {
                return 'Status must be "Given" or "Not given yet".';
            }
            if (! in_array($c['operator'], ['=', '!='], true)) {
                return 'Status only supports "is" / "is not".';
            }

            return null;
        }

        return is_numeric($c['value']) ? null : 'Enter a number for each numeric condition.';
    }

    public function rules(): JsonResponse
    {
        if ($deny = $this->guard()) {
            return $deny;
        }

        return response()->json([
            'data' => FeedbackAgRule::query()->orderBy('id')->get()->map(fn ($r) => $this->serialize($r))->values(),
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        if ($deny = $this->guard()) {
            return $deny;
        }

        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:'.FeedbackAgRule::TABLE.',id'],
            'conditions' => ['required', 'array', 'min:1', 'max:10'],
            'conditions.*.field' => ['required', Rule::in(FeedbackAgRule::FIELDS)],
            'conditions.*.operator' => ['required', Rule::in(FeedbackAgRule::OPERATORS)],
            'conditions.*.value' => ['required', 'string', 'max:40'],
            'match_mode' => ['nullable', Rule::in(['all', 'any'])],
            'message' => ['required', 'string', 'max:2000'],
            'allow_reply' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $conditions = [];
        foreach ($data['conditions'] as $c) {
            $c['value'] = trim((string) $c['value']);
            if ($err = $this->conditionError($c)) {
                return response()->json(['message' => $err], 422);
            }
            $conditions[] = ['field' => $c['field'], 'operator' => $c['operator'], 'value' => $c['value']];
        }

        $rule = ! empty($data['id']) ? FeedbackAgRule::query()->findOrFail($data['id']) : new FeedbackAgRule;
        if (! $rule->exists) {
            $rule->created_by = auth()->id();
        }

        $attrs = [
            // First condition is mirrored in the original columns (older rows / fallbacks).
            'field' => $conditions[0]['field'],
            'operator' => $conditions[0]['operator'],
            'value' => $conditions[0]['value'],
            'message' => trim($data['message']),
            'allow_reply' => (bool) ($data['allow_reply'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'updated_by' => auth()->id(),
        ];
        if (FeedbackAgRule::conditionsReady()) {
            $attrs['conditions'] = $conditions;
            $attrs['match_mode'] = $data['match_mode'] ?? 'all';
        } elseif (count($conditions) > 1) {
            return response()->json(['message' => 'Multiple conditions need a database update. Run: php artisan migrate'], 503);
        }
        $rule->fill($attrs)->save();

        return response()->json(['success' => true, 'rule' => $this->serialize($rule)]);
    }

    /** Pause / resume a saved rule without touching its other settings. */
    public function toggle(int $id): JsonResponse
    {
        if ($deny = $this->guard()) {
            return $deny;
        }
        $rule = FeedbackAgRule::query()->findOrFail($id);
        $rule->forceFill(['is_active' => ! $rule->is_active, 'updated_by' => auth()->id()])->save();

        return response()->json(['success' => true, 'is_active' => $rule->is_active]);
    }

    public function destroy(int $id): JsonResponse
    {
        if ($deny = $this->guard()) {
            return $deny;
        }
        FeedbackAgRule::query()->whereKey($id)->delete();

        return response()->json(['success' => true]);
    }

    /** AG-MSG (one row) and AG-MSG All (every row shown): generate messages for the people whose row matches a rule. */
    public function send(Request $request): JsonResponse
    {
        if ($deny = $this->guard()) {
            return $deny;
        }

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:500'],
            'user_ids.*' => ['integer'],
            'department' => ['required', 'string'],
        ]);
        $department = $data['department'];
        if ($department !== 'all' && ! \App\Models\DepartmentFeedback::isKnown($department)) {
            return response()->json(['message' => 'Unknown department.'], 422);
        }

        $ids = array_values(array_unique(array_map('intval', $data['user_ids'])));
        $r = $this->service->generate($ids, $department);

        if (FeedbackAgRule::query()->where('is_active', true)->doesntExist()) {
            return response()->json(['success' => false, 'message' => 'No active AG-MSG rules. Add one with "AG-MSG Rules".'] + $r, 422);
        }

        $parts = [];
        $parts[] = $r['sent'].' message'.($r['sent'] === 1 ? '' : 's').' sent';
        if ($r['duplicates'] > 0) {
            $parts[] = $r['duplicates'].' already sent today';
        }
        if ($r['no_match'] > 0) {
            $parts[] = $r['no_match'].' with no matching rule';
        }

        return response()->json(['success' => true, 'message' => implode(' · ', $parts).'.'] + $r);
    }
}
