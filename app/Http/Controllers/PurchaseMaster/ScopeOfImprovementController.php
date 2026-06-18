<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\ScopeOfImprovement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScopeOfImprovementController extends Controller
{
    /**
     * Only this user may add new issues.
     */
    private const ADD_ISSUE_EMAIL = 'president@5core.com';

    private function canAddIssue(): bool
    {
        return strtolower(Auth::user()->email ?? '') === self::ADD_ISSUE_EMAIL;
    }

    public function index()
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        $canAddIssue = $this->canAddIssue();
        $currentUserId = Auth::id();

        return view('purchase-master.scope-of-improvement.index', compact('users', 'canAddIssue', 'currentUserId'));
    }

    public function data()
    {
        $query = ScopeOfImprovement::with('user:id,name,email');

        // Everyone except the president sees only their own records.
        if (!$this->canAddIssue()) {
            $query->where('user_id', Auth::id());
        }

        $rows = $query->orderByDesc('updated_at')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'user_id' => $row->user_id,
                    'user_name' => optional($row->user)->name,
                    'issue' => $row->issue,
                    'root_cause' => $row->root_cause,
                    'fixing_root_cause' => $row->fixing_root_cause,
                    's_by' => $row->s_by,
                    'history' => $row->history ?? [],
                    'updated_by' => $row->updated_by,
                    'updated_at' => optional($row->updated_at)->format('Y-m-d H:i'),
                ];
            });

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'issue' => 'nullable|string',
            'root_cause' => 'nullable|string',
            'fixing_root_cause' => 'nullable|string',
        ]);

        $email = Auth::user()->email ?? 'system';

        $validated['s_by'] = Auth::user()->name ?? $email;
        $validated['created_by'] = $email;
        $validated['updated_by'] = $email;
        $validated['history'] = [
            $this->historyEntry($email, 'created'),
        ];

        $row = ScopeOfImprovement::create($validated);

        return response()->json(['success' => true, 'id' => $row->id]);
    }

    public function update(Request $request, $id)
    {
        $row = ScopeOfImprovement::findOrFail($id);
        $email = Auth::user()->email ?? 'system';

        if ($this->canAddIssue()) {
            // President: may edit every field.
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'issue' => 'nullable|string',
                'root_cause' => 'nullable|string',
                'fixing_root_cause' => 'nullable|string',
            ]);
            $action = 'updated';
        } else {
            // Assigned user ("My Progress"): may only update the progress of
            // their own record — root cause and fixing root cause.
            if ((int) $row->user_id !== (int) Auth::id()) {
                return response()->json([
                    'message' => 'You can only update your own assigned issues.',
                ], 403);
            }
            $validated = $request->validate([
                'root_cause' => 'nullable|string',
                'fixing_root_cause' => 'nullable|string',
            ]);
            $action = 'progress updated';
        }

        $history = $row->history ?? [];
        $history[] = $this->historyEntry($email, $action);

        $validated['updated_by'] = $email;
        $validated['history'] = $history;

        $row->update($validated);

        return response()->json(['success' => true, 'id' => $row->id]);
    }

    public function destroy($id)
    {
        if (!$this->canAddIssue()) {
            return response()->json([
                'message' => 'Only ' . self::ADD_ISSUE_EMAIL . ' can delete an issue.',
            ], 403);
        }

        $row = ScopeOfImprovement::findOrFail($id);
        $row->delete();

        return response()->json(['success' => true]);
    }

    private function historyEntry(string $email, string $action): array
    {
        return [
            'email' => $email,
            'action' => $action,
            'at' => now()->format('Y-m-d H:i'),
        ];
    }
}
