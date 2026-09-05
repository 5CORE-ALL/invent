<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\Dar;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DarController extends Controller
{
    public function index(Request $request)
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        $filterUserId = (int) $request->query('user_id', 0);
        $filterDays = (int) $request->query('days', 0);

        return view('purchase-master.dar.index', compact('users', 'filterUserId', 'filterDays'));
    }

    public function data(Request $request)
    {
        $query = Dar::with('user:id,name,email');

        $userId = (int) $request->query('user_id', 0);
        if ($userId > 0) {
            $query->where('user_id', $userId);
        }

        $days = (int) $request->query('days', 0);
        if ($days > 0) {
            $query->whereDate('report_date', '>=', now()->subDays(max($days, 1) - 1)->toDateString());
        }

        $rows = $query
            ->orderByDesc('report_date')
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'user_id' => $row->user_id,
                    'user_name' => optional($row->user)->name,
                    'report_date' => optional($row->report_date)->format('Y-m-d'),
                    'group' => $row->group,
                    'task' => $row->task,
                    'time_taken' => $row->time_taken,
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
            'report_date' => 'required|date',
            'group' => 'nullable|string|max:255',
            'task' => 'nullable|string',
            'time_taken' => 'nullable|numeric|min:0',
        ]);

        $email = Auth::user()->email ?? 'system';

        $validated['created_by'] = $email;
        $validated['updated_by'] = $email;
        $validated['history'] = [
            $this->historyEntry($email, 'created'),
        ];

        $row = Dar::create($validated);

        return response()->json(['success' => true, 'id' => $row->id]);
    }

    public function update(Request $request, $id)
    {
        $row = Dar::findOrFail($id);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'report_date' => 'required|date',
            'group' => 'nullable|string|max:255',
            'task' => 'nullable|string',
            'time_taken' => 'nullable|numeric|min:0',
        ]);

        $email = Auth::user()->email ?? 'system';

        $history = $row->history ?? [];
        $history[] = $this->historyEntry($email, 'updated');

        $validated['updated_by'] = $email;
        $validated['history'] = $history;

        $row->update($validated);

        return response()->json(['success' => true, 'id' => $row->id]);
    }

    public function destroy($id)
    {
        $row = Dar::findOrFail($id);
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
