<?php

namespace App\Http\Controllers;

use App\Models\DepartmentFeedback;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentFeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $weekStart = DepartmentFeedback::weekStart();
        $weekDate = $weekStart->toDateString();
        $todayKey = DepartmentFeedback::todayKey();
        $filter = $request->query('department');
        if (! is_string($filter) || ! DepartmentFeedback::isKnown($filter)) {
            $filter = null;
        }

        $responses = collect();
        $stats = collect();
        $feed = null;
        $feedCounts = collect();
        $userCards = collect();

        if (DepartmentFeedback::tableReady()) {
            // Total feedback per department (same rules as the "Recent feedback" list).
            $feedCounts = DepartmentFeedback::query()
                ->where('skipped', false)
                ->whereNotNull('rating')
                ->selectRaw('department, COUNT(*) as total')
                ->groupBy('department')
                ->pluck('total', 'department');

            // Per-user totals (all weeks). Everyone active is listed, including people who have given nothing yet.
            $given = DepartmentFeedback::query()
                ->where('skipped', false)
                ->whereNotNull('rating')
                ->selectRaw("user_id, COUNT(*) as total, AVG(rating) as avg_rating, SUM(CASE WHEN comment IS NOT NULL AND TRIM(comment) <> '' THEN 1 ELSE 0 END) as reviews, MAX(created_at) as last_at")
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');

            $userCards = User::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'avatar', 'designation'])
                ->map(function (User $u) use ($given) {
                    $g = $given->get($u->id);

                    return [
                        'id' => (int) $u->id,
                        'name' => (string) $u->name,
                        'designation' => (string) ($u->designation ?? ''),
                        'avatar' => $u->avatar
                            ? (preg_match('#^(https?:)?//|^/#', (string) $u->avatar) ? (string) $u->avatar : asset('storage/'.$u->avatar))
                            : asset('images/users/avatar-2.jpg'),
                        'ratings' => $g ? (int) $g->total : 0,
                        'reviews' => $g ? (int) $g->reviews : 0,
                        'avg' => $g ? round((float) $g->avg_rating, 1) : null,
                        'last_at' => $g && $g->last_at ? Carbon::parse($g->last_at) : null,
                    ];
                })
                ->sort(function (array $a, array $b) {
                    // Most active first; people who have not given any feedback sink to the end (alphabetical).
                    return [$b['ratings'], $a['name']] <=> [$a['ratings'], $b['name']];
                })
                ->values();

            $responses = DepartmentFeedback::query()
                ->where('user_id', $request->user()->id)
                ->where('week_start', $weekDate)
                ->get()
                ->keyBy('department');

            // Cumulative (all weeks): average, number of ratings and number of written reviews.
            $stats = DepartmentFeedback::query()
                ->where('skipped', false)
                ->whereNotNull('rating')
                ->selectRaw("department, COUNT(*) as total, AVG(rating) as avg_rating, SUM(CASE WHEN comment IS NOT NULL AND TRIM(comment) <> '' THEN 1 ELSE 0 END) as reviews")
                ->groupBy('department')
                ->get()
                ->keyBy('department');

            $feedQuery = DepartmentFeedback::query()
                ->with('user:id,name')
                ->where('skipped', false)
                ->whereNotNull('rating')
                ->latest();

            if ($filter) {
                $feedQuery->where('department', $filter);
            }

            $feed = $feedQuery->paginate(12)->withQueryString();
        }

        $selected = old('department', $todayKey);
        if (! is_string($selected) || ! DepartmentFeedback::isKnown($selected)) {
            $selected = $todayKey;
        }

        $existing = $responses->get($selected);
        $existingRating = ($existing && ! $existing->skipped) ? (int) $existing->rating : 0;
        $existingComment = ($existing && ! $existing->skipped) ? (string) $existing->comment : '';

        $mine = $responses->map(function (DepartmentFeedback $row) {
            return [
                'rating' => $row->skipped ? null : $row->rating,
                'comment' => $row->skipped ? '' : (string) $row->comment,
                'skipped' => (bool) $row->skipped,
            ];
        });

        return view('feedback.index', [
            'departments' => DepartmentFeedback::DEPARTMENTS,
            'weekdays' => DepartmentFeedback::WEEKDAYS,
            'weekStart' => $weekStart,
            'weekEnd' => $weekStart->copy()->endOfWeek(Carbon::SUNDAY),
            'questions' => collect(DepartmentFeedback::DEPARTMENTS)->map(fn (array $meta) => $meta['question']),
            'focusAreas' => collect(DepartmentFeedback::DEPARTMENTS)->map(fn (array $meta) => $meta['focus']),
            'todayKey' => $todayKey,
            'todayIso' => now()->dayOfWeekIso,
            'responses' => $responses,
            'stats' => $stats,
            'feed' => $feed,
            'feedCounts' => $feedCounts,
            'userCards' => $userCards,
            'filter' => $filter,
            'selected' => $selected,
            'ratingValue' => (int) old('rating', $existingRating),
            'commentValue' => old('comment', $existingComment),
            'mine' => $mine,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'department' => ['required', 'string', Rule::in(array_keys(DepartmentFeedback::DEPARTMENTS))],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:4', 'max:2000'],
        ]);

        $row = $this->saveWeekRow($request, $data['department'], [
            'rating' => (int) $data['rating'],
            'comment' => trim($data['comment']),
            'skipped' => false,
        ]);

        $label = DepartmentFeedback::DEPARTMENTS[$data['department']]['label'];

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'id' => $row->id,
                'message' => $label.' feedback saved.',
            ]);
        }

        return redirect()
            ->route('feedback.index', ['department' => $data['department']])
            ->with('status', $label.' feedback saved for this week.');
    }

    public function skip(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'department' => ['required', 'string', Rule::in(array_keys(DepartmentFeedback::DEPARTMENTS))],
        ]);

        $this->saveWeekRow($request, $data['department'], [
            'rating' => null,
            'comment' => null,
            'skipped' => true,
        ]);

        $label = DepartmentFeedback::DEPARTMENTS[$data['department']]['label'];

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $label.' skipped for this week.',
            ]);
        }

        return redirect()
            ->route('feedback.index')
            ->with('status', $label.' skipped for this week.');
    }

    /**
     * @param  array{rating: int|null, comment: string|null, skipped: bool}  $values
     */
    private function saveWeekRow(Request $request, string $department, array $values): DepartmentFeedback
    {
        if (! DepartmentFeedback::tableReady()) {
            abort(503, 'Feedback is not set up yet.');
        }

        return DepartmentFeedback::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'department' => $department,
                'week_start' => DepartmentFeedback::weekStart()->toDateString(),
            ],
            $values
        );
    }
}
