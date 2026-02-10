<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Models\DeletedTask;
use App\Services\TaskWhatsAppNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    public function __construct(
        protected TaskWhatsAppNotificationService $taskWhatsApp
    ) {}
    public function index()
    {
        $user = Auth::user();
        $isAdmin = strtolower($user->role ?? '') === 'admin';

        // Build query based on role
        $tasksQuery = Task::query();
        
        if (!$isAdmin) {
            // Normal user: only see tasks they created OR tasks assigned to them
            // Old table uses email fields
            $tasksQuery->where(function($query) use ($user) {
                $query->where('assignor', $user->email)
                      ->orWhere('assign_to', $user->email);
            });
        }

        // Calculate statistics based on filtered tasks
        $overdueQuery = (clone $tasksQuery)->whereNotNull('start_date')
                           ->whereRaw('DATE_ADD(start_date, INTERVAL 10 DAY) < NOW()')
                           ->whereNotIn('status', ['Done', 'Archived']);

        $stats = [
            'total' => (clone $tasksQuery)->count(),
            'pending' => (clone $tasksQuery)->where('status', 'Todo')->count(),
            'overdue' => $overdueQuery->count(),
            'etc_total' => (clone $tasksQuery)->sum('eta_time') ?? 0,
            'atc_total' => (clone $tasksQuery)->sum('etc_done') ?? 0,
            'done' => (clone $tasksQuery)->where('status', 'Done')->count(),
            'done_etc' => (clone $tasksQuery)->where('status', 'Done')->sum('eta_time') ?? 0,
            'done_atc' => (clone $tasksQuery)->where('status', 'Done')->sum('etc_done') ?? 0,
        ];

        // Get all users for filter dropdowns
        $users = User::select('id', 'name')->orderBy('name')->get();

        return view('tasks.index', compact('stats', 'isAdmin', 'users'));
    }

    public function getData()
    {
        $user = Auth::user();
        $isAdmin = strtolower($user->role ?? '') === 'admin';

        $tasksQuery = Task::query();
        
        if (!$isAdmin) {
            // Normal user: only see tasks they created OR tasks assigned to them
            // Old table uses email fields
            $tasksQuery->where(function($query) use ($user) {
                $query->where('assignor', $user->email)
                      ->orWhere('assign_to', $user->email);
            });
        }

        $tasks = $tasksQuery->orderBy('start_date', 'asc')->get();

        // Map emails to names for display
        $tasks->each(function($task) {
            // Find users by email and get their names
            if ($task->assignor) {
                $assignorUser = User::where('email', $task->assignor)->first();
                $task->assignor_name = $assignorUser ? $assignorUser->name : $task->assignor;
                $task->assignor_id = $assignorUser ? $assignorUser->id : null;
            } else {
                $task->assignor_name = '-';
                $task->assignor_id = null;
            }
            
            if ($task->assign_to) {
                // Handle multiple assignees (comma-separated emails)
                $assigneeEmails = array_map('trim', explode(',', $task->assign_to));
                $assigneeNames = [];
                $assigneeIds = [];
                
                foreach ($assigneeEmails as $email) {
                    $assigneeUser = User::where('email', $email)->first();
                    if ($assigneeUser) {
                        $assigneeNames[] = $assigneeUser->name;
                        $assigneeIds[] = $assigneeUser->id;
                    } else {
                        $assigneeNames[] = $email;
                    }
                }
                
                $task->assignee_name = implode(', ', $assigneeNames);
                $task->assignee_id = !empty($assigneeIds) ? $assigneeIds[0] : null; // First ID for compatibility
                $task->assignee_ids = $assigneeIds; // All IDs
                $task->assignee_count = count($assigneeNames);
            } else {
                $task->assignee_name = '-';
                $task->assignee_id = null;
                $task->assignee_ids = [];
                $task->assignee_count = 0;
            }
            
            // For permission checks
            $task->assignor_email = $task->assignor;
            $task->assignee_email = $task->assign_to;
        });

        return response()->json($tasks);
    }

    public function create()
    {
        $users = User::all();
        return view('tasks.create', compact('users'));
    }

    public function store(Request $request)
    {
        // Log what we receive
        \Log::info('Task Create Request:', [
            'assignee_id' => $request->assignee_id,
            'assignee_ids' => $request->assignee_ids,
            'all_data' => $request->all()
        ]);
        
        $validated = $request->validate([
            'title' => 'required|string|max:1000',
            'description' => 'nullable|string',
            'group' => 'nullable|string|max:255',
            'priority' => 'required|in:low,normal,high',
            'assignor_id' => 'nullable|exists:users,id',
            'assignee_id' => 'nullable|exists:users,id',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
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

        // Map to old table field names
        $user = Auth::user();
        $isAdmin = strtolower($user->role ?? '') === 'admin';
        
        // Get assignor email
        if ($isAdmin && $request->has('assignor_id')) {
            $assignorUser = User::find($validated['assignor_id']);
            $assignorEmail = $assignorUser ? $assignorUser->email : $user->email;
        } else {
            $assignorEmail = $user->email;
        }
        
        // Get assignee email(s) - can be multiple, comma-separated
        $assigneeEmail = null;
        $assigneeIds = $request->assignee_ids ?? [];
        
        if (!empty($assigneeIds) && count($assigneeIds) > 0) {
            // Multiple assignees - store as comma-separated emails
            $assigneeEmails = User::whereIn('id', $assigneeIds)->pluck('email')->toArray();
            $assigneeEmail = implode(', ', $assigneeEmails);
            \Log::info('Multiple assignees:', ['ids' => $assigneeIds, 'emails' => $assigneeEmail]);
        } elseif ($request->has('assignee_id') && $validated['assignee_id']) {
            // Single assignee
            $assigneeUser = User::find($validated['assignee_id']);
            $assigneeEmail = $assigneeUser ? $assigneeUser->email : null;
            \Log::info('Single assignee:', ['id' => $validated['assignee_id'], 'email' => $assigneeEmail]);
        } else {
            \Log::warning('No assignee provided');
        }
        
        // Handle image upload
        $imageName = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('uploads/tasks'), $imageName);
        }
        
        // Calculate completion_date = TID + 5 days for manual tasks
        $startDate = $validated['tid'] ?? now();
        $completionDate = \Carbon\Carbon::parse($startDate)->addDays(5);
        
        // Map new fields to old table columns
        $taskData = [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'group' => $validated['group'] ?? null,
            'priority' => $validated['priority'],
            'assignor' => $assignorEmail,
            'assign_to' => $assigneeEmail,
            'split_tasks' => $request->has('split_tasks') ? 1 : 0,
            'status' => 'Todo', // Default status
            'eta_time' => $validated['etc_minutes'] ?? 10,
            'start_date' => $startDate,
            'completion_date' => $completionDate, // TID + 5 days
            'due_date' => $completionDate, // Same as completion_date
            'completion_day' => 0,
            'etc_done' => 0,
            'is_missed' => 0,
            'is_missed_track' => 0,
            'workspace' => 0,
            'order' => 0,
            'task_id' => '',
            'link1' => $validated['l1'] ?? '',
            'link2' => $validated['l2'] ?? '',
            'link3' => $validated['training_link'] ?? '',
            'link4' => $validated['video_link'] ?? '',
            'link5' => $validated['form_link'] ?? '',
            'link6' => $validated['form_report_link'] ?? '',
            'link7' => $validated['checklist_link'] ?? '',
            'link8' => $validated['pl'] ?? '',
            'link9' => $validated['process'] ?? '',
            'image' => $imageName,
            'is_data_from' => 0, // Manual entry
            'is_automate_task' => 0, // Manual task
            'task_type' => 'manual',
            'rework_reason' => '',
            'delete_rating' => 0,
            'delete_feedback' => '',
        ];

        $task = Task::create($taskData);

        $flash = 'success';
        $message = 'Task created successfully!';

        if ($assigneeEmail) {
            try {
                $status = $this->taskWhatsApp->notifyNewTaskAssigned($task);
                if ($status === 'skipped_no_phone') {
                    $message .= ' WhatsApp not sent: assignee has no phone. Add "phone" (digits + country code) in user profile for delivery.';
                    $flash = 'warning';
                } elseif ($status === 'skipped_no_user') {
                    $message .= ' WhatsApp not sent: assignee user not found.';
                    $flash = 'warning';
                } elseif ($status === 'sent') {
                    $message .= ' WhatsApp notification sent to assignee.';
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Task WhatsApp notify new assigned failed: ' . $e->getMessage());
                $message .= ' WhatsApp send failed. Check logs.';
                $flash = 'warning';
            }
        }

        return redirect()->route('tasks.index')->with($flash, $message);
    }

    public function show($id)
    {
        try {
            $task = Task::findOrFail($id);
            
            // Check if user can view this task
            $this->authorize('view', $task);
            
            // Map email to names for display
            $taskData = $task->toArray();
            
            if ($task->assignor) {
                $assignorUser = User::where('email', $task->assignor)->first();
                $taskData['assignor_name'] = $assignorUser ? $assignorUser->name : $task->assignor;
            }
            
            if ($task->assign_to) {
                $assigneeUser = User::where('email', $task->assign_to)->first();
                $taskData['assignee_name'] = $assigneeUser ? $assigneeUser->name : $task->assign_to;
            }
            
            return response()->json($taskData);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['error' => 'Unauthorized'], 403);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Task not found or error loading: ' . $e->getMessage()], 500);
        }
    }

    public function edit($id)
    {
        $taskModel = Task::findOrFail($id);
        
        // Check if user can update this task
        $this->authorize('update', $taskModel);
        
        $users = User::all();
        
        // Create a data object with all mapped fields for the form
        $task = (object)[
            'id' => $taskModel->id,
            'title' => $taskModel->title,
            'description' => $taskModel->description,
            'group' => $taskModel->group,
            'priority' => $taskModel->priority,
            'assignor_id' => null,
            'assignee_id' => null,
            'split_tasks' => $taskModel->split_tasks,
            'flag_raise' => $taskModel->flag_raise ?? 0,
            'etc_minutes' => $taskModel->eta_time ?? 10,
            'tid' => $taskModel->start_date,
            'l1' => $taskModel->link1 ?? '',
            'l2' => $taskModel->link2 ?? '',
            'training_link' => $taskModel->link3 ?? '',
            'video_link' => $taskModel->link4 ?? '',
            'form_link' => $taskModel->link5 ?? '',
            'form_report_link' => $taskModel->link6 ?? '',
            'checklist_link' => $taskModel->link7 ?? '',
            'pl' => $taskModel->link8 ?? '',
            'process' => $taskModel->link9 ?? '',
            'image' => $taskModel->image,
            'assignor' => $taskModel->assignor,
            'assign_to' => $taskModel->assign_to,
        ];
        
        // Map email addresses to user IDs for the form
        if ($taskModel->assignor) {
            $assignorEmail = trim($taskModel->assignor);
            $assignorUser = User::where('email', $assignorEmail)->first();
            $task->assignor_id = $assignorUser ? $assignorUser->id : null;
        }
        
        if ($taskModel->assign_to) {
            // Handle potentially multiple emails (comma-separated) - take the first one
            $assignToEmail = trim($taskModel->assign_to);
            
            // If there are multiple emails, take the first one
            if (strpos($assignToEmail, ',') !== false) {
                $emails = array_map('trim', explode(',', $assignToEmail));
                $assignToEmail = $emails[0];
            }
            
            $assigneeUser = User::where('email', $assignToEmail)->first();
            $task->assignee_id = $assigneeUser ? $assigneeUser->id : null;
        }
        
        return view('tasks.edit', compact('task', 'users'));
    }

    public function update(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        
        // Check if user can update this task
        $this->authorize('update', $task);

        $validated = $request->validate([
            'title' => 'required|string|max:1000',
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

        // Map to old table field names
        $user = Auth::user();
        $isAdmin = strtolower($user->role ?? '') === 'admin';
        
        // Get assignor email
        if ($isAdmin && $request->has('assignor_id')) {
            $assignorUser = User::find($validated['assignor_id']);
            $assignorEmail = $assignorUser ? $assignorUser->email : $task->assignor;
        } else {
            $assignorEmail = $task->assignor;
        }
        
        // Get assignee email
        $assigneeEmail = $task->assign_to;
        if ($request->has('assignee_id')) {
            if ($validated['assignee_id']) {
                $assigneeUser = User::find($validated['assignee_id']);
                $assigneeEmail = $assigneeUser ? $assigneeUser->email : null;
            } else {
                $assigneeEmail = null;
            }
        }
        
        // Handle image upload
        $imageName = $task->image;
        if ($request->hasFile('image')) {
            // Delete old image
            if ($task->image && file_exists(public_path('uploads/tasks/' . $task->image))) {
                unlink(public_path('uploads/tasks/' . $task->image));
            }
            
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('uploads/tasks'), $imageName);
        }
        
        // Map new fields to old table columns
        $updateData = [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'group' => $validated['group'] ?? null,
            'priority' => $validated['priority'],
            'assignor' => $assignorEmail,
            'assign_to' => $assigneeEmail,
            'split_tasks' => $request->has('split_tasks') ? 1 : 0,
            'eta_time' => $validated['etc_minutes'] ?? $task->eta_time,
            'start_date' => $validated['tid'] ?? $task->start_date,
            'link1' => $validated['l1'] ?? '',
            'link2' => $validated['l2'] ?? '',
            'link3' => $validated['training_link'] ?? '',
            'link4' => $validated['video_link'] ?? '',
            'link5' => $validated['form_link'] ?? '',
            'link6' => $validated['form_report_link'] ?? '',
            'link7' => $validated['checklist_link'] ?? '',
            'link8' => $validated['pl'] ?? '',
            'link9' => $validated['process'] ?? '',
            'image' => $imageName,
        ];

        $relevantChanged = $this->taskDetailsChanged($task, $updateData);

        $task->update($updateData);

        if ($relevantChanged && $assigneeEmail) {
            try {
                $this->taskWhatsApp->notifyTaskUpdated($task->fresh());
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Task WhatsApp notify updated failed: ' . $e->getMessage());
            }
        }

        return redirect()->route('tasks.index')->with('success', 'Task updated successfully!');
    }

    public function destroy($id)
    {
        $task = Task::findOrFail($id);
        
        // Check if user can delete this task
        $this->authorize('delete', $task);
        
        // Save task to deleted_tasks before deletion
        $this->saveDeletedTask($task);
        
        $task->delete();

        return response()->json(['success' => true, 'message' => 'Task deleted successfully!']);
    }

    public function updateStatus(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        
        // Check if user can update status
        $this->authorize('updateStatus', $task);

        $validated = $request->validate([
            'status' => 'required|in:Todo,Working,Archived,Done,Need Help,Need Approval,Dependent,Approved,Hold,Rework',
            'atc' => 'nullable|integer',
            'rework_reason' => 'nullable|string',
        ]);

        $task->status = $validated['status'];

        // If status is Done, save ATC and completion time
        if ($validated['status'] === 'Done') {
            if (isset($validated['atc'])) {
                $task->etc_done = $validated['atc']; // Map to old column name
            }
            $task->completion_date = now(); // Map to old column name
            
            // Calculate completion days
            if ($task->start_date) {
                $startDate = \Carbon\Carbon::parse($task->start_date);
                $task->completion_day = $startDate->diffInDays(now());
            }
        }

        // If reason is provided (for any status change or rework)
        if (isset($validated['rework_reason']) && !empty($validated['rework_reason'])) {
            $task->rework_reason = $validated['rework_reason'];
        }

        $task->save();

        if ($validated['status'] === 'Done') {
            try {
                $this->taskWhatsApp->notifyTaskDone($task->fresh());
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Task WhatsApp notify done failed: ' . $e->getMessage());
            }
        } elseif ($validated['status'] === 'Rework') {
            try {
                $this->taskWhatsApp->notifyRework($task->fresh());
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Task WhatsApp notify rework failed: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully!',
            'task' => $task->fresh()
        ]);
    }

    public function bulkUpdate(Request $request)
    {
        $user = Auth::user();
        $isAdmin = strtolower($user->role ?? '') === 'admin';

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

        switch ($action) {
            case 'delete':
                // Only allow deletion of tasks where user is the assignor
                $tasksToDelete = Task::whereIn('id', $taskIds)
                    ->where('assignor', $user->email)
                    ->get();
                
                $deletedCount = $tasksToDelete->count();
                $requestedCount = count($taskIds);
                
                if ($deletedCount === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You can only delete tasks you created. None of the selected tasks belong to you.'
                    ], 403);
                }
                
                // Save all tasks to deleted_tasks before deletion
                foreach ($tasksToDelete as $task) {
                    $this->saveDeletedTask($task);
                }
                
                Task::whereIn('id', $tasksToDelete->pluck('id'))->delete();
                
                $message = "$deletedCount task(s) deleted successfully!";
                if ($deletedCount < $requestedCount) {
                    $skipped = $requestedCount - $deletedCount;
                    $message .= " ($skipped task(s) skipped - you can only delete tasks you created)";
                }
                
                return response()->json([
                    'success' => true,
                    'message' => $message
                ]);

            case 'priority':
            case 'tid':
            case 'assignee':
            case 'etc':
                // Other bulk operations require admin privileges
                if (!$isAdmin) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized. Only administrators can perform bulk updates.'
                    ], 403);
                }
                
                $count = count($taskIds);
                
                if ($action === 'priority') {
                    Task::whereIn('id', $taskIds)->update(['priority' => $validated['priority']]);
                    return response()->json([
                        'success' => true,
                        'message' => "$count task(s) priority updated to " . $validated['priority'] . "!"
                    ]);
                } elseif ($action === 'tid') {
                    Task::whereIn('id', $taskIds)->update(['start_date' => $validated['tid']]);
                    return response()->json([
                        'success' => true,
                        'message' => "$count task(s) TID date updated!"
                    ]);
                } elseif ($action === 'assignee') {
                    $assigneeUser = User::find($validated['assignee_id']);
                    if ($assigneeUser) {
                        Task::whereIn('id', $taskIds)->update(['assign_to' => $assigneeUser->email]);
                    }
                    return response()->json([
                        'success' => true,
                        'message' => "$count task(s) assignee updated!"
                    ]);
                } elseif ($action === 'etc') {
                    Task::whereIn('id', $taskIds)->update(['eta_time' => $validated['etc_minutes']]);
                    return response()->json([
                        'success' => true,
                        'message' => "$count task(s) ETC updated!"
                    ]);
                }
                break;

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

    /**
     * Check if task details relevant to "task updated" WhatsApp (title, dates, ETA, assignee) changed.
     */
    private function taskDetailsChanged(Task $task, array $updateData): bool
    {
        $startNew = isset($updateData['start_date']) ? \Carbon\Carbon::parse($updateData['start_date']) : null;
        $startOld = $task->start_date ? \Carbon\Carbon::parse($task->start_date) : null;
        if ($startNew != $startOld) {
            return true;
        }
        if (($updateData['title'] ?? null) !== null && (string) $updateData['title'] !== (string) $task->title) {
            return true;
        }
        if (array_key_exists('eta_time', $updateData) && (int) ($updateData['eta_time'] ?? 0) !== (int) ($task->eta_time ?? 0)) {
            return true;
        }
        if (array_key_exists('assign_to', $updateData) && ($updateData['assign_to'] ?? null) != ($task->assign_to ?? null)) {
            return true;
        }
        return false;
    }

    // Automated Tasks Methods
    public function automatedIndex()
    {
        $user = Auth::user();
        $isAdmin = strtolower($user->role ?? '') === 'admin';

        // Get all users for filter dropdowns
        $users = User::select('id', 'name')->orderBy('name')->get();

        // Calculate statistics for automated tasks
        $automatedQuery = \DB::table('automate_tasks');
        
        if (!$isAdmin) {
            $automatedQuery->where(function($query) use ($user) {
                $query->where('assignor', $user->email)
                      ->orWhere('assign_to', $user->email);
            });
        }

        $stats = [
            'total' => (clone $automatedQuery)->count(),
            'daily' => (clone $automatedQuery)->where('schedule_type', 'daily')->count(),
            'weekly' => (clone $automatedQuery)->where('schedule_type', 'weekly')->count(),
            'monthly' => (clone $automatedQuery)->where('schedule_type', 'monthly')->count(),
            'active' => (clone $automatedQuery)->where('status', 'Todo')->count(),
        ];

        return view('tasks.automated', compact('stats', 'isAdmin', 'users'));
    }

    public function getAutomatedData()
    {
        $user = Auth::user();
        $isAdmin = strtolower($user->role ?? '') === 'admin';

        $query = \DB::table('automate_tasks');
        
        if (!$isAdmin) {
            $query->where(function($q) use ($user) {
                $q->where('assignor', $user->email)
                  ->orWhere('assign_to', $user->email);
            });
        }

        $tasks = $query->orderBy('id', 'desc')->get();

        // Map emails to names
        $tasks->each(function($task) {
            if ($task->assignor) {
                $assignorUser = User::where('email', $task->assignor)->first();
                $task->assignor_name = $assignorUser ? $assignorUser->name : $task->assignor;
            } else {
                $task->assignor_name = '-';
            }
            
            if ($task->assign_to) {
                $assigneeUser = User::where('email', $task->assign_to)->first();
                $task->assignee_name = $assigneeUser ? $assigneeUser->name : $task->assign_to;
            } else {
                $task->assignee_name = '-';
            }
        });

        return response()->json($tasks);
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

                // Map status values (keep old format)
                $statusMap = [
                    'todo' => 'Todo',
                    'working' => 'Working',
                    'archived' => 'Archived',
                    'done' => 'Done',
                    'need help' => 'Need Help',
                    'need approval' => 'Need Approval',
                    'dependent' => 'Dependent',
                    'approved' => 'Approved',
                    'hold' => 'Hold',
                    'rework' => 'Rework',
                ];
                $status = $statusMap[strtolower($status)] ?? 'Todo';

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

    public function automatedCreate()
    {
        $users = User::all();
        return view('tasks.automated-create', compact('users'));
    }

    public function automatedStore(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'group' => 'nullable|string',
            'priority' => 'required|in:Low,Normal,High,Urgent',
            'assignor_id' => 'nullable|exists:users,id',
            'assignee_id' => 'nullable|exists:users,id',
            'etc_minutes' => 'nullable|integer',
            'tid' => 'nullable|date',
            'schedule_type' => 'required|in:daily,weekly,monthly',
            'schedule_time' => 'nullable',
            'schedule_days' => 'nullable|string',
            'l1' => 'nullable|string',
            'l2' => 'nullable|string',
            'training_link' => 'nullable|string',
            'video_link' => 'nullable|string',
            'form_link' => 'nullable|string',
            'form_report_link' => 'nullable|string',
            'checklist_link' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $user = Auth::user();
        $isAdmin = strtolower($user->role ?? '') === 'admin';
        
        $assignorEmail = $isAdmin && $request->has('assignor_id') 
            ? User::find($validated['assignor_id'])->email ?? $user->email
            : $user->email;
        
        $assigneeEmail = $request->has('assignee_id') && $validated['assignee_id']
            ? User::find($validated['assignee_id'])->email ?? null
            : null;
        
        // Insert into automate_tasks table (ONLY fields that exist in this table)
        $automateTaskId = \DB::table('automate_tasks')->insertGetId([
            'title' => $validated['title'],
            'group' => $validated['group'],
            'priority' => $validated['priority'],
            'description' => $validated['description'],
            'assignor' => $assignorEmail,
            'assign_to' => $assigneeEmail,
            'eta_time' => $validated['etc_minutes'] ?? 10,
            'start_date' => $validated['tid'] ?? now(),
            'due_date' => $validated['tid'] ?? now(),
            'split_tasks' => $request->has('split_tasks') ? 1 : 0,
            'schedule_type' => $validated['schedule_type'],
            'schedule_time' => $validated['schedule_time'],
            'schedule_days' => $validated['schedule_days'] ?? '',
            'status' => 'Todo',
            'link1' => $validated['l1'],
            'link2' => $validated['l2'],
            'link3' => $validated['training_link'],
            'link4' => $validated['video_link'],
            'link5' => $validated['form_link'],
            'link6' => $validated['form_report_link'],
            'link7' => $validated['checklist_link'],
            'workspace' => 0,
            'order' => 0,
            'is_pause' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Note: Task instances will be created automatically by the scheduler when it's time to execute
        // No immediate insert into tasks table

        $scheduleInfo = $validated['schedule_type'];
        if ($request->input('schedule_days')) {
            $scheduleInfo .= ' (' . $request->input('schedule_days') . ')';
        }
        $scheduleInfo .= ' at ' . ($validated['schedule_time'] ?? 'scheduled time');

        return redirect()->route('tasks.automated')->with('success', 'Automated task scheduled! Will execute: ' . $scheduleInfo);
    }

    public function automatedEdit($id)
    {
        $task = \DB::table('automate_tasks')->where('id', $id)->first();
        
        if (!$task) {
            return redirect()->route('tasks.automated')->with('error', 'Automated task not found');
        }
        
        $users = User::all();
        return view('tasks.automated-edit', compact('task', 'users'));
    }

    public function automatedUpdate(Request $request, $id)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'group' => 'nullable|string',
            'priority' => 'required|in:Low,Normal,High,Urgent',
            'etc_minutes' => 'nullable|integer',
            'schedule_type' => 'required|in:daily,weekly,monthly',
            'schedule_days' => 'nullable|string',
            'schedule_time' => 'nullable',
        ]);

        // Update automate_tasks table
        \DB::table('automate_tasks')->where('id', $id)->update([
            'title' => $validated['title'],
            'group' => $validated['group'],
            'priority' => $validated['priority'],
            'eta_time' => $validated['etc_minutes'] ?? 10,
            'schedule_type' => $validated['schedule_type'],
            'schedule_days' => $validated['schedule_days'] ?? '',
            'schedule_time' => $validated['schedule_time'],
            'updated_at' => now(),
        ]);

        // Update any existing executed instances in tasks table
        \DB::table('tasks')->where('automate_task_id', $id)->update([
            'title' => $validated['title'],
            'group' => $validated['group'],
            'priority' => $validated['priority'],
            'eta_time' => $validated['etc_minutes'] ?? 10,
            'schedule_type' => $validated['schedule_type'],
            'updated_at' => now(),
        ]);

        return redirect()->route('tasks.automated')->with('success', 'Automated task updated! New schedule will take effect immediately.');
    }

    public function automatedDestroy($id)
    {
        // Delete from automate_tasks
        \DB::table('automate_tasks')->where('id', $id)->delete();
        
        // Also delete any executed instances from tasks table
        \DB::table('tasks')->where('automate_task_id', $id)->delete();

        return response()->json(['success' => true, 'message' => 'Automated task and all its instances deleted!']);
    }

    /**
     * Save task to deleted_tasks table before deletion
     */
    private function saveDeletedTask(Task $task)
    {
        $user = Auth::user();
        
        // Get assignor and assignee names
        $assignorUser = User::where('email', $task->assignor)->first();
        $assigneeUser = User::where('email', $task->assign_to)->first();
        
        DeletedTask::create([
            'original_task_id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'group' => $task->group,
            'priority' => $task->priority,
            'status' => $task->status,
            'assignor' => $task->assignor,
            'assign_to' => $task->assign_to,
            'assignor_name' => $assignorUser ? $assignorUser->name : $task->assignor,
            'assignee_name' => $assigneeUser ? $assigneeUser->name : $task->assign_to,
            'eta_time' => $task->eta_time,
            'etc_done' => $task->etc_done,
            'start_date' => $task->start_date,
            'completion_date' => $task->completion_date,
            'completion_day' => $task->completion_day,
            'split_tasks' => $task->split_tasks,
            'is_missed' => $task->is_missed,
            'is_missed_track' => $task->is_missed_track,
            'link1' => $task->link1,
            'link2' => $task->link2,
            'link3' => $task->link3,
            'link4' => $task->link4,
            'link5' => $task->link5,
            'link6' => $task->link6,
            'link7' => $task->link7,
            'link8' => $task->link8,
            'link9' => $task->link9,
            'image' => $task->image,
            'task_type' => $task->task_type,
            'rework_reason' => $task->rework_reason,
            'deleted_by_email' => $user->email,
            'deleted_by_name' => $user->name,
            'deleted_at' => now(),
        ]);
    }

    /**
     * Display deleted tasks page
     */
    public function deletedIndex()
    {
        $user = Auth::user();
        $isAdmin = strtolower($user->role ?? '') === 'admin';

        // Calculate statistics for deleted tasks
        $deletedQuery = DeletedTask::query();
        
        if (!$isAdmin) {
            // Non-admin users can only see deleted tasks they created or were assigned to
            $deletedQuery->where(function($query) use ($user) {
                $query->where('assignor', $user->email)
                      ->orWhere('assign_to', $user->email);
            });
        }

        $stats = [
            'total' => (clone $deletedQuery)->count(),
            'this_month' => (clone $deletedQuery)->whereMonth('deleted_at', now()->month)->count(),
            'this_week' => (clone $deletedQuery)->whereBetween('deleted_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'today' => (clone $deletedQuery)->whereDate('deleted_at', today())->count(),
        ];

        return view('tasks.deleted', compact('stats', 'isAdmin'));
    }

    /**
     * Get deleted tasks data for table
     */
    public function deletedData()
    {
        $user = Auth::user();
        $isAdmin = strtolower($user->role ?? '') === 'admin';

        $query = DeletedTask::query();
        
        if (!$isAdmin) {
            $query->where(function($q) use ($user) {
                $q->where('assignor', $user->email)
                  ->orWhere('assign_to', $user->email);
            });
        }

        $deletedTasks = $query->orderBy('deleted_at', 'desc')->get();

        return response()->json($deletedTasks);
    }
    
}
