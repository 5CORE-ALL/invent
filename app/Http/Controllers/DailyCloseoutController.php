<?php

namespace App\Http\Controllers;

use App\Models\DailyCloseout;
use App\Models\User;
use App\Support\DailyCloseoutNudge;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DailyCloseoutController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
        $filterUserId = (int) $request->query('user_id', 0);

        return view('daily-closeout.index', compact('users', 'filterUserId'));
    }

    public function data(Request $request): JsonResponse
    {
        if (! Schema::hasTable('daily_closeouts')) {
            return response()->json(['data' => []]);
        }

        $query = DailyCloseout::query()->with('user:id,name,email');

        $userId = (int) $request->query('user_id', 0);
        if ($userId > 0) {
            $query->where('user_id', $userId);
        }

        $completed = $request->query('completed');
        if ($completed === 'yes') {
            $query->where('tasks_completed', true);
        } elseif ($completed === 'no') {
            $query->where('tasks_completed', false);
        } elseif ($completed === 'pending') {
            $query->whereNull('tasks_answered_at');
        }

        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));
        if ($from !== '') {
            $query->whereDate('check_date', '>=', $from);
        }
        if ($to !== '') {
            $query->whereDate('check_date', '<=', $to);
        }

        $rows = $query
            ->orderByDesc('check_date')
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (DailyCloseout $row) {
                return [
                    'id' => $row->id,
                    'user_id' => $row->user_id,
                    'user_name' => optional($row->user)->name,
                    'check_date' => optional($row->check_date)->format('Y-m-d'),
                    'tasks_completed' => $row->tasks_answered_at ? ($row->tasks_completed ? 'yes' : 'no') : 'pending',
                    'incomplete_reason' => $row->incomplete_reason,
                    'tasks_answered_at' => $row->tasks_answered_at?->timezone(DailyCloseoutNudge::TZ)->format('Y-m-d H:i'),
                    'dar_nudge_5am_at' => $row->dar_nudge_5am_at?->timezone(DailyCloseoutNudge::TZ)->format('Y-m-d H:i'),
                    'dar_nudge_530am_at' => $row->dar_nudge_530am_at?->timezone(DailyCloseoutNudge::TZ)->format('Y-m-d H:i'),
                ];
            });

        return response()->json(['data' => $rows]);
    }

    public function nudge(): JsonResponse
    {
        $viewer = Auth::user();
        if (! $viewer) {
            return response()->json(['success' => false, 'message' => 'Not signed in.'], 401);
        }

        return response()->json([
            'success' => true,
            'user_id' => (int) $viewer->id,
            'user_name' => (string) ($viewer->name ?? ''),
            ...DailyCloseoutNudge::forUser($viewer),
        ]);
    }

    public function answerTasks(Request $request): JsonResponse
    {
        $viewer = Auth::user();
        if (! $viewer) {
            return response()->json(['success' => false, 'message' => 'Not signed in.'], 401);
        }
        if (! Schema::hasTable('daily_closeouts')) {
            return response()->json(['success' => false, 'message' => 'Daily closeout is not ready yet.'], 503);
        }

        $validated = $request->validate([
            'check_date' => ['nullable', 'date'],
            'tasks_completed' => ['required', 'boolean'],
            'incomplete_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $completed = (bool) $validated['tasks_completed'];
        $reason = trim((string) ($validated['incomplete_reason'] ?? ''));
        if (! $completed && $reason === '') {
            return response()->json([
                'success' => false,
                'message' => 'Please say why today\'s tasks were not completed.',
            ], 422);
        }

        $date = $this->resolveCheckDate($validated['check_date'] ?? null);
        $row = DailyCloseoutNudge::rowFor($viewer, $date);
        $row->tasks_completed = $completed;
        $row->incomplete_reason = $completed ? null : $reason;
        $row->tasks_answered_at = now();
        $row->save();

        return response()->json(['success' => true, 'id' => $row->id]);
    }

    public function markDarNudge(Request $request): JsonResponse
    {
        $viewer = Auth::user();
        if (! $viewer) {
            return response()->json(['success' => false, 'message' => 'Not signed in.'], 401);
        }
        if (! Schema::hasTable('daily_closeouts')) {
            return response()->json(['success' => false, 'message' => 'Daily closeout is not ready yet.'], 503);
        }

        $validated = $request->validate([
            'check_date' => ['nullable', 'date'],
            'slot' => ['required', 'in:first,second'],
        ]);

        $date = $this->resolveCheckDate($validated['check_date'] ?? null);
        $row = DailyCloseoutNudge::rowFor($viewer, $date);
        $column = $validated['slot'] === 'second' ? 'dar_nudge_530am_at' : 'dar_nudge_5am_at';
        if (! $row->{$column}) {
            $row->{$column} = now();
            $row->save();
        }

        return response()->json(['success' => true, 'id' => $row->id]);
    }

    private function resolveCheckDate(?string $requested): string
    {
        $today = DailyCloseoutNudge::checkDate();
        $yesterday = DailyCloseoutNudge::now()->copy()->subDay()->toDateString();
        if (! $requested) {
            return $today;
        }

        try {
            $parsed = Carbon::parse($requested, DailyCloseoutNudge::TZ)->toDateString();
        } catch (\Throwable $e) {
            return $today;
        }

        if (in_array($parsed, [$today, $yesterday], true)) {
            return $parsed;
        }

        return $today;
    }
}
