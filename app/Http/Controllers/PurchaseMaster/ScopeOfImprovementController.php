<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\ScopeOfImprovement;
use App\Models\User;
use App\Support\SuperAdminAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScopeOfImprovementController extends Controller
{
    /**
     * Only this user may add new issues.
     */
    private const ADD_ISSUE_EMAIL = 'president@5core.com';

    private function canAddIssue(?User $user = null): bool
    {
        $user ??= Auth::user();

        return strtolower($user->email ?? '') === self::ADD_ISSUE_EMAIL;
    }

    /**
     * Seniors, management, directors, and admins may see every assigned
     * record (any score / any user). Everyone else still sees only their own.
     */
    private function canViewAllRecords(?User $user = null): bool
    {
        $user ??= Auth::user();
        if (! $user) {
            return false;
        }

        if ($this->canAddIssue($user)) {
            return true;
        }

        if (SuperAdminAccess::is($user) || SuperAdminAccess::isTaskAdmin($user)) {
            return true;
        }

        $role = strtolower(trim((string) ($user->role ?? '')));
        if (in_array($role, ['senior', 'director', 'admin', 'manager', 'superadmin'], true)) {
            return true;
        }

        $org = strtolower(trim((string) ($user->org_level ?? '')));
        if (in_array($org, ['director', 'mgr'], true)) {
            return true;
        }

        $designation = strtolower(trim((string) ($user->designation ?? '')));
        if ($designation !== '' && preg_match('/\b(senior|sr\.?\s*manager|manager|management|director)\b/', $designation)) {
            return true;
        }

        return false;
    }

    public function index()
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        $canAddIssue = $this->canAddIssue();
        $canViewAll = $this->canViewAllRecords();
        $currentUserId = Auth::id();

        return view('purchase-master.scope-of-improvement.index', compact('users', 'canAddIssue', 'canViewAll', 'currentUserId'));
    }

    public function data()
    {
        $query = ScopeOfImprovement::with('user:id,name,email');

        // Regular team members see only their own records. Seniors,
        // management, and the president see every assigned record.
        if (!$this->canViewAllRecords()) {
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
            'issues' => 'nullable|array',
            'issues.*' => 'nullable|string',
            'root_cause' => 'nullable|string',
            'fixing_root_cause' => 'nullable|string',
        ]);

        $email = Auth::user()->email ?? 'system';
        $sBy = Auth::user()->name ?? $email;

        // Multi-select submission: one row per selected/typed issue.
        $issues = collect($validated['issues'] ?? [])
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values();

        // Fallback to the legacy single "issue" field if no issues[] was sent.
        if ($issues->isEmpty() && !empty($validated['issue'])) {
            $issues = collect([trim($validated['issue'])]);
        }

        if ($issues->isEmpty()) {
            return response()->json([
                'message' => 'Please select or type at least one issue.',
            ], 422);
        }

        // Skip any issues already filed for this user so the "remaining only"
        // contract holds even if the client is out of sync.
        $existing = ScopeOfImprovement::where('user_id', $validated['user_id'])
            ->pluck('issue')
            ->filter()
            ->map(fn ($v) => (string) $v)
            ->all();

        $createdIds = [];
        foreach ($issues as $issueText) {
            if (in_array($issueText, $existing, true)) {
                continue;
            }
            $row = ScopeOfImprovement::create([
                'user_id'           => $validated['user_id'],
                'issue'             => $issueText,
                'root_cause'        => $validated['root_cause'] ?? null,
                'fixing_root_cause' => $validated['fixing_root_cause'] ?? null,
                's_by'              => $sBy,
                'created_by'        => $email,
                'updated_by'        => $email,
                'history'           => [$this->historyEntry($email, 'created')],
            ]);
            $createdIds[] = $row->id;
            $existing[] = $issueText;
        }

        return response()->json([
            'success'     => true,
            'created'     => count($createdIds),
            'created_ids' => $createdIds,
        ]);
    }

    /**
     * Return the distinct list of issue labels already filed for a given user.
     * Used by the modal to filter the predefined dropdown down to "remaining"
     * issues only.
     */
    public function userIssues($userId)
    {
        $issues = ScopeOfImprovement::where('user_id', $userId)
            ->whereNotNull('issue')
            ->where('issue', '!=', '')
            ->orderBy('issue')
            ->pluck('issue')
            ->unique()
            ->values();

        return response()->json(['issues' => $issues]);
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
