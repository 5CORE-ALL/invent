<?php

namespace App\Http\Controllers;

use App\Models\PenaltyCorrection;
use App\Models\PenaltyEntry;
use App\Models\PenaltyRule;
use App\Models\PenaltySlab;
use App\Models\User;
use App\Support\ChatRules;
use App\Support\Penalty;
use App\Support\TaskBusinessTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Floating "Penalty" icon.
 *
 * - Every user sees ONLY their own penalty points, reasons, last-30-days numbers, slabs and monthly totals,
 *   and can add corrective points for themselves.
 * - Only president@5core.com can add / delete penalty points, manage the auto rules, edit the slabs and
 *   look at other members.
 *
 * Purpose: to evaluate mistakes and shortcomings in performance, not to deduct money.
 */
class PenaltyFabController extends Controller
{
    protected function requireSetter(Request $request): void
    {
        abort_unless(Penalty::isSetter($request->user()), 403, 'Only president@5core.com can do this.');
    }

    protected function notReady(): ?JsonResponse
    {
        if (! Penalty::tablesReady()) {
            return response()->json(['success' => false, 'message' => 'Penalty tables are not ready. Run php artisan migrate.'], 503);
        }

        return null;
    }

    /** @return array<string, mixed> */
    protected function ruleRow(PenaltyRule $r): array
    {
        $metrics = ChatRules::metrics();

        return [
            'id' => $r->id,
            'name' => $r->name,
            'is_active' => (bool) $r->is_active,
            'metric' => $r->metric,
            'metric_label' => $metrics[$r->metric]['label'] ?? $r->metric,
            'operator' => $r->operator,
            'operator_label' => ChatRules::OPERATORS[$r->operator] ?? $r->operator,
            'threshold' => (float) $r->threshold,
            'points' => (int) $r->points,
            'reason' => $r->reason,
            'repeat' => $r->repeat,
            'run_time' => $r->run_time,
            'weekdays_only' => (bool) $r->weekdays_only,
            'last_run_at' => $r->last_run_at?->timezone(TaskBusinessTime::tz())->format('d M, H:i'),
            'last_added_count' => (int) $r->last_added_count,
        ];
    }

    public function show(Request $request): JsonResponse
    {
        if ($resp = $this->notReady()) {
            return $resp;
        }

        $me = $request->user();

        // Light call used on every page to put the L30 count on the floating icon.
        if ($request->boolean('lite')) {
            return response()->json([
                'success' => true,
                'detail' => ['l30_points' => Penalty::l30Points((int) $me->id)],
            ]);
        }

        $isSetter = Penalty::isSetter($me);
        Penalty::ensureMonthsClosed();

        $target = $me;
        if ($isSetter && $request->filled('user_id')) {
            $target = User::query()->find((int) $request->query('user_id')) ?? $me;
        }

        $data = [
            'success' => true,
            'is_setter' => $isSetter,
            'viewing_me' => (int) $target->id === (int) $me->id,
            'detail' => Penalty::detail($target),
            'slabs' => Penalty::slabs(),
            'window_days' => Penalty::windowDays(),
        ];

        if ($isSetter) {
            $data['employees'] = Penalty::employees();
            $data['rules'] = PenaltyRule::query()->orderBy('id')->get()->map(fn ($r) => $this->ruleRow($r))->values();
            $data['metrics'] = collect(ChatRules::metrics())
                ->map(fn ($m, $k) => ['key' => $k, 'label' => $m['label'], 'group' => $m['group'], 'hint' => $m['hint']])
                ->values();
            $data['operators'] = collect(ChatRules::OPERATORS)->map(fn ($label, $op) => ['op' => $op, 'label' => $label])->values();
            $data['today'] = Penalty::today()->toDateString();
            $data['month_start'] = Penalty::today()->startOfMonth()->toDateString();
        }

        return response()->json($data);
    }

