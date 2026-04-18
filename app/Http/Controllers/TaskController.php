<?php

namespace App\Http\Controllers;

use App\Models\PerformanceReview;
use App\Models\Task;
use App\Models\User;
use App\Models\UserRR;
use App\Models\DeletedTask;
use App\Policies\TaskPolicy;
use App\Services\TaskWhatsAppNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function __construct(
        protected TaskWhatsAppNotificationService $taskWhatsApp
    ) {}
    public function index()
    {
        $user = Auth::user();
        $isAdmin = strtolower($user->role ?? '') === 'admin';

        $tasksQuery = $this->taskManagerVisibilityQuery();

        // Get selected user from session (set from user selection) - CHECK THIS FIRST
        $selectedUserName = Session::get('selected_user_name', '');
        $selectedUserEmail = null;
        if ($selectedUserName) {
            $selectedUser = User::where('name', $selectedUserName)->first();
            $selectedUserEmail = $selectedUser ? $selectedUser->email : null;
        }

        // Filter tasks query by selected user if set (for all stats cards)
        if ($selectedUserEmail) {
            $tasksQuery->where(function($query) use ($selectedUserEmail) {
                $query->where('assignor', $selectedUserEmail)
                      ->orWhere('assign_to', 'LIKE', '%' . $selectedUserEmail . '%');
            });
        }

        // Calculate statistics based on filtered tasks (with user filter if selected)
        // Overdue means TID/start_date + 1 day grace is already past.
        // Keep only archived tasks excluded from overdue stats.
        $overdueQuery = (clone $tasksQuery)->whereNotNull('start_date')
                           ->whereRaw('DATE_ADD(start_date, INTERVAL 1 DAY) < NOW()')
                           ->where('status', '!=', 'Archived');

        // Calculate statistics based on filtered tasks (with user filter if selected)
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

        // 30-day ETC/ATC badges from deleted_tasks only (by deleted_at)
        $deletedLast30ForTimeQuery = DeletedTask::query()
            ->where('deleted_at', '>=', now()->subDays(30));

        if (!$isAdmin) {
            $deletedLast30ForTimeQuery->where(function($query) use ($user) {
                $query->where('assignor', $user->email)
                    ->orWhere('assign_to', 'LIKE', '%' . $user->email . '%');
            });
        }

        if ($selectedUserEmail) {
            $deletedLast30ForTimeQuery->where(function($query) use ($selectedUserEmail) {
                $query->where('assignor', $selectedUserEmail)
                    ->orWhere('assign_to', 'LIKE', '%' . $selectedUserEmail . '%');
            });
        }

        $deletedEtcLast30 = (clone $deletedLast30ForTimeQuery)->sum('eta_time') ?? 0;
        $deletedAtcLast30 = (clone $deletedLast30ForTimeQuery)->sum('etc_done') ?? 0;

        $stats['etc_last_30'] = (float) $deletedEtcLast30;
        $stats['atc_last_30'] = (float) $deletedAtcLast30;

        // Calculate R&R hours (tasks with group containing "R&R" or "R&R" in group name)
        $rrQuery = (clone $tasksQuery)->where(function($q) {
            $q->where('group', 'LIKE', '%R&R%')
              ->orWhere('group', 'LIKE', '%R and R%')
              ->orWhere('group', 'LIKE', '%Roles%Responsibilities%');
        });
        $stats['rr'] = $rrQuery->sum('eta_time') ?? 0;
        $stats['etc_rr'] = $stats['rr']; // Alias for backward compatibility

        // Get all users for filter dropdowns (include email, avatar for user-select card)
        $users = User::where('is_active', true)
            ->select('id', 'name', 'email', 'avatar')
            ->orderBy('name')
            ->get();

        // Assignor/assignee roles from visible tasks (for "Select user" dropdown labels)
        $baseTasksQuery = $this->taskManagerVisibilityQuery();
        $assignorEmails = (clone $baseTasksQuery)->whereNotNull('assignor')->where('assignor', '!=', '')
            ->distinct()->pluck('assignor')->values()->all();
        $assigneeEmails = (clone $baseTasksQuery)->whereNotNull('assign_to')->where('assign_to', '!=', '')
            ->pluck('assign_to')
            ->flatMap(function ($assignTo) {
                return array_map('trim', explode(',', $assignTo));
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
        foreach ($users as $u) {
            $u->is_assignor = in_array($u->email, $assignorEmails, true);
            $u->is_assignee = in_array($u->email, $assigneeEmails, true);
        }

        $assignorOnTasksUsers = $users->filter(fn ($u) => $u->is_assignor)->sortBy('name')->values();
        $assignorOtherUsers = $users->filter(fn ($u) => ! $u->is_assignor)->sortBy('name')->values();
        $assigneeOnTasksUsers = $users->filter(fn ($u) => $u->is_assignee)->sortBy('name')->values();
        $assigneeOtherUsers = $users->filter(fn ($u) => ! $u->is_assignee)->sortBy('name')->values();

        // TAT badge: average TAT (days from start_date to completion_date) for Done tasks completed in last 30 days
        $last30DoneQuery = (clone $tasksQuery)
            ->where('status', 'Done')
            ->whereNotNull('start_date')
            ->where(function($q) {
                $q->whereNotNull('completion_date')
                  ->where('completion_date', '>=', now()->subDays(30))
                  ->orWhere(function($q2) {
                      $q2->whereNull('completion_date')
                         ->where('updated_at', '>=', now()->subDays(30));
                  });
            });
        // Filter by selected user if set (search in both assignor and assign_to)
        if ($selectedUserEmail) {
            $last30DoneQuery->where(function($query) use ($selectedUserEmail) {
                $query->where('assignor', $selectedUserEmail)
                      ->orWhere('assign_to', 'LIKE', '%' . $selectedUserEmail . '%');
            });
        }
        $last30DoneTasks = $last30DoneQuery->get();
        $tatValues = [];
        foreach ($last30DoneTasks as $task) {
            $start = \Carbon\Carbon::parse($task->start_date);
            $completion = $task->completion_date 
                ? \Carbon\Carbon::parse($task->completion_date)
                : \Carbon\Carbon::parse($task->updated_at);
            $days = abs($completion->getTimestamp() - $start->getTimestamp()) / 86400;
            $tatValues[] = (int) round($days);
        }
        $stats['tat_avg_30'] = count($tatValues) > 0 ? (int) round(array_sum($tatValues) / count($tatValues)) : null;

        // Daily TAT for line chart (last 30 days): date => avg TAT for tasks completed on that day
        $tatByDay = [];
        foreach ($last30DoneTasks as $task) {
            $completion = $task->completion_date 
                ? \Carbon\Carbon::parse($task->completion_date)
                : \Carbon\Carbon::parse($task->updated_at);
            $day = $completion->format('Y-m-d');
            $start = \Carbon\Carbon::parse($task->start_date);
            $days = abs($completion->getTimestamp() - $start->getTimestamp()) / 86400;
            if (!isset($tatByDay[$day])) {
                $tatByDay[$day] = [];
            }
            $tatByDay[$day][] = (int) round($days);
        }
        $tatChartData = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = now()->subDays($i);
            $key = $d->format('Y-m-d');
            $avg = isset($tatByDay[$key]) && count($tatByDay[$key]) > 0
                ? (int) round(array_sum($tatByDay[$key]) / count($tatByDay[$key]))
                : null;
            $tatChartData[] = [
                'date' => $key,
                'label' => $d->format('d M'),
                'avg' => $avg,
            ];
        }

        // Missed badge: count of tasks that are overdue or not Done, with start_date in last 30 days
        $missedQuery = (clone $tasksQuery)
            ->whereNotNull('start_date')
            ->where('start_date', '>=', now()->subDays(30))
            ->where(function($q) {
                $q->whereNotIn('status', ['Done', 'Archived'])
                  ->orWhere(function($q2) {
                      $q2->whereNotNull('start_date')
                         ->whereRaw('DATE_ADD(start_date, INTERVAL 10 DAY) < NOW()')
                         ->whereNotIn('status', ['Done', 'Archived']);
                  });
            });
        // Filter by selected user if set (search in both assignor and assign_to)
        if ($selectedUserEmail) {
            $missedQuery->where(function($query) use ($selectedUserEmail) {
                $query->where('assignor', $selectedUserEmail)
                      ->orWhere('assign_to', 'LIKE', '%' . $selectedUserEmail . '%');
            });
        }
        $stats['missed_count_30'] = $missedQuery->count();

        // Daily missed count for line chart (last 30 days): date => count of missed tasks started on that day
        $missedByDay = [];
        $missedTasks = $missedQuery->get();
        foreach ($missedTasks as $task) {
            $day = \Carbon\Carbon::parse($task->start_date)->format('Y-m-d');
            $missedByDay[$day] = ($missedByDay[$day] ?? 0) + 1;
        }
        $missedChartData = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = now()->subDays($i);
            $key = $d->format('Y-m-d');
            $count = $missedByDay[$key] ?? 0;
            $missedChartData[] = [
                'date' => $key,
                'label' => $d->format('d M'),
                'count' => $count,
            ];
        }

        // Special permission: Jasmine, Ritu mam, Joy sir can delete/modify any task
        $canDeleteAnyTask = TaskPolicy::userHasSpecialTaskPermission($user);

        // AVG SCORE: always the logged-in user's performance average (not task "selected user" filter)
        $stats['average_score'] = null;
        $viewerId = (int) $user->id;
        if ($viewerId > 0 && Schema::hasTable('performance_reviews')) {
            try {
                $avgNorm = PerformanceReview::query()
                    ->where('employee_id', $viewerId)
                    ->where('is_completed', true)
                    ->avg('normalized_score');
                $stats['average_score'] = $avgNorm !== null ? round((float) $avgNorm, 2) : null;
            } catch (\Throwable $e) {
                $stats['average_score'] = null;
            }
        }

        return view('tasks.index', compact(
            'stats',
            'isAdmin',
            'users',
            'canDeleteAnyTask',
            'tatChartData',
            'missedChartData',
            'selectedUserName',
            'assignorOnTasksUsers',
            'assignorOtherUsers',
            'assigneeOnTasksUsers',
            'assigneeOtherUsers'
        ));
    }

    /**
     * Tasks visible in Task Manager for the current user (same rules as the task list API).
     * Does not apply {@see Session::get('selected_user_name')} — dashboard / Task Summary stay global within that visibility.
     */
    protected function taskManagerVisibilityQuery(): Builder
    {
        $user = Auth::user();
        $isAdmin = strtolower($user->role ?? '') === 'admin';

        $tasksQuery = Task::query();
        if (!$isAdmin) {
            $tasksQuery->where(function ($query) use ($user) {
                $query->where('is_automate_task', 1)
                    ->orWhere(function ($q) use ($user) {
                        $q->where('assignor', $user->email)
                            ->orWhere('assign_to', 'LIKE', '%' . $user->email . '%');
                    });
            });
        }

        return $tasksQuery;
    }

    /**
     * Per–team-member task counts (assignee-based), matching Task Manager visibility for the viewer only.
     * Does not apply {@see Session::get('selected_user_name')} — Task Summary stays global within that visibility;
     * the /tasks page keeps its own session + UI filters.
     *
     * @return list<array{team_member: string, email: string, avatar: mixed, designation: mixed, task: int, assignor_task: int, overdue: int, a_task: int, a_task_h: int, need_approval: int, done: int}> assignor_task excludes tasks where assignor appears in assign_to (self-assigned). a_task_h is rounded total ETC hours for automated (is_automate_task) assignee tasks.
     */
    protected function getTaskSummaryMemberRows(): array
    {
        $tasksQuery = $this->taskManagerVisibilityQuery();

        $tasks = (clone $tasksQuery)->get(['id', 'assign_to', 'assignor', 'status', 'start_date', 'is_automate_task', 'eta_time']);

        $defaultCounts = ['task' => 0, 'overdue' => 0, 'a_task' => 0, 'a_task_h' => 0, 'need_approval' => 0, 'assignor_task' => 0, 'done' => 0];

        $byEmail = [];
        foreach ($tasks as $task) {
            $assignorEmail = trim((string) ($task->assignor ?? ''));
            if ($assignorEmail !== '') {
                $assignToRaw = trim((string) ($task->assign_to ?? ''));
                $assignsToSelf = false;
                if ($assignToRaw !== '') {
                    foreach (array_map('trim', explode(',', $assignToRaw)) as $assigneeEmail) {
                        if ($assigneeEmail !== '' && strcasecmp($assigneeEmail, $assignorEmail) === 0) {
                            $assignsToSelf = true;
                            break;
                        }
                    }
                }
                if (!$assignsToSelf) {
                    if (!isset($byEmail[$assignorEmail])) {
                        $byEmail[$assignorEmail] = $defaultCounts;
                    }
                    $byEmail[$assignorEmail]['assignor_task']++;
                }
            }

            if (!$task->assign_to || trim((string) $task->assign_to) === '') {
                continue;
            }
            $emails = array_map('trim', explode(',', $task->assign_to));
            foreach ($emails as $email) {
                if ($email === '') {
                    continue;
                }
                if (!isset($byEmail[$email])) {
                    $byEmail[$email] = $defaultCounts;
                }
                $byEmail[$email]['task']++;
                if (($task->status ?? '') === 'Done') {
                    $byEmail[$email]['done']++;
                }
                if (($task->status ?? '') === 'Todo') {
                    $byEmail[$email]['a_task']++;
                }
                if (($task->status ?? '') === 'Need Approval') {
                    $byEmail[$email]['need_approval']++;
                }
                $graceEnd = $task->start_date
                    ? \Carbon\Carbon::parse($task->start_date)->copy()->addDay()
                    : null;
                $isOverdue = $graceEnd
                    && ($task->status ?? '') !== 'Archived'
                    && $graceEnd->lt(now());
                if ($isOverdue) {
                    $byEmail[$email]['overdue']++;
                }
                if (!empty($task->is_automate_task)) {
                    $byEmail[$email]['a_task_h'] += (float) ($task->eta_time ?? 0);
                }
            }
        }

        $members = User::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'avatar', 'designation']);

        $rows = [];
        foreach ($members as $member) {
            $email = $member->email;
            $counts = $byEmail[$email] ?? $defaultCounts;
            $rows[] = [
                'team_member' => $member->name,
                'email' => $email,
                'avatar' => $member->avatar,
                'designation' => $member->designation,
                'task' => $counts['task'],
                'assignor_task' => $counts['assignor_task'],
                'overdue' => $counts['overdue'],
                'a_task' => $counts['a_task'],
                'a_task_h' => (int) round($counts['a_task_h'] / 60),
                'need_approval' => $counts['need_approval'],
                'done' => $counts['done'],
            ];
        }

        usort($rows, function (array $a, array $b): int {
            $ta = (int) ($a['task'] ?? 0);
            $tb = (int) ($b['task'] ?? 0);
            if ($ta !== $tb) {
                return $tb <=> $ta;
            }

            return strcasecmp((string) ($a['team_member'] ?? ''), (string) ($b['team_member'] ?? ''));
        });

        return $rows;
    }

    /**
     * Dashboard / summary headline stats: one row per task, same definitions as {@see index()} stats
     * (without session selected_user — matches /tasks when no user filter is selected).
     *
     * @return array{total_tasks: int, assigned_members: int, pending: int, overdue: int, approval_pending: int, done: int}
     */
    protected function getTaskDashboardAggregates(): array
    {
        $q = $this->taskManagerVisibilityQuery();

        $overdueQuery = (clone $q)->whereNotNull('start_date')
            ->whereRaw('DATE_ADD(start_date, INTERVAL 1 DAY) < NOW()')
            ->where('status', '!=', 'Archived');

        $activeEmailSet = array_flip(User::where('is_active', true)->pluck('email')->all());
        $assignedMembers = (clone $q)
            ->whereNotNull('assign_to')
            ->where('assign_to', '!=', '')
            ->pluck('assign_to')
            ->flatMap(function ($assignTo) {
                return array_map('trim', explode(',', $assignTo));
            })
            ->filter()
            ->unique()
            ->filter(function ($email) use ($activeEmailSet) {
                return isset($activeEmailSet[$email]);
            })
            ->count();

        return [
            'total_tasks' => (clone $q)->count(),
            'assigned_members' => $assignedMembers,
            'pending' => (clone $q)->where('status', 'Todo')->count(),
            'overdue' => $overdueQuery->count(),
            'approval_pending' => (clone $q)->where('status', 'Need Approval')->count(),
            'done' => (clone $q)->where('status', 'Done')->count(),
        ];
    }

    /**
     * Headline task counts for dashboard blades outside {@see homeDashboard()} (e.g. channel master).
     */
    public function sharedTaskDashboardAggregates(): array
    {
        return $this->getTaskDashboardAggregates();
    }

    /**
     * Main dashboard (home) with task overview stats.
     */
    public function homeDashboard(): View
    {
        $taskDashboardStats = $this->getTaskDashboardAggregates();

        return view('index', compact('taskDashboardStats'));
    }

    /**
     * Task summary page (same data as {@see getTaskSummaryMemberRows()}).
     */
    public function taskSummary()
    {
        $rows = $this->getTaskSummaryMemberRows();
        $taskDashboardStats = $this->getTaskDashboardAggregates();

        return view('tasks.task-summary', compact('rows', 'taskDashboardStats'));
    }

    /**
     * Aggregates for dashboard / API — same headline counts as /tasks (no session user filter).
     */
    public function taskSummaryStats(): JsonResponse
    {
        $s = $this->getTaskDashboardAggregates();

        return response()->json([
            'total_tasks' => $s['total_tasks'],
            'assigned_members' => $s['assigned_members'],
            'overdue' => $s['overdue'],
            'approval_pending' => $s['approval_pending'],
            'done' => $s['done'],
            'pending' => $s['pending'],
        ]);
    }

    public function getData(Request $request)
    {
        $tasksQuery = $this->taskManagerVisibilityQuery();

        $userNameFilter = trim((string) $request->query('user_name', ''));
        if ($userNameFilter !== '') {
            $filterUser = User::where('name', $userNameFilter)->first();
            if ($filterUser && $filterUser->email) {
                $email = $filterUser->email;
                $tasksQuery->where(function ($q) use ($email) {
                    $q->where('assignor', $email)
                        ->orWhere('assign_to', 'LIKE', '%' . $email . '%');
                });
            } else {
                $tasksQuery->whereRaw('1 = 0');
            }
        }

        // Order: by date (asc). Within same day, automated tasks on top (us din ka automated task top par), then start_date
        $tasks = $tasksQuery
            ->orderByRaw('(start_date IS NULL) ASC, DATE(start_date) ASC')
            ->orderBy('is_automate_task', 'desc')
            ->orderBy('start_date', 'asc')
            ->get();

        // Map emails to names and avatar URLs for display
        $defaultAvatar = asset('images/users/avatar-2.jpg');
        $tasks->each(function($task) use ($defaultAvatar) {
            // Normalize datetime fields to local string format so frontend date parsing
            // doesn't shift dates because of UTC ISO serialization ("...Z").
            foreach (['start_date', 'due_date', 'completion_date', 'created_at', 'updated_at'] as $dtField) {
                if (!empty($task->{$dtField})) {
                    try {
                        $task->{$dtField} = \Carbon\Carbon::parse($task->{$dtField}, config('app.timezone'))
                            ->setTimezone(config('app.timezone'))
                            ->format('Y-m-d H:i:s');
                    } catch (\Throwable $e) {
                        // keep original value if parsing fails
                    }
                }
            }

            // Find users by email and get their names + avatars
            if ($task->assignor) {
                $assignorUser = User::where('email', $task->assignor)->first();
                $task->assignor_name = $assignorUser ? $assignorUser->name : $task->assignor;
                $task->assignor_id = $assignorUser ? $assignorUser->id : null;
                $task->assignor_avatar = $assignorUser && $assignorUser->avatar
                    ? asset('storage/' . $assignorUser->avatar)
                    : $defaultAvatar;
            } else {
                $task->assignor_name = '-';
                $task->assignor_id = null;
                $task->assignor_avatar = null;
            }

            if ($task->assign_to) {
                // Handle multiple assignees (comma-separated emails)
                $assigneeEmails = array_map('trim', explode(',', $task->assign_to));
                $assigneeNames = [];
                $assigneeIds = [];
                $assigneeAvatars = [];

                foreach ($assigneeEmails as $email) {
                    $assigneeUser = User::where('email', $email)->first();
                    if ($assigneeUser) {
                        $assigneeNames[] = $assigneeUser->name;
                        $assigneeIds[] = $assigneeUser->id;
                        $assigneeAvatars[] = $assigneeUser->avatar
                            ? asset('storage/' . $assigneeUser->avatar)
                            : $defaultAvatar;
                    } else {
                        $assigneeNames[] = $email;
                        $assigneeAvatars[] = $defaultAvatar;
                    }
                }

                $task->assignee_name = implode(', ', $assigneeNames);
                $task->assignee_id = !empty($assigneeIds) ? $assigneeIds[0] : null; // First ID for compatibility
                $task->assignee_ids = $assigneeIds;
                $task->assignee_count = count($assigneeNames);
                $task->assignee_avatar = !empty($assigneeAvatars) ? $assigneeAvatars[0] : null;
                $task->assignee_avatars = $assigneeAvatars;
            } else {
                $task->assignee_name = '-';
                $task->assignee_id = null;
                $task->assignee_ids = [];
                $task->assignee_count = 0;
                $task->assignee_avatar = null;
                $task->assignee_avatars = [];
            }

            // For permission checks
            $task->assignor_email = $task->assignor;
            $task->assignee_email = $task->assign_to;
        });

        // Return raw DB attributes (not casted UTC ISO datetimes) so date filters/display
        // align with local task dates in the blade.
        $responseRows = $tasks->map(function ($task) {
            $row = $task->getAttributes(); // raw DB values (e.g. "Y-m-d H:i:s")

            foreach ([
                'assignor_name',
                'assignor_id',
                'assignor_avatar',
                'assignee_name',
                'assignee_id',
                'assignee_ids',
                'assignee_count',
                'assignee_avatar',
                'assignee_avatars',
                'assignor_email',
                'assignee_email',
            ] as $field) {
                if (isset($task->{$field})) {
                    $row[$field] = $task->{$field};
                }
            }

            return $row;
        })->values();

        return response()->json($responseRows);
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
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240', // 10MB max
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
        
        \Log::info('📝 Assignee Processing:', [
            'has_assignee_id' => $request->has('assignee_id'),
            'assignee_id_value' => $request->assignee_id,
            'has_assignee_ids' => !empty($assigneeIds),
            'assignee_ids_value' => $assigneeIds,
            'validated_assignee_id' => $validated['assignee_id'] ?? null
        ]);
        
        if (!empty($assigneeIds) && count($assigneeIds) > 0) {
            // Multiple assignees - store as comma-separated emails
            $assigneeEmails = User::whereIn('id', $assigneeIds)->pluck('email')->toArray();
            $assigneeEmail = implode(', ', $assigneeEmails);
            \Log::info('✅ Multiple assignees selected:', [
                'count' => count($assigneeIds),
                'ids' => $assigneeIds, 
                'emails' => $assigneeEmail
            ]);
        } elseif ($request->has('assignee_id') && $validated['assignee_id']) {
            // Single assignee
            $assigneeUser = User::find($validated['assignee_id']);
            $assigneeEmail = $assigneeUser ? $assigneeUser->email : null;
            \Log::info('✅ Single assignee selected:', [
                'id' => $validated['assignee_id'], 
                'email' => $assigneeEmail
            ]);
        } else {
            \Log::warning('⚠️ No assignee provided - task will be unassigned');
        }
        
        \Log::info('💾 Final assignee to save:', ['assign_to' => $assigneeEmail]);
        
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

        if ($request->boolean('quick_create_more')) {
            $detail = $message;
            if (str_starts_with($detail, 'Task created successfully!')) {
                $detail = trim(substr($detail, strlen('Task created successfully!')));
            }
            $displayMessage = 'Task generated successfully!' . ($detail !== '' ? ' ' . $detail : '');

            return response()->json([
                'success' => true,
                'message' => $displayMessage,
            ]);
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

            // Ensure view modal gets link fields (support both training_link column and link3-7)
            $taskData['training_link'] = $task->getAttribute('training_link') ?: $task->getAttribute('link3') ?: '';
            $taskData['video_link'] = $task->getAttribute('video_link') ?: $task->getAttribute('link4') ?: '';
            $taskData['form_link'] = $task->getAttribute('form_link') ?: $task->getAttribute('link5') ?: '';
            $taskData['form_report_link'] = $task->getAttribute('form_report_link') ?: $task->getAttribute('link6') ?: '';
            $taskData['checklist_link'] = $task->getAttribute('checklist_link') ?: $task->getAttribute('link7') ?: '';
            // L1/L2 stored as link1/link2; PL/process stored as link8/link9 in DB
            $taskData['l1'] = $task->getAttribute('link1') ?: $task->getAttribute('l1') ?: '';
            $taskData['l2'] = $task->getAttribute('link2') ?: $task->getAttribute('l2') ?: '';
            $taskData['pl'] = $task->getAttribute('link8') ?: $task->getAttribute('pl') ?: '';
            $taskData['process'] = $task->getAttribute('link9') ?: $task->getAttribute('process') ?: '';
            $taskData['report'] = $task->getAttribute('report') ?: '';
            $taskData['reference_link'] = $task->getAttribute('reference_link') ?: '';

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
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240', // 10MB max
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
        
        // Delete associated image file if exists
        if ($task->image && file_exists(public_path('uploads/tasks/' . $task->image))) {
            unlink(public_path('uploads/tasks/' . $task->image));
            \Log::info('🗑️ Image deleted for deleted task:', ['task_id' => $task->id, 'image' => $task->image]);
        }
        
        // Save task to deleted_tasks before deletion
        $this->saveDeletedTask($task);
        
        $task->delete();

        return response()->json(['success' => true, 'message' => 'Task deleted successfully!']);
    }

    /**
     * Mark a manual task Done with required completion report (Task Manager /tasks).
     */
    public function complete(Request $request, $id): JsonResponse
    {
        $task = Task::findOrFail($id);
        $this->authorize('updateStatus', $task);

        if ($task->status === 'Done') {
            return response()->json([
                'message' => 'This task is already marked as Done.',
            ], 422);
        }

        $this->mergeEmptyReferenceLink($request);
        $request->merge(['report' => trim((string) $request->input('report', ''))]);

        $validated = $request->validate([
            'report' => 'required|string|min:1',
            'reference_link' => 'nullable|url|max:2048',
            'atc' => 'required|integer|min:1|digits_between:1,10',
        ]);

        $task->report = $validated['report'];
        $task->reference_link = $validated['reference_link'] ?? null;
        $task->status = 'Done';
        $this->applyTaskDoneEffects($task, (int) $validated['atc']);

        $task->save();

        try {
            $this->taskWhatsApp->notifyTaskDone($task->fresh());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Task WhatsApp notify done failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Task completed successfully!',
            'task' => $task->fresh(),
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        
        // Check if user can update status
        $this->authorize('updateStatus', $task);

        $this->mergeEmptyReferenceLink($request);

        $validated = $request->validate([
            'status' => 'required|in:Todo,Working,Archived,Done,Need Help,Need Approval,Dependent,Approved,Hold,Rework',
            'atc' => 'nullable|integer|min:1|digits_between:1,10',
            'rework_reason' => 'nullable|string',
            'report' => [
                Rule::requiredIf(fn () => $request->input('status') === 'Done' && ! $task->is_automate_task),
                'nullable',
                'string',
            ],
            'reference_link' => 'nullable|url|max:2048',
        ]);

        $task->status = $validated['status'];

        if ($validated['status'] === 'Done') {
            if (! $task->is_automate_task) {
                $task->report = trim((string) ($validated['report'] ?? ''));
                $task->reference_link = $validated['reference_link'] ?? null;
            }
            $atc = array_key_exists('atc', $validated) && $validated['atc'] !== null
                ? (int) $validated['atc']
                : null;
            $this->applyTaskDoneEffects($task, $atc);
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

    protected function mergeEmptyReferenceLink(Request $request): void
    {
        if (! $request->has('reference_link')) {
            return;
        }
        if (trim((string) $request->input('reference_link', '')) === '') {
            $request->merge(['reference_link' => null]);
        }
    }

    protected function applyTaskDoneEffects(Task $task, ?int $atcMinutes): void
    {
        if ($atcMinutes !== null) {
            $task->etc_done = $atcMinutes;

            if ($task->assign_to && str_contains($task->assign_to, ',')) {
                $assigneeEmails = array_map('trim', explode(',', $task->assign_to));
                \Log::info('✅ Task completed - ATC credited to ALL assignees:', [
                    'task_id' => $task->id,
                    'atc_minutes' => $atcMinutes,
                    'assignees' => $assigneeEmails,
                    'count' => count($assigneeEmails),
                    'note' => 'Each assignee gets credit for ' . $atcMinutes . ' minutes',
                ]);
            }
        }

        $task->completion_date = now();

        if ($task->start_date) {
            try {
                $startDate = \Carbon\Carbon::parse($task->start_date);
                $task->completion_day = $startDate->diffInDays(now());
            } catch (\Throwable $e) {
                // keep completion_day unchanged
            }
        }

        if ($task->image && file_exists(public_path('uploads/tasks/' . $task->image))) {
            unlink(public_path('uploads/tasks/' . $task->image));
            \Log::info('🗑️ Image deleted for completed task:', ['task_id' => $task->id, 'image' => $task->image]);
            $task->image = null;
        }
    }

    public function bulkUpdate(Request $request)
    {
        try {
            $user = Auth::user();
            $isAdmin = strtolower($user->role ?? '') === 'admin';

            // Check if this is for automated tasks
            // Either based on is_automated flag or specific actions only for automated tasks
            $isAutomatedTask = $request->boolean('is_automated') || in_array($request->action, ['duplicate', 'freq', 'assignor']);

            // Ensure task_ids are integers (frontend may send strings); filter out invalid
            $taskIdsInput = $request->input('task_ids', []);
            if (!is_array($taskIdsInput)) {
                $taskIdsInput = [];
            }
            $request->merge([
                'task_ids' => array_values(array_filter(array_map('intval', $taskIdsInput), function ($id) {
                    return $id > 0;
                })),
            ]);

            $action = $request->input('action');
            $taskIdRule = $isAutomatedTask ? 'integer' : (($action === 'delete') ? 'integer' : 'exists:tasks,id');
            $rules = [
                'action' => 'required|in:delete,priority,tid,assignee,etc,assign_assignee,assign_assignor,duplicate,assignor,freq',
                'task_ids' => 'required|array',
                'task_ids.*' => $taskIdRule,
                'is_automated' => 'nullable|boolean',
                'priority' => 'nullable|in:low,normal,high',
                'tid' => 'nullable|date',
                'assignee_id' => 'nullable|exists:users,id',
                'assignor_id' => 'nullable|exists:users,id',
                'assignee' => 'nullable|string',
                'assignor' => 'nullable|string',
                'etc_minutes' => 'nullable|integer|min:1',
                'freq' => 'nullable|in:daily,weekly,monthly',
                'duplicate_group' => 'nullable|string|max:255',
                'duplicate_title_suffix' => 'nullable|string|max:500',
                'duplicate_assignor_id' => 'nullable|exists:users,id',
            ];
            $validated = $request->validate($rules);

        $taskIds = $validated['task_ids'];
        $action = $validated['action'];
        
        \Log::info('🔵 Bulk Update Request:', [
            'action' => $action,
            'task_ids' => $taskIds,
            'is_automated' => $isAutomatedTask,
            'count' => count($taskIds)
        ]);

        switch ($action) {
            case 'delete':
                if ($isAutomatedTask) {
                    // Delete automated tasks from automate_tasks table
                    $deletedCount = \DB::table('automate_tasks')
                        ->whereIn('id', $taskIds)
                        ->delete();
                    
                    return response()->json([
                        'success' => true,
                        'message' => "$deletedCount automated task(s) deleted successfully!"
                    ]);
                } else {
                    try {
                        // Special permission: Jasmine, Ritu mam, Joy sir can delete any task; others only their own
                        if (TaskPolicy::userHasSpecialTaskPermission($user)) {
                            $tasksToDelete = Task::whereIn('id', $taskIds)->get();
                        } else {
                            $tasksToDelete = Task::whereIn('id', $taskIds)
                                ->where('assignor', $user->email)
                                ->get();
                        }

                        $deletedCount = $tasksToDelete->count();
                        $requestedCount = count($taskIds);

                        if ($deletedCount === 0) {
                            return response()->json([
                                'success' => false,
                                'message' => 'You can only delete tasks you created. None of the selected tasks belong to you.'
                            ], 403);
                        }

                        // Delete images and save to deleted_tasks before deletion
                        $imagesDeleted = 0;
                        $archiveFailed = 0;
                        foreach ($tasksToDelete as $task) {
                            // Delete image file if exists (don't fail bulk delete if file delete fails)
                            if (!empty($task->image)) {
                                $imagePath = public_path('uploads/tasks/' . $task->image);
                                if (file_exists($imagePath) && is_file($imagePath)) {
                                    try {
                                        if (@unlink($imagePath)) {
                                            $imagesDeleted++;
                                            \Log::info('🗑️ Image deleted:', ['task_id' => $task->id, 'image' => $task->image]);
                                        }
                                    } catch (\Throwable $e) {
                                        \Log::warning('Bulk delete: could not delete image file', ['path' => $imagePath, 'error' => $e->getMessage()]);
                                    }
                                }
                            }
                            // Archive to deleted_tasks (best-effort: don't fail bulk delete when archiving other users' tasks)
                            try {
                                $this->saveDeletedTask($task);
                            } catch (\Throwable $e) {
                                $archiveFailed++;
                                \Log::warning('Bulk delete: could not archive task to deleted_tasks', [
                                    'task_id' => $task->id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }

                        Task::whereIn('id', $tasksToDelete->pluck('id'))->delete();

                        if ($imagesDeleted > 0) {
                            \Log::info("🗑️ Bulk delete: $imagesDeleted image(s) deleted");
                        }

                        $message = "$deletedCount task(s) deleted successfully!";
                        if ($deletedCount < $requestedCount) {
                            $skipped = $requestedCount - $deletedCount;
                            $message .= " ($skipped task(s) skipped - you can only delete tasks you created)";
                        }
                        if ($archiveFailed > 0) {
                            $message .= " (Archive failed for {$archiveFailed} task(s).)";
                        }

                        return response()->json([
                            'success' => true,
                            'message' => $message
                        ]);
                    } catch (\Throwable $e) {
                        \Log::error('Bulk delete tasks failed', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'task_ids' => $taskIds,
                        ]);
                        return response()->json([
                            'success' => false,
                            'message' => 'Failed to delete tasks: ' . (config('app.debug') ? $e->getMessage() : 'Please try again or contact support.'),
                        ], 500);
                    }
                }

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
                    if ($isAutomatedTask) {
                        // Update assignee for automated tasks
                        $assigneeUser = User::find($validated['assignee_id']);
                        if ($assigneeUser) {
                            \DB::table('automate_tasks')
                                ->whereIn('id', $taskIds)
                                ->update(['assign_to' => $assigneeUser->email, 'updated_at' => now()]);
                        }
                        return response()->json([
                            'success' => true,
                            'message' => "$count automated task(s) assignee updated!"
                        ]);
                    } else {
                        $assigneeUser = User::find($validated['assignee_id']);
                        if ($assigneeUser) {
                            Task::whereIn('id', $taskIds)->update(['assign_to' => $assigneeUser->email]);
                        }
                        return response()->json([
                            'success' => true,
                            'message' => "$count task(s) assignee updated!"
                        ]);
                    }
                } elseif ($action === 'etc') {
                    if ($isAutomatedTask) {
                        // Update ETC for automated tasks
                        \DB::table('automate_tasks')
                            ->whereIn('id', $taskIds)
                            ->update(['eta_time' => $validated['etc_minutes'], 'updated_at' => now()]);
                        return response()->json([
                            'success' => true,
                            'message' => "$count automated task(s) ETC updated!"
                        ]);
                    } else {
                        Task::whereIn('id', $taskIds)->update(['eta_time' => $validated['etc_minutes']]);
                        return response()->json([
                            'success' => true,
                            'message' => "$count task(s) ETC updated!"
                        ]);
                    }
                }
                break;
            
            case 'assign_assignee':
                // Bulk assign assignee(s) - comma-separated for multiple
                $count = count($taskIds);
                $assigneeEmails = $request->assignee;
                
                \Log::info("🔍 Bulk Assign Assignee Debug:", [
                    'task_ids_count' => $count,
                    'task_ids' => $taskIds,
                    'assignee_emails' => $assigneeEmails,
                    'request_all' => $request->all()
                ]);
                
                if (empty($assigneeEmails)) {
                    \Log::error("❌ No assignee emails provided!");
                    return response()->json([
                        'success' => false,
                        'message' => 'No assignee provided!'
                    ], 400);
                }
                
                // Update ONLY the specified tasks
                $updated = Task::whereIn('id', $taskIds)->update(['assign_to' => $assigneeEmails]);
                
                \Log::info("✅ Updated $updated tasks with assignee: $assigneeEmails");
                
                return response()->json([
                    'success' => true,
                    'message' => "$updated task(s) assignee updated to: " . substr($assigneeEmails, 0, 50) . "..."
                ]);
            
            case 'assign_assignor':
                // Bulk assign assignor (admin only)
                if (!$isAdmin) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Only admins can change assignor'
                    ], 403);
                }
                
                $count = count($taskIds);
                $assignorEmail = $request->assignor;
                
                \Log::info("🔍 Bulk Assign Assignor Debug:", [
                    'task_ids_count' => $count,
                    'task_ids' => $taskIds,
                    'assignor_email' => $assignorEmail,
                    'request_all' => $request->all()
                ]);
                
                if (empty($assignorEmail)) {
                    \Log::error("❌ No assignor email provided!");
                    return response()->json([
                        'success' => false,
                        'message' => 'No assignor provided!'
                    ], 400);
                }
                
                $updated = Task::whereIn('id', $taskIds)->update(['assignor' => $assignorEmail]);
                
                \Log::info("✅ Updated $updated tasks with assignor: $assignorEmail");
                
                return response()->json([
                    'success' => true,
                    'message' => "$updated task(s) assignor updated to: $assignorEmail"
                ]);
            
            case 'duplicate':
                // Duplicate automated tasks from automate_tasks table
                $tasksToDuplicate = \DB::table('automate_tasks')->whereIn('id', $taskIds)->get();

                if ($tasksToDuplicate->isEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No tasks found to duplicate'
                    ], 404);
                }

                $duplicateAssigneeEmail = null;
                if ($request->filled('assignee_id')) {
                    $dupAssignee = User::find((int) $request->input('assignee_id'));
                    if ($dupAssignee && $dupAssignee->email) {
                        $duplicateAssigneeEmail = $dupAssignee->email;
                    }
                }

                $groupOverride = trim((string) $request->input('duplicate_group', ''));
                $titleSuffix = trim((string) $request->input('duplicate_title_suffix', ''));

                $duplicateAssignorEmail = null;
                if ($isAdmin && $request->filled('duplicate_assignor_id')) {
                    $dupAssignor = User::find((int) $request->input('duplicate_assignor_id'));
                    if ($dupAssignor && $dupAssignor->email) {
                        $duplicateAssignorEmail = $dupAssignor->email;
                    }
                }

                $duplicatedCount = 0;
                foreach ($tasksToDuplicate as $task) {
                    $taskArray = (array) $task;
                    unset($taskArray['id']); // Remove ID so a new one is created
                    $taskArray['created_at'] = now();
                    $taskArray['updated_at'] = now();
                    if ($duplicateAssigneeEmail !== null) {
                        $taskArray['assign_to'] = $duplicateAssigneeEmail;
                    }
                    if ($groupOverride !== '') {
                        $taskArray['group'] = $groupOverride;
                    }
                    if ($titleSuffix !== '') {
                        $taskArray['title'] = (string) ($task->title ?? '') . $titleSuffix;
                    }
                    if ($duplicateAssignorEmail !== null) {
                        $taskArray['assignor'] = $duplicateAssignorEmail;
                    }

                    \DB::table('automate_tasks')->insert($taskArray);
                    $duplicatedCount++;
                }

                $msg = "$duplicatedCount task(s) duplicated successfully!";
                if ($duplicateAssigneeEmail !== null) {
                    $msg .= ' All copies use the selected assignee.';
                }
                if ($groupOverride !== '') {
                    $msg .= ' Group set for all copies.';
                }
                if ($titleSuffix !== '') {
                    $msg .= ' Title suffix applied to each copy.';
                }
                if ($duplicateAssignorEmail !== null) {
                    $msg .= ' Assignor set for all copies.';
                }

                return response()->json([
                    'success' => true,
                    'message' => $msg,
                ]);
            
            case 'assignor':
                // Bulk update assignor for automated tasks
                if (!$isAdmin) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Only admins can change assignor'
                    ], 403);
                }
                
                $assignorUser = User::find($validated['assignor_id']);
                if (!$assignorUser) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Assignor not found'
                    ], 404);
                }
                
                $updated = \DB::table('automate_tasks')
                    ->whereIn('id', $taskIds)
                    ->update(['assignor' => $assignorUser->email, 'updated_at' => now()]);
                
                return response()->json([
                    'success' => true,
                    'message' => "$updated automated task(s) assignor updated!"
                ]);
            
            case 'freq':
                // Bulk update frequency (schedule_type) for automated tasks
                if (!$isAdmin) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Only admins can change frequency'
                    ], 403);
                }
                
                $updated = \DB::table('automate_tasks')
                    ->whereIn('id', $taskIds)
                    ->update(['schedule_type' => $validated['freq'], 'updated_at' => now()]);
                
                return response()->json([
                    'success' => true,
                    'message' => "$updated automated task(s) frequency updated to: " . $validated['freq']
                ]);

            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid action'
                ], 400);
        }
        } catch (\Throwable $e) {
            \Log::error('Bulk update failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'action' => $request->input('action'),
                'task_ids' => $request->input('task_ids'),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Bulk update failed: ' . (config('app.debug') ? $e->getMessage() : 'Please try again or contact support.'),
            ], 500);
        }
    }

    public function getUsersList()
    {
        $users = User::where('is_active', true)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
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

        // Get active users for filter dropdowns (same behavior as task page)
        $users = User::where('is_active', true)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // Calculate statistics for automated tasks
        $automatedQuery = \DB::table('automate_tasks');
        
        // Show all automated templates to everyone (admin and non-admin).

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
        
        // Show all automated templates to everyone (admin and non-admin).

        $tasks = $query->orderBy('id', 'desc')->get();

        // Map emails to names and avatar URLs
        $defaultAvatar = asset('images/users/avatar-2.jpg');
        $tasks->each(function($task) use ($defaultAvatar) {
            if ($task->assignor) {
                $assignorUser = User::where('email', $task->assignor)->first();
                $task->assignor_name = $assignorUser ? $assignorUser->name : $task->assignor;
                $task->assignor_avatar = $assignorUser && $assignorUser->avatar
                    ? asset('storage/' . $assignorUser->avatar)
                    : $defaultAvatar;
            } else {
                $task->assignor_name = '-';
                $task->assignor_avatar = null;
            }

            if ($task->assign_to) {
                $assigneeUser = User::where('email', $task->assign_to)->first();
                $task->assignee_name = $assigneeUser ? $assigneeUser->name : $task->assign_to;
                $task->assignee_avatar = $assigneeUser && $assigneeUser->avatar
                    ? asset('storage/' . $assigneeUser->avatar)
                    : $defaultAvatar;
            } else {
                $task->assignee_name = '-';
                $task->assignee_avatar = null;
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
            'priority' => 'nullable|in:Low,Normal,High,Urgent',
            'assignor_id' => 'nullable|exists:users,id',
            'assignee_id' => 'required|exists:users,id', // Required so auto-generated tasks are always assigned to someone
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
        
        // Set default priority to Normal if not provided
        $validated['priority'] = $validated['priority'] ?? 'Normal';
        if (($validated['schedule_type'] ?? '') === 'daily') {
            $validated['schedule_time'] = '12:01:00';
        }

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
            'description' => $validated['description'] ?? '',
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
        $taskModel = \DB::table('automate_tasks')->where('id', $id)->first();
        
        if (!$taskModel) {
            return redirect()->route('tasks.automated')->with('error', 'Automated task not found');
        }
        
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
            'image' => $taskModel->image ?? null,
            'assignor' => $taskModel->assignor,
            'assign_to' => $taskModel->assign_to,
            'schedule_type' => $taskModel->schedule_type ?? 'daily',
            'schedule_days' => $taskModel->schedule_days ?? '',
            'schedule_time' => $taskModel->schedule_time ?? '12:01',
        ];
        
        // Map email addresses to user IDs for the form
        if ($taskModel->assignor) {
            $assignorEmail = trim($taskModel->assignor);
            $assignorUser = User::where('email', $assignorEmail)->first();
            $task->assignor_id = $assignorUser ? $assignorUser->id : null;
        }
        
        if ($taskModel->assign_to) {
            $assignToEmail = trim($taskModel->assign_to);
            
            // If there are multiple emails, take the first one
            if (strpos($assignToEmail, ',') !== false) {
                $emails = array_map('trim', explode(',', $assignToEmail));
                $assignToEmail = $emails[0];
            }
            
            $assigneeUser = User::where('email', $assignToEmail)->first();
            $task->assignee_id = $assigneeUser ? $assigneeUser->id : null;
        }
        
        return view('tasks.automated-edit', compact('task', 'users'));
    }

    public function automatedUpdate(Request $request, $id)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'group' => 'nullable|string',
            'priority' => 'nullable|in:Low,Normal,High,Urgent',
            'etc_minutes' => 'nullable|integer',
            'schedule_type' => 'required|in:daily,weekly,monthly',
            'schedule_days' => 'nullable|string',
            'schedule_time' => 'nullable',
            'assignor_id' => 'nullable|exists:users,id',
            'assignee_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
            'l1' => 'nullable|string|max:2048',
            'l2' => 'nullable|string|max:2048',
            'training_link' => 'nullable|string|max:2048',
            'video_link' => 'nullable|string|max:2048',
            'form_link' => 'nullable|string|max:2048',
            'form_report_link' => 'nullable|string|max:2048',
            'checklist_link' => 'nullable|string|max:2048',
            'pl' => 'nullable|string|max:2048',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
        ]);
        
        // Set default priority to Normal if not provided
        $validated['priority'] = $validated['priority'] ?? 'Normal';
        if (($validated['schedule_type'] ?? '') === 'daily') {
            $validated['schedule_time'] = '12:01:00';
        }

        $user = Auth::user();
        $existing = \DB::table('automate_tasks')->where('id', $id)->first();
        if (!$existing) {
            return redirect()->route('tasks.automated')->with('error', 'Automated task not found.');
        }

        // Resolve assignor email from assignor_id (form always sends it: dropdown for admin, hidden for non-admin)
        $assignorEmail = $existing->assignor ?? $user->email;
        if ($request->filled('assignor_id')) {
            $assignorUser = User::find($validated['assignor_id']);
            $assignorEmail = $assignorUser ? $assignorUser->email : $assignorEmail;
        }

        // Resolve assignee email from assignee_id
        $assigneeEmail = null;
        if ($request->filled('assignee_id')) {
            $assigneeUser = User::find($validated['assignee_id']);
            $assigneeEmail = $assigneeUser ? $assigneeUser->email : null;
        }

        $imageName = $existing->image ?? null;
        if ($request->hasFile('image')) {
            if ($imageName && file_exists(public_path('uploads/tasks/' . $imageName))) {
                @unlink(public_path('uploads/tasks/' . $imageName));
            }
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('uploads/tasks'), $imageName);
        }

        $description = $validated['description'] ?? $existing->description ?? '';

        // automate_tasks historically had link1–link7 only; link8/link9/image may exist after migration.
        $automateUpdate = [
            'title' => $validated['title'],
            'description' => $description,
            'group' => $validated['group'],
            'priority' => $validated['priority'],
            'eta_time' => $validated['etc_minutes'] ?? 10,
            'schedule_type' => $validated['schedule_type'],
            'schedule_days' => $validated['schedule_days'] ?? '',
            'schedule_time' => $validated['schedule_time'],
            'assignor' => $assignorEmail,
            'assign_to' => $assigneeEmail,
            'link1' => $validated['l1'] ?? '',
            'link2' => $validated['l2'] ?? '',
            'link3' => $validated['training_link'] ?? '',
            'link4' => $validated['video_link'] ?? '',
            'link5' => $validated['form_link'] ?? '',
            'link6' => $validated['form_report_link'] ?? '',
            'link7' => $validated['checklist_link'] ?? '',
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('automate_tasks', 'link8')) {
            $automateUpdate['link8'] = $validated['pl'] ?? '';
        }
        if (Schema::hasColumn('automate_tasks', 'link9')) {
            $automateUpdate['link9'] = $existing->link9 ?? '';
        }
        if (Schema::hasColumn('automate_tasks', 'image')) {
            $automateUpdate['image'] = $imageName;
        }

        \DB::table('automate_tasks')->where('id', $id)->update($automateUpdate);

        // Update any existing executed instances in tasks table (including assignor/assignee and links)
        $tasksUpdate = [
            'title' => $validated['title'],
            'description' => $description,
            'group' => $validated['group'],
            'priority' => $validated['priority'],
            'eta_time' => $validated['etc_minutes'] ?? 10,
            'schedule_type' => $validated['schedule_type'],
            'assignor' => $assignorEmail,
            'assign_to' => $assigneeEmail,
            'link1' => $validated['l1'] ?? '',
            'link2' => $validated['l2'] ?? '',
            'link3' => $validated['training_link'] ?? '',
            'link4' => $validated['video_link'] ?? '',
            'link5' => $validated['form_link'] ?? '',
            'link6' => $validated['form_report_link'] ?? '',
            'link7' => $validated['checklist_link'] ?? '',
            'link8' => $validated['pl'] ?? '',
            'link9' => $existing->link9 ?? '',
            'image' => $imageName,
            'updated_at' => now(),
        ];
        \DB::table('tasks')->where('automate_task_id', $id)->update($tasksUpdate);

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
     * Save task to deleted_tasks table before deletion.
     * Never throws: safe for server (no Schema calls, all errors caught).
     */
    private function saveDeletedTask(Task $task)
    {
        try {
            $user = Auth::user();
            $str = function ($v, $max = 255) {
                if ($v === null || $v === '') {
                    return null;
                }
                $s = (string) $v;
                return strlen($s) > $max ? substr($s, 0, $max) : $s;
            };
            $date = function ($v) {
                if ($v === null || $v === '') {
                    return null;
                }
                if ($v instanceof \DateTimeInterface) {
                    return $v->format('Y-m-d H:i:s');
                }
                return (string) $v;
            };

            $assignToRaw = $task->assign_to;
            $assignorUser = !empty($task->assignor) ? User::where('email', $task->assignor)->first() : null;
            $firstAssignee = !empty($assignToRaw) ? trim(explode(',', (string) $assignToRaw)[0]) : null;
            $assigneeUser = $firstAssignee ? User::where('email', $firstAssignee)->first() : null;

            $splitTasks = $task->split_tasks;
            $isMissed = $task->is_missed;
            $isMissedTrack = $task->is_missed_track;
            if (!is_numeric($splitTasks)) {
                $splitTasks = $splitTasks ? 1 : 0;
            }
            if (!is_numeric($isMissed)) {
                $isMissed = $isMissed ? 1 : 0;
            }
            if (!is_numeric($isMissedTrack)) {
                $isMissedTrack = $isMissedTrack ? 1 : 0;
            }

            $now = now()->format('Y-m-d H:i:s');
            $fullRow = [
                'original_task_id' => (int) $task->id,
                'title' => $str($task->title ?? '', 255),
                'description' => $task->description !== null ? $str((string) $task->description, 65535) : null,
                'group' => $str($task->group),
                'priority' => $str($task->priority),
                'status' => $str($task->status),
                'assignor' => $str($task->assignor),
                'assign_to' => $str($assignToRaw),
                'assignor_name' => $str($assignorUser ? $assignorUser->name : $task->assignor),
                'assignee_name' => $str($assigneeUser ? $assigneeUser->name : $assignToRaw),
                'eta_time' => $task->eta_time !== null && $task->eta_time !== '' ? (int) $task->eta_time : null,
                'etc_done' => $task->etc_done !== null && $task->etc_done !== '' ? (int) $task->etc_done : null,
                'start_date' => $date($task->start_date),
                'completion_date' => $date($task->completion_date),
                'completion_day' => $task->completion_day !== null && $task->completion_day !== '' ? (int) $task->completion_day : null,
                'split_tasks' => (int) $splitTasks,
                'is_missed' => (int) $isMissed,
                'is_missed_track' => (int) $isMissedTrack,
                'link1' => $str($task->link1),
                'link2' => $str($task->link2),
                'link3' => $str($task->link3),
                'link4' => $str($task->link4),
                'link5' => $str($task->link5),
                'link6' => $str($task->link6),
                'link7' => $str($task->link7),
                'link8' => $str($task->link8),
                'link9' => $str($task->link9),
                'image' => $str($task->image),
                'task_type' => $str($task->task_type),
                'rework_reason' => $task->rework_reason !== null ? $str((string) $task->rework_reason, 65535) : null,
                'deleted_by_email' => $str($user->email ?? ''),
                'deleted_by_name' => $str($user->name ?? ''),
                'deleted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            try {
                \DB::table('deleted_tasks')->insert($fullRow);
            } catch (\Throwable $e) {
                $minimal = [
                    'original_task_id' => (int) $task->id,
                    'title' => $str($task->title ?? '', 255),
                    'assignor' => $str($task->assignor),
                    'assign_to' => $str($assignToRaw),
                    'deleted_by_email' => $str($user->email ?? ''),
                    'deleted_by_name' => $str($user->name ?? ''),
                    'deleted_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                try {
                    \DB::table('deleted_tasks')->insert($minimal);
                } catch (\Throwable $e2) {
                    \Log::warning('saveDeletedTask: archive skipped (table missing or insert failed)', [
                        'task_id' => $task->id,
                        'error' => $e2->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('saveDeletedTask: archive skipped', ['task_id' => $task->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Store selected user in session (called from AJAX)
     */
    public function setSelectedUser(Request $request)
    {
        $userName = $request->input('user_name', '');
        if ($userName) {
            Session::put('selected_user_name', $userName);
        } else {
            Session::forget('selected_user_name');
        }
        return response()->json(['success' => true, 'user_name' => $userName]);
    }

    /**
     * Badge stats from deleted_tasks only (last 30 days by deleted_at).
     */
    public function deletedBadgeStats(Request $request)
    {
        $user = Auth::user();
        $isAdmin = strtolower($user->role ?? '') === 'admin';

        $assignorName = trim((string) $request->query('assignor', ''));
        $assigneeName = trim((string) $request->query('assignee', ''));
        $assignorEmail = $assignorName && $assignorName !== '__NULL__'
            ? User::where('name', $assignorName)->value('email')
            : null;
        $assigneeEmail = $assigneeName && $assigneeName !== '__NULL__'
            ? User::where('name', $assigneeName)->value('email')
            : null;

        $query = DeletedTask::query()
            ->where('deleted_at', '>=', now()->subDays(30));

        if (!$isAdmin) {
            $query->where(function($q) use ($user) {
                $q->where('assignor', $user->email)
                    ->orWhere('assign_to', 'LIKE', '%' . $user->email . '%');
            });
        }

        if ($assignorName !== '') {
            if ($assignorName === '__NULL__') {
                $query->where(function($q) {
                    $q->whereNull('assignor')->orWhere('assignor', '');
                });
            } else {
                $query->where('assignor', $assignorEmail ?: $assignorName);
            }
        }

        if ($assigneeName !== '') {
            if ($assigneeName === '__NULL__') {
                $query->where(function($q) {
                    $q->whereNull('assign_to')->orWhere('assign_to', '');
                });
            } else {
                $query->where('assign_to', 'LIKE', '%' . ($assigneeEmail ?: $assigneeName) . '%');
            }
        }

        $etcMinutes = (float) ((clone $query)->sum('eta_time') ?? 0);
        $atcMinutes = (float) ((clone $query)->sum('etc_done') ?? 0);

        return response()->json([
            'etc_minutes' => $etcMinutes,
            'atc_minutes' => $atcMinutes,
            'etc_hours' => round($etcMinutes / 60, 1),
            'atc_hours' => round($atcMinutes / 60, 1),
        ]);
    }

    /**
     * Display deleted tasks page
     */
    public function deletedIndex()
    {
        $user = Auth::user();
        $isAdmin = strtolower($user->role ?? '') === 'admin';

        // Get selected user from session (set from tasks index page)
        $selectedUserName = Session::get('selected_user_name', '');

        // Calculate statistics for deleted tasks
        $deletedQuery = DeletedTask::query();
        
        if (!$isAdmin) {
            // Non-admin users can only see deleted tasks they created or were assigned to
            $deletedQuery->where(function($query) use ($user) {
                $query->where('assignor', $user->email)
                      ->orWhere('assign_to', 'LIKE', '%' . $user->email . '%');
            });
        }

        // Filter by selected user if set (search in both assignor_name and assignee_name)
        if ($selectedUserName) {
            $deletedQuery->where(function($query) use ($selectedUserName) {
                $query->where('assignor_name', 'LIKE', '%' . $selectedUserName . '%')
                      ->orWhere('assignee_name', 'LIKE', '%' . $selectedUserName . '%');
            });
        }

        $stats = [
            'total' => (clone $deletedQuery)->count(),
            'this_month' => (clone $deletedQuery)->whereMonth('deleted_at', now()->month)->count(),
            'this_week' => (clone $deletedQuery)->whereBetween('deleted_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'today' => (clone $deletedQuery)->whereDate('deleted_at', today())->count(),
        ];

        $stats['etc_total_minutes'] = (int) round((float) ((clone $deletedQuery)->sum(\DB::raw('COALESCE(eta_time, 0)')) ?? 0));
        $stats['atc_total_minutes'] = (int) round((float) ((clone $deletedQuery)->sum(\DB::raw('COALESCE(etc_done, 0)')) ?? 0));

        // TAT badge: average TAT (days from start_date to deleted_at) for deleted tasks in last 30 days
        $last30Query = (clone $deletedQuery)
            ->where('deleted_at', '>=', now()->subDays(30))
            ->whereNotNull('deleted_at')
            ->whereNotNull('start_date');
        $last30Tasks = $last30Query->get();
        $tatValues = [];
        foreach ($last30Tasks as $task) {
            $deleted = \Carbon\Carbon::parse($task->deleted_at);
            $tid = \Carbon\Carbon::parse($task->start_date);
            $days = abs($deleted->getTimestamp() - $tid->getTimestamp()) / 86400;
            $tatValues[] = (int) round($days);
        }
        $stats['tat_avg_30'] = count($tatValues) > 0 ? (int) round(array_sum($tatValues) / count($tatValues)) : null;

        // Daily TAT for line chart (last 30 days): date => avg TAT for tasks deleted on that day
        $tatByDay = [];
        foreach ($last30Tasks as $task) {
            $day = \Carbon\Carbon::parse($task->deleted_at)->format('Y-m-d');
            $deleted = \Carbon\Carbon::parse($task->deleted_at);
            $tid = \Carbon\Carbon::parse($task->start_date);
            $days = abs($deleted->getTimestamp() - $tid->getTimestamp()) / 86400;
            if (!isset($tatByDay[$day])) {
                $tatByDay[$day] = [];
            }
            $tatByDay[$day][] = (int) round($days);
        }
        $tatChartData = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = now()->subDays($i);
            $key = $d->format('Y-m-d');
            $avg = isset($tatByDay[$key]) && count($tatByDay[$key]) > 0
                ? (int) round(array_sum($tatByDay[$key]) / count($tatByDay[$key]))
                : null;
            $tatChartData[] = [
                'date' => $key,
                'label' => $d->format('d M'),
                'avg' => $avg,
            ];
        }

        // Missed badge: count of tasks deleted in last 30 days where status != 'Done'
        $missedQuery = (clone $deletedQuery)
            ->where('deleted_at', '>=', now()->subDays(30))
            ->whereNotNull('deleted_at')
            ->where(function($q) {
                $q->where('status', '!=', 'Done')
                  ->orWhereNull('status')
                  ->orWhere('status', '');
            });
        $stats['missed_count_30'] = $missedQuery->count();

        // Daily missed count for line chart (last 30 days): date => count of missed tasks deleted on that day
        $missedByDay = [];
        $missedTasks = $missedQuery->get();
        foreach ($missedTasks as $task) {
            $day = \Carbon\Carbon::parse($task->deleted_at)->format('Y-m-d');
            $missedByDay[$day] = ($missedByDay[$day] ?? 0) + 1;
        }
        $missedChartData = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = now()->subDays($i);
            $key = $d->format('Y-m-d');
            $count = $missedByDay[$key] ?? 0;
            $missedChartData[] = [
                'date' => $key,
                'label' => $d->format('d M'),
                'count' => $count,
            ];
        }

        $canReviveArchivedTasks = $this->userCanReviveArchivedTasks($user);

        return view('tasks.deleted', compact('stats', 'isAdmin', 'tatChartData', 'missedChartData', 'selectedUserName', 'canReviveArchivedTasks'));
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
                  ->orWhere('assign_to', 'LIKE', '%' . $user->email . '%');
            });
        }

        $deletedTasks = $query->orderBy('deleted_at', 'desc')->get();

        // Add avatar URLs for assignor and assignee; compute TAT (days from deleted_at to start_date/tidDate)
        $defaultAvatar = asset('images/users/avatar-2.jpg');
        $deletedTasks->each(function($task) use ($defaultAvatar) {
            if ($task->assignor) {
                $assignorUser = User::where('email', $task->assignor)->first();
                $task->assignor_avatar = $assignorUser && $assignorUser->avatar
                    ? asset('storage/' . $assignorUser->avatar)
                    : $defaultAvatar;
            } else {
                $task->assignor_avatar = null;
            }
            if ($task->assign_to) {
                $assigneeUser = User::where('email', $task->assign_to)->first();
                $task->assignee_avatar = $assigneeUser && $assigneeUser->avatar
                    ? asset('storage/' . $assigneeUser->avatar)
                    : $defaultAvatar;
            } else {
                $task->assignee_avatar = null;
            }
            // TAT = number of days from tidDate (start_date) to deleted date, one decimal (always positive)
            $task->tat = null;
            if ($task->deleted_at && $task->start_date) {
                $deleted = \Carbon\Carbon::parse($task->deleted_at);
                $tid = \Carbon\Carbon::parse($task->start_date);
                $days = abs($deleted->getTimestamp() - $tid->getTimestamp()) / 86400;
                $task->tat = round($days, 1);
            }
        });

        return response()->json($deletedTasks);
    }

    /**
     * Users allowed to revive tasks from the deleted_tasks archive.
     */
    private function userCanReviveArchivedTasks(?User $user): bool
    {
        $email = strtolower((string) ($user->email ?? ''));

        return in_array($email, ['president@5core.com', 'software5@5core.com'], true);
    }

    /**
     * Revive a deleted/archived task back to active tasks.
     * Access: president@5core.com and software5@5core.com.
     */
    public function reviveDeletedTask($id)
    {
        $user = Auth::user();
        if (!$this->userCanReviveArchivedTasks($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to revive archived tasks.'
            ], 403);
        }

        $deletedTask = DeletedTask::findOrFail($id);

        try {
            \DB::beginTransaction();

            // Prefer restoring original soft-deleted task when available.
            $restored = false;
            if (!empty($deletedTask->original_task_id)) {
                $existing = Task::withTrashed()->find($deletedTask->original_task_id);
                if ($existing && $existing->trashed()) {
                    $existing->restore();
                    $restored = true;
                }
            }

            // Fallback: recreate task from archived snapshot.
            if (!$restored) {
                Task::create([
                    'task_id' => (string) ($deletedTask->task_id ?? ''),
                    'title' => (string) ($deletedTask->title ?? ''),
                    'description' => $deletedTask->description,
                    'group' => $deletedTask->group,
                    'priority' => $deletedTask->priority ?: 'normal',
                    'assignor' => $deletedTask->assignor,
                    'assign_to' => $deletedTask->assign_to,
                    'split_tasks' => (int) ($deletedTask->split_tasks ?? 0),
                    'status' => $deletedTask->status ?: 'Todo',
                    'eta_time' => (int) ($deletedTask->eta_time ?? 0),
                    'start_date' => $deletedTask->start_date,
                    'completion_date' => $deletedTask->completion_date,
                    'due_date' => $deletedTask->completion_date,
                    'completion_day' => (int) ($deletedTask->completion_day ?? 0),
                    'etc_done' => (int) ($deletedTask->etc_done ?? 0),
                    'is_missed' => (int) ($deletedTask->is_missed ?? 0),
                    'is_missed_track' => (int) ($deletedTask->is_missed_track ?? 0),
                    'link1' => $deletedTask->link1,
                    'link2' => $deletedTask->link2,
                    'link3' => $deletedTask->link3,
                    'link4' => $deletedTask->link4,
                    'link5' => $deletedTask->link5,
                    'link6' => $deletedTask->link6,
                    'link7' => $deletedTask->link7,
                    'link8' => $deletedTask->link8,
                    'link9' => $deletedTask->link9,
                    'image' => $deletedTask->image,
                    'task_type' => $deletedTask->task_type ?: 'manual',
                    'rework_reason' => $deletedTask->rework_reason,
                    'workspace' => 0,
                    'order' => 0,
                    'is_data_from' => 0,
                    'is_automate_task' => 0,
                ]);
            }

            // Remove from archived table after successful revive.
            $deletedTask->delete();

            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Task revived successfully.'
            ]);
        } catch (\Throwable $e) {
            \DB::rollBack();
            \Log::error('Failed to revive archived task', [
                'deleted_task_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to revive task. Please try again.'
            ], 500);
        }
    }

    /**
     * Show bulk task create form (optional; modal on index can be used without this).
     */
    public function bulkCreate()
    {
        $users = User::select('id', 'name', 'email')->orderBy('name')->get();
        return view('tasks.bulk-create', compact('users'));
    }

    /**
     * Store multiple tasks at once (bulk create).
     * Accepts: titles (newline-separated or array), common assignee/priority/group/tid/etc.
     */
    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'titles' => 'required|string',
            'priority' => 'required|in:low,normal,high',
            'assignee_id' => 'nullable|exists:users,id',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
            'group' => 'nullable|string|max:255',
            'link1' => 'nullable|string|max:2048',
            'tid' => 'nullable|date',
            'etc_minutes' => 'nullable|integer|min:1',
        ]);

        $titlesRaw = $validated['titles'];
        $titles = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $titlesRaw)));
        if (empty($titles)) {
            return redirect()->back()->with('warning', 'Please enter at least one task title (one per line).');
        }

        $user = Auth::user();
        $isAdmin = strtolower($user->role ?? '') === 'admin';
        $assignorEmail = $user->email;

        $assigneeIds = $request->assignee_ids ?? [];
        if ($request->has('assignee_id') && $request->assignee_id) {
            $assigneeIds = array_unique(array_merge($assigneeIds, [$request->assignee_id]));
        }
        $assigneeEmail = null;
        if (!empty($assigneeIds)) {
            $assigneeEmail = User::whereIn('id', $assigneeIds)->pluck('email')->implode(', ');
        }

        $startDate = $validated['tid'] ?? now();
        $completionDate = \Carbon\Carbon::parse($startDate)->addDays(5);
        $etcMinutes = (int) ($validated['etc_minutes'] ?? 10);
        $group = $validated['group'] ?? null;
        $link1 = $validated['link1'] ?? '';
        $priority = $validated['priority'];

        $created = 0;
        foreach ($titles as $title) {
            if (strlen($title) > 1000) {
                continue;
            }
            $taskData = [
                'title' => $title,
                'description' => null,
                'group' => $group,
                'priority' => $priority,
                'assignor' => $assignorEmail,
                'assign_to' => $assigneeEmail,
                'split_tasks' => 0,
                'status' => 'Todo',
                'eta_time' => $etcMinutes,
                'start_date' => $startDate,
                'completion_date' => $completionDate,
                'due_date' => $completionDate,
                'completion_day' => 0,
                'etc_done' => 0,
                'is_missed' => 0,
                'is_missed_track' => 0,
                'workspace' => 0,
                'order' => 0,
                'task_id' => '',
                'link1' => $link1, 'link2' => '', 'link3' => '', 'link4' => '', 'link5' => '', 'link6' => '', 'link7' => '', 'link8' => '', 'link9' => '',
                'image' => null,
                'is_data_from' => 0,
                'is_automate_task' => 0,
                'task_type' => 'manual',
                'rework_reason' => '',
                'delete_rating' => 0,
                'delete_feedback' => '',
            ];
            Task::create($taskData);
            $created++;
        }

        $message = $created . ' task(s) created successfully.';
        if ($created < count($titles)) {
            $message .= ' ' . (count($titles) - $created) . ' skipped (title too long or empty).';
        }
        return redirect()->route('tasks.index')->with('success', $message);
    }

    /**
     * Get user's Role & Responsibility data.
     * Returns rendered Blade partial as HTML for AJAX injection.
     */
    public function getUserRR(Request $request)
    {
        $userName = $request->input('user_name');
        
        if (empty($userName)) {
            return response()->json([
                'html' => view('partials.rr_card', ['userRR' => null, 'userName' => null])->render()
            ]);
        }

        $user = User::where('name', $userName)->first();
        
        if (!$user) {
            return response()->json([
                'html' => view('partials.rr_card', ['userRR' => null, 'userName' => $userName])->render()
            ]);
        }

        $userRR = $user->userRR;
        
        return response()->json([
            'html' => view('partials.rr_card', ['userRR' => $userRR, 'userName' => $userName])->render()
        ]);
    }

    /**
     * Store or update user's Role & Responsibility.
     */
    public function storeUserRR(Request $request)
    {
        // Log incoming request data for debugging
        \Log::info('storeUserRR - Request data:', [
            'user_id' => $request->user_id,
            'content_length' => $request->content ? strlen($request->content) : 0,
            'content_preview' => $request->content ? substr($request->content, 0, 100) : 'empty',
            'all_input' => $request->all()
        ]);

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'content' => 'nullable|string',
        ]);

        $user = User::findOrFail($request->user_id);
        
        // Get content - handle empty string or null
        $content = $request->input('content');
        if (empty($content) || trim($content) === '') {
            $content = null;
        }

        \Log::info('storeUserRR - Saving data:', [
            'user_id' => $user->id,
            'content_length' => $content ? strlen($content) : 0,
            'content_is_null' => is_null($content)
        ]);
        
        // Store combined content in role field (for simplicity, we use role field to store all content)
        // You can also create a separate 'content' field if preferred
        $userRR = UserRR::updateOrCreate(
            ['user_id' => $user->id],
            [
                'role' => $content, // Store combined content in role field
                'responsibilities' => null, // Clear old separate fields
                'goals' => null,
            ]
        );

        \Log::info('storeUserRR - Saved successfully:', [
            'user_rr_id' => $userRR->id,
            'role_length' => $userRR->role ? strlen($userRR->role) : 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role & Responsibility saved successfully.',
            'userRR' => [
                'id' => $userRR->id,
                'user_id' => $userRR->user_id,
                'role' => $userRR->role,
                'role_length' => $userRR->role ? strlen($userRR->role) : 0
            ]
        ]);
    }

    /**
     * Upload image for TinyMCE editor.
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ]);

        if ($request->hasFile('file')) {
            $image = $request->file('file');
            $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $path = $image->storeAs('public/rr-images', $filename);
            
            return response()->json([
                'location' => asset('storage/rr-images/' . $filename)
            ]);
        }

        return response()->json(['error' => 'No file uploaded'], 400);
    }

    /**
     * Get R&R data for a specific user (for editing).
     */
    public function getUserRRData(Request $request)
    {
        $userId = $request->input('user_id');
        
        if (!$userId) {
            return response()->json(['error' => 'User ID is required'], 400);
        }

        $user = User::findOrFail($userId);
        $userRR = $user->userRR;

        return response()->json([
            'userRR' => $userRR ? [
                'id' => $userRR->id,
                'user_id' => $userRR->user_id,
                'role' => $userRR->role,
                'responsibilities' => $userRR->responsibilities,
                'goals' => $userRR->goals,
                'content' => $userRR->role, // For backward compatibility, use role as content
            ] : null,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]
        ]);
    }

}
