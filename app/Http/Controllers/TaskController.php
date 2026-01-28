<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $isAdmin = strtolower($user->role ?? '') === 'admin';

        // Build query based on role
        $tasksQuery = Task::query();
        
        if (!$isAdmin) {
            // Normal user: only see tasks they created OR tasks assigned to them
            $tasksQuery->where(function($query) use ($user) {
                $query->where('assignor_id', $user->id)
                      ->orWhere('assignee_id', $user->id);
            });
        }

        // Calculate statistics based on filtered tasks
        $overdueQuery = (clone $tasksQuery)->whereNotNull('tid')
                           ->whereRaw('DATE_ADD(tid, INTERVAL 10 DAY) < NOW()')
                           ->whereNotIn('status', ['completed', 'cancelled']);

        $stats = [
            'total' => (clone $tasksQuery)->count(),
            'pending' => (clone $tasksQuery)->where('status', 'pending')->count(),
            'overdue' => $overdueQuery->count(),
            'etc_total' => (clone $tasksQuery)->sum('etc_minutes') ?? 0,
            'atc_total' => (clone $tasksQuery)->sum('atc') ?? 0,
            'done' => (clone $tasksQuery)->where('status', 'completed')->count(),
            'done_etc' => (clone $tasksQuery)->where('status', 'completed')->sum('etc_minutes') ?? 0,
            'done_atc' => (clone $tasksQuery)->where('status', 'completed')->sum('atc') ?? 0,
        ];

        // Get all users for filter dropdowns
        $users = User::select('id', 'name')->orderBy('name')->get();

        return view('tasks.index', compact('stats', 'isAdmin', 'users'));
    }

    public function getData()
    {
        $user = Auth::user();
        $isAdmin = strtolower($user->role ?? '') === 'admin';

        $tasksQuery = Task::with(['assignor', 'assignee']);
        
        if (!$isAdmin) {
            // Normal user: only see tasks they created OR tasks assigned to them
            $tasksQuery->where(function($query) use ($user) {
                $query->where('assignor_id', $user->id)
                      ->orWhere('assignee_id', $user->id);
            });
        }

        $tasks = $tasksQuery->orderBy('id', 'desc')->get();

        return response()->json($tasks);
    }

    public function create()
    {
        $users = User::all();
        return view('tasks.create', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'group' => 'nullable|string|max:255',
            'priority' => 'required|in:low,normal,high',
            'assignor_id' => 'nullable|exists:users,id',
            'assignee_id' => 'nullable|exists:users,id',
            'split_tasks' => 'nullable|boolean',
            'flag_raise' => 'nullable|boolean',
            'etc_minutes' => 'nullable|integer',
            'tid' => 'nullable|date',
            'l1' => 'nullable|string',
            'l2' => 'nullable|string',
            'training_link' => 'nullable|string',
            'video_link' => 'nullable|string',
            'form_link' => 'nullable|string',
            'form_report_link' => 'nullable|string',
            'checklist_link' => 'nullable|string',
            'pl' => 'nullable|string',
            'process' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Set assignor_id
        $user = Auth::user();
        $isAdmin = strtolower($user->role ?? '') === 'admin';
        
        if ($isAdmin && $request->has('assignor_id')) {
            // Admin can set custom assignor
            $validated['assignor_id'] = $validated['assignor_id'];
        } else {
            // Normal user: assignor is always themselves
            $validated['assignor_id'] = Auth::id();
        }
        
        // Set default status to pending for new tasks
        $validated['status'] = 'pending';
        
        $validated['split_tasks'] = $request->has('split_tasks') ? 1 : 0;
        $validated['flag_raise'] = $request->has('flag_raise') ? 1 : 0;

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('uploads/tasks'), $imageName);
            $validated['image'] = $imageName;
        }

        Task::create($validated);

        return redirect()->route('tasks.index')->with('success', 'Task created successfully!');
    }

    public function show($id)
    {
        $task = Task::with(['assignor', 'assignee'])->findOrFail($id);
        
        // Check if user can view this task
        $this->authorize('view', $task);
        
        return response()->json($task);
    }

    public function edit($id)
    {
        $task = Task::findOrFail($id);
        
        // Check if user can update this task
        $this->authorize('update', $task);
        
        $users = User::all();
        return view('tasks.edit', compact('task', 'users'));
    }

    public function update(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        
        // Check if user can update this task
        $this->authorize('update', $task);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'group' => 'nullable|string|max:255',
            'priority' => 'required|in:low,normal,high',
            'assignor_id' => 'nullable|exists:users,id',
            'assignee_id' => 'nullable|exists:users,id',
            'split_tasks' => 'nullable|boolean',
            'flag_raise' => 'nullable|boolean',
            'etc_minutes' => 'nullable|integer',
            'tid' => 'nullable|date',
            'l1' => 'nullable|string',
            'l2' => 'nullable|string',
            'training_link' => 'nullable|string',
            'video_link' => 'nullable|string',
            'form_link' => 'nullable|string',
            'form_report_link' => 'nullable|string',
            'checklist_link' => 'nullable|string',
            'pl' => 'nullable|string',
            'process' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Handle assignor_id (only admin can change it)
        $user = Auth::user();
        $isAdmin = strtolower($user->role ?? '') === 'admin';
        
        if (!$isAdmin || !$request->has('assignor_id')) {
            // Keep original assignor if not admin or not provided
            $validated['assignor_id'] = $task->assignor_id;
        }

        $validated['split_tasks'] = $request->has('split_tasks') ? 1 : 0;
        $validated['flag_raise'] = $request->has('flag_raise') ? 1 : 0;

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image
            if ($task->image && file_exists(public_path('uploads/tasks/' . $task->image))) {
                unlink(public_path('uploads/tasks/' . $task->image));
            }
            
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('uploads/tasks'), $imageName);
            $validated['image'] = $imageName;
        }

        $task->update($validated);

        return redirect()->route('tasks.index')->with('success', 'Task updated successfully!');
    }

    public function destroy($id)
    {
        $task = Task::findOrFail($id);
        
        // Check if user can delete this task
        $this->authorize('delete', $task);
        
        $task->delete();

        return response()->json(['success' => true, 'message' => 'Task deleted successfully!']);
    }

    public function updateStatus(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        
        // Check if user can update status
        $this->authorize('updateStatus', $task);

        $validated = $request->validate([
            'status' => 'required|in:pending,in_progress,archived,completed,need_help,need_approval,dependent,approved,hold,cancelled',
            'atc' => 'nullable|integer',
            'rework_reason' => 'nullable|string',
        ]);

        $task->status = $validated['status'];

        // If status is completed (done), save ATC and completion time
        if ($validated['status'] === 'completed') {
            if (isset($validated['atc'])) {
                $task->atc = $validated['atc'];
            }
            $task->completed_at = now();
        }

        // If reason is provided (for any status change or rework)
        if (isset($validated['rework_reason']) && !empty($validated['rework_reason'])) {
            $task->rework_reason = $validated['rework_reason'];
        }

        $task->save();

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully!',
            'task' => $task->fresh()
        ]);
    }

    public function bulkUpdate(Request $request)
    {
        // Check if user is admin (only admin can perform bulk operations)
        $user = Auth::user();
        $isAdmin = strtolower($user->role ?? '') === 'admin';
        
        if (!$isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only administrators can perform bulk operations.'
            ], 403);
        }

        $validated = $request->validate([
            'action' => 'required|in:delete,priority,tid,assignee,etc',
            'task_ids' => 'required|array',
            'task_ids.*' => 'exists:tasks,id',
            'priority' => 'nullable|in:low,normal,high',
            'tid' => 'nullable|date',
            'assignee_id' => 'nullable|exists:users,id',
            'etc_minutes' => 'nullable|integer|min:1',
        ]);

        $taskIds = $validated['task_ids'];
        $action = $validated['action'];
        $count = count($taskIds);

        switch ($action) {
            case 'delete':
                Task::whereIn('id', $taskIds)->delete();
                return response()->json([
                    'success' => true,
                    'message' => "$count task(s) deleted successfully!"
                ]);

            case 'priority':
                Task::whereIn('id', $taskIds)->update(['priority' => $validated['priority']]);
                return response()->json([
                    'success' => true,
                    'message' => "$count task(s) priority updated to " . $validated['priority'] . "!"
                ]);

            case 'tid':
                Task::whereIn('id', $taskIds)->update(['tid' => $validated['tid']]);
                return response()->json([
                    'success' => true,
                    'message' => "$count task(s) TID date updated!"
                ]);

            case 'assignee':
                Task::whereIn('id', $taskIds)->update(['assignee_id' => $validated['assignee_id']]);
                return response()->json([
                    'success' => true,
                    'message' => "$count task(s) assignee updated!"
                ]);

            case 'etc':
                Task::whereIn('id', $taskIds)->update(['etc_minutes' => $validated['etc_minutes']]);
                return response()->json([
                    'success' => true,
                    'message' => "$count task(s) ETC updated!"
                ]);

            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid action'
                ], 400);
        }
    }

    public function getUsersList()
    {
        $users = User::select('id', 'name')->get();
        return response()->json($users);
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="task_import_template.csv"',
        ];

        $columns = ['Group', 'Task', 'Assignor', 'Assignee', 'Status', 'Priority', 'Image', 'Links'];
        $sampleData = [
            ['Marketplaces', 'Sample Task 1', 'John Doe', 'Jane Smith', 'Todo', 'Normal', '', 'https://example.com'],
            ['Development', 'Sample Task 2', 'Jane Smith', 'John Doe', 'Working', 'High', '', 'L1: https://link1.com'],
        ];

        $callback = function() use ($columns, $sampleData) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            foreach ($sampleData as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function importCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');
        
        // Skip header row
        $header = fgetcsv($handle);
        
        $imported = 0;
        $skipped = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            try {
                // Map CSV columns: Group, Task, Assignor, Assignee, Status, Priority, Image, Links
                $group = $row[0] ?? null;
                $title = $row[1] ?? null;
                $assignorName = $row[2] ?? null;
                $assigneeName = $row[3] ?? null;
                $status = $row[4] ?? 'pending';
                $priority = $row[5] ?? 'normal';
                $image = $row[6] ?? null;
                $links = $row[7] ?? null;

                // Skip if no title
                if (empty($title)) {
                    $skipped++;
                    continue;
                }

                // Find users by name
                $assignor = User::where('name', 'LIKE', '%' . $assignorName . '%')->first();
                $assignee = User::where('name', 'LIKE', '%' . $assigneeName . '%')->first();

                if (!$assignor) {
                    $assignor = Auth::user(); // Default to current user
                }

                // Map status values
                $statusMap = [
                    'todo' => 'pending',
                    'working' => 'in_progress',
                    'archived' => 'archived',
                    'done' => 'completed',
                    'need help' => 'need_help',
                    'need approval' => 'need_approval',
                    'dependent' => 'dependent',
                    'approved' => 'approved',
                    'hold' => 'hold',
                    'cancelled' => 'cancelled',
                ];
                $status = $statusMap[strtolower($status)] ?? 'pending';

                // Map priority
                $priorityMap = [
                    'urgent' => 'high',
                    'high' => 'high',
                    'normal' => 'normal',
                    'low' => 'low',
                ];
                $priority = $priorityMap[strtolower($priority)] ?? 'normal';

                // Parse links (format: "L1: url")
                $l1 = null;
                if ($links && preg_match('/L1:\s*(.+)/i', $links, $matches)) {
                    $l1 = trim($matches[1]);
                }

                // Create task
                Task::create([
                    'title' => $title,
                    'group' => $group,
                    'assignor_id' => $assignor->id,
                    'assignee_id' => $assignee ? $assignee->id : null,
                    'status' => $status,
                    'priority' => $priority,
                    'l1' => $l1,
                    'etc_minutes' => 10, // Default
                    'tid' => now(),
                ]);

                $imported++;
            } catch (\Exception $e) {
                $skipped++;
                $errors[] = 'Row ' . ($imported + $skipped) . ': ' . $e->getMessage();
            }
        }

        fclose($handle);

        return response()->json([
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => "$imported task(s) imported successfully!",
        ]);
    }
}