    /** History graph data: rolling last-30-days count or amount, one point per day. */
    public function history(Request $request): JsonResponse
    {
        if ($resp = $this->notReady()) {
            return $resp;
        }

        $request->validate([
            'metric' => 'required|in:count,amount',
            'days' => 'nullable|integer|min:7|max:180',
            'user_id' => 'nullable|integer',
        ]);

        $userId = (int) $request->user()->id;
        if (Penalty::isSetter($request->user()) && $request->filled('user_id')) {
            $userId = (int) $request->query('user_id');
        }

        return response()->json([
            'success' => true,
            'metric' => $request->query('metric'),
            'points' => Penalty::history($userId, (int) $request->query('days', 60), (string) $request->query('metric')),
        ]);
    }

    // ---------- president only: penalty entries ----------

    public function storeEntry(Request $request): JsonResponse
    {
        $this->requireSetter($request);
        if ($resp = $this->notReady()) {
            return $resp;
        }

        $v = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'points' => 'required|integer|min:1|max:100',
            'reason' => 'required|string|max:500',
            'penalty_date' => 'nullable|date',
        ]);

        $today = Penalty::today();
        $date = isset($v['penalty_date']) ? \Carbon\Carbon::parse($v['penalty_date'], TaskBusinessTime::tz())->startOfDay() : $today->copy();
        if ($date->gt($today)) {
            return response()->json(['success' => false, 'message' => 'The date cannot be in the future.'], 422);
        }
        if ($date->lt($today->copy()->startOfMonth())) {
            return response()->json(['success' => false, 'message' => 'That month is already closed. Pick a date in the current month.'], 422);
        }

        PenaltyEntry::query()->create([
            'user_id' => (int) $v['user_id'],
            'points' => (int) $v['points'],
            'reason' => trim($v['reason']),
            'source' => 'manual',
            'penalty_date' => $date->toDateString(),
            'created_by' => $request->user()->email,
        ]);

        return response()->json(['success' => true]);
    }

    public function destroyEntry(Request $request, int $id): JsonResponse
    {
        $this->requireSetter($request);

        $entry = PenaltyEntry::query()->findOrFail($id);
        if ($entry->penalty_date->lt(Penalty::today()->startOfMonth())) {
            return response()->json(['success' => false, 'message' => 'That month is already closed, so this penalty can no longer be removed.'], 422);
        }
        $entry->delete();

        return response()->json(['success' => true]);
    }

    // ---------- president only: auto rules ----------

    public function saveRule(Request $request): JsonResponse
    {
        $this->requireSetter($request);
        if ($resp = $this->notReady()) {
            return $resp;
        }

        $v = $request->validate([
            'id' => 'nullable|integer|exists:penalty_rules,id',
            'name' => 'required|string|max:120',
            'metric' => 'required|string',
            'operator' => 'required|string',
            'threshold' => 'required|numeric',
            'points' => 'required|integer|min:1|max:50',
            'reason' => 'required|string|max:500',
            'repeat' => 'required|in:daily,weekly,monthly',
            'run_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'weekdays_only' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        if (! isset(ChatRules::metrics()[$v['metric']])) {
            return response()->json(['success' => false, 'message' => 'Pick a valid column for the condition.'], 422);
        }
        if (! isset(ChatRules::OPERATORS[$v['operator']])) {
            return response()->json(['success' => false, 'message' => 'Pick how to compare the number.'], 422);
        }

        $fields = [
            'name' => trim($v['name']),
            'metric' => $v['metric'],
            'operator' => $v['operator'],
            'threshold' => round((float) $v['threshold'], 2),
            'points' => (int) $v['points'],
            'reason' => trim($v['reason']),
            'repeat' => $v['repeat'],
            'run_time' => $v['run_time'],
            'weekdays_only' => (bool) ($v['weekdays_only'] ?? true),
            'is_active' => (bool) ($v['is_active'] ?? true),
        ];

        if (! empty($v['id'])) {
            PenaltyRule::query()->findOrFail((int) $v['id'])->update($fields);
        } else {
            PenaltyRule::query()->create($fields + ['created_by' => $request->user()->email]);
        }

        return response()->json(['success' => true]);
    }

    public function toggleRule(Request $request, int $id): JsonResponse
    {
        $this->requireSetter($request);
        $rule = PenaltyRule::query()->findOrFail($id);
        $rule->update(['is_active' => ! $rule->is_active]);

        return response()->json(['success' => true, 'is_active' => (bool) $rule->is_active]);
    }

    public function destroyRule(Request $request, int $id): JsonResponse
    {
        $this->requireSetter($request);
        PenaltyRule::query()->findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    /** "Run now": apply every active rule to today's numbers (one penalty per rule, person and period). */
    public function runRules(Request $request): JsonResponse
    {
        $this->requireSetter($request);
        if ($resp = $this->notReady()) {
            return $resp;
        }

        $rules = PenaltyRule::query()->where('is_active', true)->get();
        if ($rules->isEmpty()) {
            return response()->json(['success' => true, 'added' => 0, 'message' => 'No active rules to run.']);
        }

        try {
            $rows = ChatRules::memberRows();
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Could not read the Task Summary numbers right now.'], 500);
        }

        $added = 0;
        $already = 0;
        foreach ($rules as $rule) {
            $r = Penalty::runRule($rule, $rows);
            $added += $r['added'];
            $already += $r['already'];
        }

        return response()->json([
            'success' => true,
            'added' => $added,
            'already' => $already,
            'message' => "Rules ran: {$added} penalty entr".($added === 1 ? 'y' : 'ies')." added, {$already} already had it this period.",
        ]);
    }

    // ---------- president only: slabs ----------

    public function saveSlabs(Request $request): JsonResponse
    {
        $this->requireSetter($request);
        if ($resp = $this->notReady()) {
            return $resp;
        }

        $v = $request->validate([
            'slabs' => 'required|array|size:5',
            'slabs.*.max_points' => 'nullable|integer|min:0|max:100000',
            'slabs.*.rate' => 'required|numeric|min:0|max:100000',
        ]);

        $rows = array_values($v['slabs']);
        $prev = -1;
        foreach ($rows as $i => $row) {
            $last = $i === count($rows) - 1;
            if ($last) {
                $rows[$i]['max_points'] = null; // the last slab has no upper limit
                continue;
            }
            if (! isset($row['max_points']) || $row['max_points'] === '') {
                return response()->json(['success' => false, 'message' => 'Slab '.($i + 1).' needs an upper limit.'], 422);
            }
            if ((int) $row['max_points'] <= $prev) {
                return response()->json(['success' => false, 'message' => 'Each slab must end higher than the one before it.'], 422);
            }
            $prev = (int) $row['max_points'];
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $i => $row) {
                PenaltySlab::query()->updateOrCreate(
                    ['position' => $i + 1],
                    ['max_points' => $row['max_points'] === null ? null : (int) $row['max_points'], 'rate' => round((float) $row['rate'], 2)]
                );
            }
        });

        return response()->json(['success' => true, 'slabs' => Penalty::slabs()]);
    }

    // ---------- every user: own corrective points ----------

    public function addCorrection(Request $request): JsonResponse
    {
        if ($resp = $this->notReady()) {
            return $resp;
        }

        $v = $request->validate([
            'note' => 'required|string|min:3|max:500',
            'penalty_entry_id' => 'nullable|integer',
        ]);

        $me = $request->user();
        if (! empty($v['penalty_entry_id'])) {
            $owns = PenaltyEntry::query()->where('id', (int) $v['penalty_entry_id'])->where('user_id', $me->id)->exists();
            if (! $owns) {
                return response()->json(['success' => false, 'message' => 'That penalty is not yours.'], 403);
            }
        }

        PenaltyCorrection::query()->create([
            'user_id' => (int) $me->id,
            'penalty_entry_id' => ! empty($v['penalty_entry_id']) ? (int) $v['penalty_entry_id'] : null,
            'note' => trim($v['note']),
        ]);

        return response()->json(['success' => true]);
    }

    public function deleteCorrection(Request $request, int $id): JsonResponse
    {
        PenaltyCorrection::query()->where('id', $id)->where('user_id', $request->user()->id)->delete();

        return response()->json(['success' => true]);
    }
}
