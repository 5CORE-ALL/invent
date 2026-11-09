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
            'field' => $r->field,
            'operator' => $r->operator,
            'value' => $r->value,
            'message' => $r->message,
            'allow_reply' => $r->allow_reply,
            'is_active' => $r->is_active,
        ];
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
            'field' => ['required', Rule::in(FeedbackAgRule::FIELDS)],
            'operator' => ['required', Rule::in(FeedbackAgRule::OPERATORS)],
            'value' => ['required', 'string', 'max:40'],
            'message' => ['required', 'string', 'max:2000'],
            'allow_reply' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($data['field'] === 'status') {
            if (! in_array($data['value'], ['given', 'none'], true)) {
                return response()->json(['message' => 'Status must be "Given" or "Not given yet".'], 422);
            }
            if (! in_array($data['operator'], ['=', '!='], true)) {
                return response()->json(['message' => 'Status only supports "is" / "is not".'], 422);
            }
        } elseif (! is_numeric($data['value'])) {
            return response()->json(['message' => 'Enter a number for this rule.'], 422);
        }

        $rule = ! empty($data['id']) ? FeedbackAgRule::query()->findOrFail($data['id']) : new FeedbackAgRule;
        if (! $rule->exists) {
            $rule->created_by = auth()->id();
        }
        $rule->fill([
            'field' => $data['field'],
            'operator' => $data['operator'],
            'value' => trim($data['value']),
            'message' => trim($data['message']),
            'allow_reply' => (bool) ($data['allow_reply'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'updated_by' => auth()->id(),
        ])->save();

        return response()->json(['success' => true, 'rule' => $this->serialize($rule)]);
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
