<?php

namespace App\Http\Controllers;

use App\Models\DesignationMgrCheckpoint;
use App\Models\DesignationRrCheckpoint;
use App\Models\DesignationRrItem;
use App\Models\GeneralChecklistItem;
use App\Models\ManagerJunior;
use App\Models\PerformanceReview;
use App\Models\Task;
use App\Models\User;
use App\Models\UserGeneralChecklistProgress;
use App\Models\UserMgrCheckpointProgress;
use App\Models\UserRR;
use App\Models\UserRrCheckpointProgress;
use App\Models\UserRrProgress;
use App\Models\UserScoreHistory;
use App\Models\DeletedTask;
use App\Policies\TaskPolicy;
use App\Services\TaskWhatsAppNotificationService;
use App\Support\OpenAiRequest;
use App\Support\TaskBusinessTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
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

        // Overdue = TID business calendar day + 1 day grace (office timezone).
        $overdueQuery = $this->whereOverdueByBusinessTid(clone $tasksQuery)
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
        $missedTasks = $missedQuery->get();

        // Also include daily-auto tasks that the system already auto-expired into deleted_tasks
        // (see App\Console\Commands\ExpireDailyAutomatedTasks). Without this, the Missed badge would
        // drop to 0 as soon as the nightly cleanup archives them.
        $archivedMissedQuery = DeletedTask::query()
            ->where('is_missed', 1)
            ->where('deleted_at', '>=', now()->subDays(30))
            ->whereNotNull('start_date');
        if (!$isAdmin) {
            $archivedMissedQuery->where(function($query) use ($user) {
                $query->where('assignor', $user->email)
                      ->orWhere('assign_to', 'LIKE', '%' . $user->email . '%');
            });
        }
        if ($selectedUserEmail) {
            $archivedMissedQuery->where(function($query) use ($selectedUserEmail) {
                $query->where('assignor', $selectedUserEmail)
                      ->orWhere('assign_to', 'LIKE', '%' . $selectedUserEmail . '%');
            });
        }
        $archivedMissedTasks = $archivedMissedQuery->get();

        $stats['missed_count_30'] = $missedTasks->count() + $archivedMissedTasks->count();

        // Daily missed count for line chart (last 30 days): date => count of missed tasks started on that day
        $missedByDay = [];
        foreach ($missedTasks as $task) {
            $day = \Carbon\Carbon::parse($task->start_date)->format('Y-m-d');
            $missedByDay[$day] = ($missedByDay[$day] ?? 0) + 1;
        }
        foreach ($archivedMissedTasks as $task) {
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
        $canShowTaskMaintenanceButtons = TaskPolicy::userCanAccessTaskMaintenanceTools($user);

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

        // Training video (header icon): link + whether this user may edit it.
        $trainingVideoLink = $this->getTrainingVideoLink();
        $canEditTrainingVideo = $this->userCanEditTrainingVideo($user);

        return view('tasks.index', compact(
            'stats',
            'isAdmin',
            'users',
            'canDeleteAnyTask',
            'canShowTaskMaintenanceButtons',
            'tatChartData',
            'missedChartData',
            'selectedUserName',
            'assignorOnTasksUsers',
            'assignorOtherUsers',
            'assigneeOnTasksUsers',
            'assigneeOtherUsers',
            'trainingVideoLink',
            'canEditTrainingVideo'
        ) + [
            'taskBusinessTz' => TaskBusinessTime::tz(),
            'taskBusinessTzShort' => TaskBusinessTime::shortLabel(),
            'taskBusinessTzLabel' => TaskBusinessTime::label(),
            'taskBusinessToday' => TaskBusinessTime::today()->toDateString(),
        ]);
    }

    /** Email allowed to add/edit the Task Manager training video link. */
    private const TRAINING_VIDEO_EDITOR_EMAIL = 'mgr-content@5core.com';

    /** Storage path (relative to storage/app) for the persisted training video link. */
    private const TRAINING_VIDEO_FILE = 'task_training_video.json';

    private function userCanEditTrainingVideo($user): bool
    {
        return $user && strtolower(trim($user->email ?? '')) === self::TRAINING_VIDEO_EDITOR_EMAIL;
    }

    private function getTrainingVideoLink(): string
    {
        try {
            if (\Illuminate\Support\Facades\Storage::exists(self::TRAINING_VIDEO_FILE)) {
                $data = json_decode(\Illuminate\Support\Facades\Storage::get(self::TRAINING_VIDEO_FILE), true);
                return is_array($data) ? (string) ($data['link'] ?? '') : '';
            }
        } catch (\Throwable $e) {
            // ignore and fall through to empty
        }
        return '';
    }

    /** Return the current training video link as JSON. */
    public function getTrainingVideo(): JsonResponse
    {
        return response()->json([
            'link' => $this->getTrainingVideoLink(),
            'can_edit' => $this->userCanEditTrainingVideo(Auth::user()),
        ]);
    }

    /** Save the training video link. Restricted to the designated editor email. */
    public function saveTrainingVideo(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$this->userCanEditTrainingVideo($user)) {
            return response()->json(['message' => 'You are not allowed to edit the training video link.'], 403);
        }

        $validated = $request->validate([
            'link' => 'nullable|url|max:2048',
        ]);

        $link = trim((string) ($validated['link'] ?? ''));

        try {
            \Illuminate\Support\Facades\Storage::put(
                self::TRAINING_VIDEO_FILE,
                json_encode(['link' => $link, 'updated_by' => $user->email, 'updated_at' => now()->toIso8601String()])
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to save the link.'], 500);
        }

        return response()->json(['link' => $link, 'message' => 'Training video link saved.']);
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
     * Overdue when office-calendar TID day + 1 full day has passed (matches UI grace).
     */
    protected function whereOverdueByBusinessTid(Builder $query): Builder
    {
        TaskBusinessTime::applyDatabaseSession();

        return $query->whereNotNull('start_date')
            ->whereRaw('DATE(DATE_ADD(DATE(start_date), INTERVAL 1 DAY)) < CURDATE()');
    }

    /**
     * Predicted auto-delete time for daily automated tasks (nightly job at 00:05 office time).
     *
     * @return array<string, mixed>|null
     */
    protected function autoDeleteMetaForTask(Task $task): ?array
    {
        if (!(int) ($task->is_automate_task ?? 0)) {
            return null;
        }
        if (strtolower((string) ($task->schedule_type ?? '')) !== 'daily') {
            return null;
        }
        $status = (string) ($task->status ?? '');
        if (in_array($status, ['Done', 'Archived'], true)) {
            return null;
        }
        if (empty($task->start_date)) {
            return null;
        }

        try {
            $startDay = TaskBusinessTime::parse($task->start_date)->startOfDay();
            $deleteAt = TaskBusinessTime::autoDeleteAtForStartDay($startDay);
            $now = TaskBusinessTime::now();
            $past = $deleteAt->lt($now);
            $tzShort = TaskBusinessTime::shortLabel();

            return [
                'auto_delete_at' => $deleteAt->format('Y-m-d H:i:s'),
                'auto_delete_at_human' => $past
                    ? $deleteAt->format('d M, h:i A').' '.$tzShort.' (pending)'
                    : $deleteAt->format('d M, h:i A').' '.$tzShort,
                'auto_delete_past' => $past,
                'auto_delete_tooltip' => $past
                    ? 'Missed daily auto-delete — removed at next 00:05 '.$tzShort.' run if still incomplete'
                    : 'Auto-deletes at 12:05 AM '.$tzShort.' the day after TID if not marked Done',
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Active user accounts (signed-in capable): matches Team Management “active” users.
     */
    protected function activeTeamUsersQuery(): Builder
    {
        return User::query()
            ->where('is_active', true)
            ->whereNull('deactivated_at');
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

        $tasks = (clone $tasksQuery)->get(['id', 'assign_to', 'assignor', 'status', 'start_date', 'completion_date', 'is_automate_task', 'is_missed', 'eta_time']);

        // tat_sum_days + tat_count are used to compute the average L30 TAT
        // (Turn-Around Time, in calendar days) for tasks the user closed
        // (status=Done) in the last 30 days.
        // missed_l30 counts tasks with is_missed = true whose start_date
        // falls inside the same rolling 30-day window.
        $defaultCounts = [
            'task' => 0, 'overdue' => 0, 'a_task' => 0, 'a_task_h' => 0,
            'need_approval' => 0, 'assignor_task' => 0, 'done' => 0,
            'tat_sum_days' => 0.0, 'tat_count' => 0,
            'missed_l30' => 0,
        ];

        $tatCutoff = \Carbon\Carbon::now()->subDays(30);
        $missedCutoff = $tatCutoff; // same 30-day window

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

                // L30 TAT: tasks the assignee completed (Done) in the last 30
                // days, measuring days from start_date → completion_date.
                if (
                    ($task->status ?? '') === 'Done'
                    && !empty($task->start_date)
                    && !empty($task->completion_date)
                ) {
                    try {
                        $start = \Carbon\Carbon::parse($task->start_date);
                        $end = \Carbon\Carbon::parse($task->completion_date);
                        if ($end->greaterThanOrEqualTo($tatCutoff) && $end->greaterThanOrEqualTo($start)) {
                            // Wall-clock days (fractional). Works on any
                            // Carbon version without needing floatDiffInDays.
                            $days = ($end->getTimestamp() - $start->getTimestamp()) / 86400.0;
                            if ($days < 0) {
                                $days = 0.0;
                            }
                            $byEmail[$email]['tat_sum_days'] += $days;
                            $byEmail[$email]['tat_count']++;
                        }
                    } catch (\Throwable $e) {
                        // Malformed timestamp — silently skip this row's TAT.
                    }
                }

                // L30 Missed: tasks flagged is_missed whose start_date is
                // within the last 30 days.
                if (! empty($task->is_missed) && ! empty($task->start_date)) {
                    try {
                        $startMissed = \Carbon\Carbon::parse($task->start_date);
                        if ($startMissed->greaterThanOrEqualTo($missedCutoff)) {
                            $byEmail[$email]['missed_l30']++;
                        }
                    } catch (\Throwable $e) {
                        // Malformed timestamp — skip silently.
                    }
                }
            }
        }

        $members = $this->activeTeamUsersQuery()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'avatar', 'designation', 'org_level']);

        $scoresByUser = $this->bulkComputeCLScores(
            $members->pluck('id')->all(),
            $members->pluck('designation')->filter()->unique()->values()->all()
        );

        // Fetch TeamLogger data for current month
        $teamLoggerData = [];
        try {
            $teamLoggerService = new \App\Services\TeamLoggerService();
            $currentMonth = \Carbon\Carbon::now()->format('F Y'); // e.g., "May 2026"
            $teamLoggerData = $teamLoggerService->fetchByMonth($currentMonth);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch TeamLogger data for task summary: ' . $e->getMessage());
        }

        $rows = [];
        foreach ($members as $member) {
            $email = $member->email;
            $counts = $byEmail[$email] ?? $defaultCounts;
            
            // Get TeamLogger hours for this user (current month)
            $l30Hours = 0;
            if (isset($teamLoggerData[$email])) {
                $l30Hours = $teamLoggerData[$email]['hours'] ?? 0;
            }
            
            $tatCount = (int) $counts['tat_count'];
            $tatAvgDays = $tatCount > 0 ? round($counts['tat_sum_days'] / $tatCount, 1) : null;

            $rows[] = [
                'user_id' => $member->id,
                'team_member' => $member->name,
                'email' => $email,
                'avatar' => $member->avatar,
                'designation' => $member->designation,
                'org_level' => $member->org_level,
                'task' => $counts['task'],
                'l30_hrs' => round($l30Hours, 1),
                'assignor_task' => $counts['assignor_task'],
                'overdue' => $counts['overdue'],
                'tat_l30_days' => $tatAvgDays,
                'tat_l30_count' => $tatCount,
                'missed_l30' => (int) $counts['missed_l30'],
                'score_clrr' => (int) ($scoresByUser[$member->id]['clrr'] ?? 0),
                'score_clmgr' => (int) ($scoresByUser[$member->id]['clmgr'] ?? 0),
                'score_clgen' => (int) ($scoresByUser[$member->id]['clgen'] ?? 0),
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
     * Compute the three CL scores (CL R&R / CL Mgr / CL Gen own%) for a
     * batch of users in a few bulk queries instead of per-row N+1 lookups.
     *
     * @param  array<int>     $userIds
     * @param  array<string>  $designations
     * @return array<int, array{clrr:int, clmgr:int, clgen:int}>
     */
    protected function bulkComputeCLScores(array $userIds, array $designations): array
    {
        $out = [];
        foreach ($userIds as $uid) {
            $out[(int) $uid] = ['clrr' => 0, 'clmgr' => 0, 'clgen' => 0];
        }
        if (empty($userIds)) {
            return $out;
        }

        // ---------------------- CL Gen (global) ---------------------------
        $genItems = GeneralChecklistItem::get(['id', 'weightage']);
        if ($genItems->isNotEmpty()) {
            $genWeightById = $genItems->mapWithKeys(fn ($i) => [(int) $i->id => max(1, (int) $i->weightage)]);
            $genTotal = $genWeightById->sum();
            if ($genTotal > 0) {
                $genProgress = UserGeneralChecklistProgress::query()
                    ->whereIn('user_id', $userIds)
                    ->where('checked', true)
                    ->whereIn('general_checklist_item_id', $genWeightById->keys())
                    ->get(['user_id', 'general_checklist_item_id']);
                $genEarnedByUser = [];
                foreach ($genProgress as $p) {
                    $uid = (int) $p->user_id;
                    $iid = (int) $p->general_checklist_item_id;
                    $genEarnedByUser[$uid] = ($genEarnedByUser[$uid] ?? 0) + ($genWeightById[$iid] ?? 0);
                }
                foreach ($userIds as $uid) {
                    $earned = $genEarnedByUser[(int) $uid] ?? 0;
                    $out[(int) $uid]['clgen'] = (int) round(($earned / $genTotal) * 100);
                }
            }
        }

        // ---------------------- CL R&R (per designation) ------------------
        $rrItemIdsByDesignation = DesignationRrItem::query()
            ->whereIn('designation', array_values(array_unique(array_filter($designations))))
            ->get(['id', 'designation'])
            ->groupBy('designation')
            ->map(fn ($coll) => $coll->pluck('id')->all());

        // All checkpoints under those items.
        $allRrItemIds = $rrItemIdsByDesignation->flatten()->unique()->values();
        $rrCheckpoints = $allRrItemIds->isEmpty()
            ? collect()
            : DesignationRrCheckpoint::query()
                ->whereIn('designation_rr_item_id', $allRrItemIds)
                ->get(['id', 'designation_rr_item_id', 'weightage']);
        $rrCheckpointWeightById = $rrCheckpoints->mapWithKeys(fn ($c) => [(int) $c->id => max(1, (int) $c->weightage)]);
        $rrCheckpointsByItem = $rrCheckpoints->groupBy('designation_rr_item_id');

        // Total weight per designation = sum of all checkpoint weights under its items.
        $rrTotalByDesignation = [];
        foreach ($rrItemIdsByDesignation as $des => $itemIds) {
            $sum = 0;
            foreach ($itemIds as $iid) {
                foreach (($rrCheckpointsByItem[$iid] ?? []) as $cp) {
                    $sum += max(1, (int) $cp->weightage);
                }
            }
            $rrTotalByDesignation[$des] = $sum;
        }

        $rrProgress = $rrCheckpointWeightById->isEmpty()
            ? collect()
            : UserRrCheckpointProgress::query()
                ->whereIn('user_id', $userIds)
                ->where('checked', true)
                ->whereIn('designation_rr_checkpoint_id', $rrCheckpointWeightById->keys())
                ->get(['user_id', 'designation_rr_checkpoint_id']);

        $rrEarnedByUser = [];
        foreach ($rrProgress as $p) {
            $uid = (int) $p->user_id;
            $cid = (int) $p->designation_rr_checkpoint_id;
            $rrEarnedByUser[$uid] = ($rrEarnedByUser[$uid] ?? 0) + ($rrCheckpointWeightById[$cid] ?? 0);
        }

        // ---------------------- CL Mgr (own % per designation) ------------
        $mgrCheckpoints = DesignationMgrCheckpoint::query()
            ->whereIn('designation', array_values(array_unique(array_filter($designations))))
            ->get(['id', 'designation', 'weightage']);
        $mgrWeightById = $mgrCheckpoints->mapWithKeys(fn ($c) => [(int) $c->id => max(1, (int) $c->weightage)]);
        $mgrTotalByDesignation = $mgrCheckpoints->groupBy('designation')->map(function ($coll) {
            return $coll->sum(fn ($c) => max(1, (int) $c->weightage));
        });

        $mgrProgress = $mgrWeightById->isEmpty()
            ? collect()
            : UserMgrCheckpointProgress::query()
                ->whereIn('user_id', $userIds)
                ->where('checked', true)
                ->whereIn('designation_mgr_checkpoint_id', $mgrWeightById->keys())
                ->get(['user_id', 'designation_mgr_checkpoint_id']);

        $mgrEarnedByUser = [];
        foreach ($mgrProgress as $p) {
            $uid = (int) $p->user_id;
            $cid = (int) $p->designation_mgr_checkpoint_id;
            $mgrEarnedByUser[$uid] = ($mgrEarnedByUser[$uid] ?? 0) + ($mgrWeightById[$cid] ?? 0);
        }

        // ---------------------- Assemble per-user CL R&R + CL Mgr ---------
        // Need each user's designation for the denominator lookup.
        $userDesignations = User::query()
            ->whereIn('id', $userIds)
            ->pluck('designation', 'id');

        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            $des = (string) ($userDesignations[$uid] ?? '');

            // CL R&R
            $rrTotal = (int) ($rrTotalByDesignation[$des] ?? 0);
            if ($rrTotal > 0) {
                $earned = (int) ($rrEarnedByUser[$uid] ?? 0);
                $out[$uid]['clrr'] = (int) round(($earned / $rrTotal) * 100);
            }

            // CL Mgr (own %; the combined-with-juniors number lives in the modal).
            $mgrTotal = (int) ($mgrTotalByDesignation[$des] ?? 0);
            if ($mgrTotal > 0) {
                $earned = (int) ($mgrEarnedByUser[$uid] ?? 0);
                $out[$uid]['clmgr'] = (int) round(($earned / $mgrTotal) * 100);
            }
        }

        return $out;
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

        $overdueQuery = $this->whereOverdueByBusinessTid(clone $q)
            ->where('status', '!=', 'Archived');

        $activeEmailSet = array_flip(
            $this->activeTeamUsersQuery()->pluck('email')->filter()->all()
        );
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

        // Fetch On Sea Transit statistics directly (bypassing cache for now to debug)
        $onSeaPlanningCount = \App\Models\OnSeaTransit::where('status', 'Planning')->count();
        $onSeaTotalCount = \App\Models\OnSeaTransit::count();
        $onSeaArrivedCount = \App\Models\OnSeaTransit::where('status', 'Arrived')->count();
        $onSeaRemainingCount = $onSeaTotalCount - ($onSeaArrivedCount + $onSeaPlanningCount);
        
        // Total value - sum ALL invoice values
        $onSeaTotalValue = \App\Models\OnSeaTransit::sum('invoice_value') ?? 0;
        
        // Total pending amount - sum ALL balances
        $onSeaPendingAmount = \App\Models\OnSeaTransit::sum('balance') ?? 0;
        
        // Debug log
        \Log::info('On Sea Transit Dashboard Data', [
            'planning' => $onSeaPlanningCount,
            'remaining' => $onSeaRemainingCount,
            'total_value' => $onSeaTotalValue,
            'pending' => $onSeaPendingAmount
        ]);

        return view('index', compact('taskDashboardStats', 'onSeaPlanningCount', 'onSeaRemainingCount', 'onSeaTotalValue', 'onSeaPendingAmount'));
    }

    /**
     * Task summary page (same data as {@see getTaskSummaryMemberRows()}).
     *
     * Row visibility is gated by the viewer's org_level (Task Summary
     * "Role" column) — see {@see getTaskSummaryVisibleUserIds()}:
     *  - Admin (system role) or Director → sees every row.
     *  - Manager (org_level='mgr')        → sees self + their juniors only.
     *  - Executive / no role              → sees only their own row.
     *
     * Also ships the manager_juniors pairs as $orgGraph so the front-end
     * can rearrange the table into Director → Mgr → Exec hierarchy groups
     * without a second round-trip. The graph is filtered to the same
     * visible-user set so the hierarchy view never tries to render
     * orphaned rows.
     */
    public function taskSummary()
    {
        $viewer = Auth::user();
        $visibleIds = $this->getTaskSummaryVisibleUserIds($viewer);

        $rows = $this->getTaskSummaryMemberRows();
        if ($visibleIds !== null) {
            $allowed = array_flip($visibleIds);
            $rows = array_values(array_filter($rows, function (array $r) use ($allowed) {
                return isset($allowed[(int) ($r['user_id'] ?? 0)]);
            }));
        }

        $taskDashboardStats = $this->getTaskDashboardAggregates();

        $orgGraphQuery = ManagerJunior::query();
        if ($visibleIds !== null) {
            $orgGraphQuery
                ->whereIn('manager_user_id', $visibleIds)
                ->whereIn('junior_user_id', $visibleIds);
        }
        $orgGraph = $orgGraphQuery
            ->get(['manager_user_id', 'junior_user_id'])
            ->map(function (ManagerJunior $r) {
                return [
                    'm' => (int) $r->manager_user_id,
                    'j' => (int) $r->junior_user_id,
                ];
            })
            ->values()
            ->all();

        $visibility = $this->describeTaskSummaryVisibility($viewer, $visibleIds, count($rows));
        $canEditTags = $this->canEditOrgTags($viewer);

        // Permission flags consumed by the Role-column dropdown — see
        // canChangeOrgLevelOf() for the full rule.
        $orgLevelControl = [
            'can_edit_any' => $canEditTags, // admin / director / shobha
            'is_manager' => strtolower((string) ($viewer->org_level ?? '')) === 'mgr',
        ];

        return view(
            'tasks.task-summary',
            compact('rows', 'taskDashboardStats', 'orgGraph', 'visibility', 'canEditTags', 'orgLevelControl')
        );
    }

    /**
     * Who is allowed to edit org tags from the Task Summary "Role" column?
     *
     * Per business rule: admins, anyone whose org_level is Director, and
     * a designated user named "shobha" (matched on name or email). Everyone
     * else sees the dropdown but not the Tags dot, so they can't reassign
     * juniors.
     */
    protected function canEditOrgTags(?User $viewer): bool
    {
        if (! $viewer) {
            return false;
        }
        if (strtolower((string) ($viewer->role ?? '')) === 'admin') {
            return true;
        }
        if (strtolower((string) ($viewer->org_level ?? '')) === 'director') {
            return true;
        }
        $name = strtolower((string) ($viewer->name ?? ''));
        $email = strtolower((string) ($viewer->email ?? ''));
        if (str_contains($name, 'shobha') || str_contains($email, 'shobha')) {
            return true;
        }
        return false;
    }

    /**
     * Compute the set of user IDs visible to the current viewer for the
     * Task Summary page.
     *
     * @return array<int,int>|null  null = no filter (admin / director).
     */
    protected function getTaskSummaryVisibleUserIds(?User $viewer): ?array
    {
        if (! $viewer) {
            // No authenticated viewer (CLI / unusual context) — block all
            // rows to fail safe; the auth middleware on the route should
            // mean we never actually hit this in practice.
            return [];
        }

        // Admin (system role) — global visibility, same as before.
        if (strtolower((string) $viewer->role) === 'admin') {
            return null;
        }

        $level = strtolower((string) ($viewer->org_level ?? ''));

        if ($level === 'director') {
            return null;
        }

        if ($level === 'mgr') {
            $juniorIds = ManagerJunior::where('manager_user_id', $viewer->id)
                ->pluck('junior_user_id')
                ->map(fn ($v) => (int) $v)
                ->all();

            $ids = array_values(array_unique(array_merge([(int) $viewer->id], $juniorIds)));
            return $ids;
        }

        // Exec / no role → see only self.
        return [(int) $viewer->id];
    }

    /**
     * Build a small descriptor the blade can use to render the visibility
     * banner.  Returns an associative array with `scope` ('all'|'team'|'self'),
     * a human label, the viewer name + role, and how many rows are shown.
     *
     * @return array{scope:string, label:string, role_label:string, viewer:?string, shown:int}
     */
    protected function describeTaskSummaryVisibility(?User $viewer, ?array $visibleIds, int $shown): array
    {
        if ($visibleIds === null) {
            return [
                'scope' => 'all',
                'label' => 'Showing every team member.',
                'role_label' => $viewer && strtolower((string) $viewer->role) === 'admin' ? 'Admin' : 'Director',
                'viewer' => $viewer ? $viewer->name : null,
                'shown' => $shown,
            ];
        }

        $level = $viewer ? strtolower((string) ($viewer->org_level ?? '')) : '';
        if ($level === 'mgr') {
            return [
                'scope' => 'team',
                'label' => 'Showing your own row and the executives tagged to you (' . $shown . ').',
                'role_label' => 'Manager',
                'viewer' => $viewer ? $viewer->name : null,
                'shown' => $shown,
            ];
        }

        return [
            'scope' => 'self',
            'label' => 'Showing only your own row.',
            'role_label' => $level === 'exec' ? 'Executive' : 'Team member',
            'viewer' => $viewer ? $viewer->name : null,
            'shown' => $shown,
        ];
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
            $filterUser = $this->activeTeamUsersQuery()->where('name', $userNameFilter)->first();
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

        // Order:
        //   1. Urgent (priority = 'high') ALWAYS at the top, regardless of TID — these
        //      need eyeballs first and must not get buried by older dated tasks.
        //   2. Then by TID date (asc). Within the same day:
        //      - Default (no user filter): automated tasks on top (us din ka automated task top par)
        //      - When a user is filtered: manual/normal tasks first, then automated (per user request)
        //      start_date is the tiebreaker either way.
        $hasUserFilter = $userNameFilter !== '';
        $automateSortDirection = $hasUserFilter ? 'asc' : 'desc';

        $tasks = $tasksQuery
            ->orderByRaw("(LOWER(COALESCE(priority, '')) = 'high') DESC")
            ->orderByRaw('(start_date IS NULL) ASC, DATE(start_date) ASC')
            ->orderBy('is_automate_task', $automateSortDirection)
            ->orderBy('start_date', 'asc')
            ->get();

        // Map emails to names and avatar URLs for display
        $defaultAvatar = asset('images/users/avatar-2.jpg');
        $tasks->each(function($task) use ($defaultAvatar) {
            // Normalize datetime fields to local string format so frontend date parsing
            // doesn't shift dates because of UTC ISO serialization ("...Z").
            foreach (['start_date', 'due_date', 'completion_date', 'created_at', 'updated_at'] as $dtField) {
                // Use the raw DB value (already stored as office-time wall-clock) and reformat
                // without any timezone conversion. Reading $task->{$dtField} would apply the
                // 'datetime' cast (app TZ = Asia/Kolkata) and a later shift to PT, rolling
                // 00:01 PT auto-tasks back to the previous day.
                $raw = $task->getRawOriginal($dtField);
                if (!empty($raw)) {
                    try {
                        $task->{$dtField} = \Carbon\Carbon::parse($raw)->format('Y-m-d H:i:s');
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
                $task->assignor_designation = $assignorUser ? $assignorUser->designation : null;
                $task->assignor_avatar = $assignorUser && $assignorUser->avatar
                    ? asset('storage/' . $assignorUser->avatar)
                    : $defaultAvatar;
            } else {
                $task->assignor_name = '-';
                $task->assignor_id = null;
                $task->assignor_designation = null;
                $task->assignor_avatar = null;
            }

            if ($task->assign_to) {
                // Handle multiple assignees (comma-separated emails)
                $assigneeEmails = array_map('trim', explode(',', $task->assign_to));
                $assigneeNames = [];
                $assigneeIds = [];
                $assigneeDesignations = [];
                $assigneeAvatars = [];

                foreach ($assigneeEmails as $email) {
                    $assigneeUser = User::where('email', $email)->first();
                    if ($assigneeUser) {
                        $assigneeNames[] = $assigneeUser->name;
                        $assigneeIds[] = $assigneeUser->id;
                        $assigneeDesignations[] = $assigneeUser->designation;
                        $assigneeAvatars[] = $assigneeUser->avatar
                            ? asset('storage/' . $assigneeUser->avatar)
                            : $defaultAvatar;
                    } else {
                        $assigneeNames[] = $email;
                        $assigneeDesignations[] = null;
                        $assigneeAvatars[] = $defaultAvatar;
                    }
                }

                $task->assignee_name = implode(', ', $assigneeNames);
                $task->assignee_id = !empty($assigneeIds) ? $assigneeIds[0] : null; // First ID for compatibility
                $task->assignee_ids = $assigneeIds;
                $task->assignee_designation = !empty($assigneeDesignations) ? $assigneeDesignations[0] : null;
                $task->assignee_designations = $assigneeDesignations;
                $task->assignee_count = count($assigneeNames);
                $task->assignee_avatar = !empty($assigneeAvatars) ? $assigneeAvatars[0] : null;
                $task->assignee_avatars = $assigneeAvatars;
            } else {
                $task->assignee_name = '-';
                $task->assignee_id = null;
                $task->assignee_ids = [];
                $task->assignee_designation = null;
                $task->assignee_designations = [];
                $task->assignee_count = 0;
                $task->assignee_avatar = null;
                $task->assignee_avatars = [];
            }

            // For permission checks
            $task->assignor_email = $task->assignor;
            $task->assignee_email = $task->assign_to;

            $autoDeleteMeta = $this->autoDeleteMetaForTask($task);
            if ($autoDeleteMeta) {
                foreach ($autoDeleteMeta as $key => $value) {
                    $task->{$key} = $value;
                }
            }

            $task->tid_business_date = TaskBusinessTime::businessDateFromStart($task->start_date);
        });

        // Return raw DB attributes (not casted UTC ISO datetimes) so date filters/display
        // align with local task dates in the blade.
        $responseRows = $tasks->map(function ($task) {
            $row = $task->getAttributes(); // raw DB values (e.g. "Y-m-d H:i:s")

            foreach ([
                'assignor_name',
                'assignor_id',
                'assignor_designation',
                'assignor_avatar',
                'assignee_name',
                'assignee_id',
                'assignee_ids',
                'assignee_designation',
                'assignee_designations',
                'assignee_count',
                'assignee_avatar',
                'assignee_avatars',
                'assignor_email',
                'assignee_email',
                'auto_delete_at',
                'auto_delete_at_human',
                'auto_delete_past',
                'auto_delete_tooltip',
                'tid_business_date',
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

        return redirect()->back()->with($flash, $message);
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
                $taskData['assignor_id'] = $assignorUser ? $assignorUser->id : null;
            } else {
                $taskData['assignor_id'] = null;
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

        // Assignees may also open the edit page, but only to attach
        // links — non-link fields are locked in the view + on update().
        $this->authorize('updateLinks', $taskModel);
        $canEditAll = Auth::user()->can('update', $taskModel);

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
        
        return view('tasks.edit', compact('task', 'users', 'canEditAll'));
    }

    public function update(Request $request, $id)
    {
        $task = Task::findOrFail($id);

        // Assignor / admin / president override gets full edit access.
        // Assignees may only attach links, and we silently drop any other
        // fields they try to submit so a tampered form can't reassign or
        // change the date / title / group.
        $this->authorize('updateLinks', $task);
        $user = Auth::user();
        $canEditAll = $user->can('update', $task);

        if ($canEditAll) {
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
        } else {
            // Assignee-only: links are the only thing they can change.
            $validated = $request->validate([
                'l1' => 'nullable|string',
                'l2' => 'nullable|string',
                'training_link' => 'nullable|string',
                'video_link' => 'nullable|string',
                'form_link' => 'nullable|string',
                'form_report_link' => 'nullable|string',
                'checklist_link' => 'nullable|string',
                'pl' => 'nullable|string',
                'process' => 'nullable|string',
            ]);
        }

        $isAdmin = strtolower($user->role ?? '') === 'admin';

        if ($canEditAll) {
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

            // Handle image upload (only the assignor / admin can replace it).
            $imageName = $task->image;
            if ($request->hasFile('image')) {
                if ($task->image && file_exists(public_path('uploads/tasks/' . $task->image))) {
                    unlink(public_path('uploads/tasks/' . $task->image));
                }

                $image = $request->file('image');
                $imageName = time() . '_' . $image->getClientOriginalName();
                $image->move(public_path('uploads/tasks'), $imageName);
            }

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

            $assigneeEmailForNotify = $assigneeEmail;
        } else {
            // Assignee can only change link fields. Everything else stays as-is.
            $updateData = [
                'link1' => $validated['l1'] ?? '',
                'link2' => $validated['l2'] ?? '',
                'link3' => $validated['training_link'] ?? '',
                'link4' => $validated['video_link'] ?? '',
                'link5' => $validated['form_link'] ?? '',
                'link6' => $validated['form_report_link'] ?? '',
                'link7' => $validated['checklist_link'] ?? '',
                'link8' => $validated['pl'] ?? '',
                'link9' => $validated['process'] ?? '',
            ];

            $assigneeEmailForNotify = $task->assign_to;
        }

        $relevantChanged = $this->taskDetailsChanged($task, $updateData);

        $task->update($updateData);

        if ($relevantChanged && $assigneeEmailForNotify) {
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

        // Auto-archive of completed automated tasks disabled (user request) — completed tasks now stay in the active list.
        $archived = false;

        return response()->json([
            'success' => true,
            'message' => 'Task completed successfully!',
            'archived' => $archived,
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

        $archived = false;
        if ($validated['status'] === 'Done') {
            try {
                $this->taskWhatsApp->notifyTaskDone($task->fresh());
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Task WhatsApp notify done failed: ' . $e->getMessage());
            }
            // Auto-archive of completed automated tasks disabled (user request) — completed tasks now stay in the active list.
        } elseif ($validated['status'] === 'Rework') {
            try {
                $this->taskWhatsApp->notifyRework($task->fresh());
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Task WhatsApp notify rework failed: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => $archived
                ? 'Status updated to Done & task auto-archived (visible in Today Deleted for 24h).'
                : 'Status updated successfully!',
            'archived' => $archived,
            'task' => $task->fresh()
        ]);
    }

    /**
     * Auto-archive a completed automated task. Returns true when the task was archived + soft-deleted.
     * No-op for manual tasks, soft-deleted tasks, or non-Done tasks.
     *
     * Stores the row in deleted_tasks with status='Done' so the Today Deleted modal lists it under
     * the user's name (deleted_by_email = current user), letting them undo if Done was clicked by mistake.
     */
    protected function archiveCompletedAutomatedTask(Task $task): bool
    {
        if (!$task->is_automate_task) {
            return false;
        }
        if ($task->trashed()) {
            return false;
        }
        if (($task->status ?? '') !== 'Done') {
            return false;
        }

        try {
            $this->saveDeletedTask($task);
            $task->delete();

            return true;
        } catch (\Throwable $e) {
            \Log::warning('archiveCompletedAutomatedTask failed', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
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
                'action' => 'required|in:delete,priority,tid,assignee,etc,assign_assignee,assign_assignor,duplicate,assignor,freq,group,task',
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
                'group' => 'nullable|string|max:255',
                'task_title' => 'nullable|string|max:500',
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

            case 'group':
                // Bulk update group
                $groupName = $validated['group'] ?? '';
                
                if ($isAutomatedTask) {
                    $updated = \DB::table('automate_tasks')
                        ->whereIn('id', $taskIds)
                        ->update(['group' => $groupName, 'updated_at' => now()]);
                } else {
                    $updated = Task::whereIn('id', $taskIds)
                        ->update(['group' => $groupName, 'updated_at' => now()]);
                }
                
                $groupDisplay = empty($groupName) ? '(empty)' : $groupName;
                return response()->json([
                    'success' => true,
                    'message' => "$updated task(s) group updated to: $groupDisplay"
                ]);

            case 'task':
                // Bulk update task title
                $taskTitle = $validated['task_title'] ?? '';
                
                if (empty($taskTitle)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Task title cannot be empty'
                    ], 400);
                }
                
                if ($isAutomatedTask) {
                    $updated = \DB::table('automate_tasks')
                        ->whereIn('id', $taskIds)
                        ->update(['title' => $taskTitle, 'updated_at' => now()]);
                } else {
                    $updated = Task::whereIn('id', $taskIds)
                        ->update(['title' => $taskTitle, 'updated_at' => now()]);
                }
                
                return response()->json([
                    'success' => true,
                    'message' => "$updated task(s) title updated!"
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

        $successMessage = 'Automated task scheduled! Will execute: ' . $scheduleInfo;
        if ($request->input('after_create') === 'another') {
            return redirect()->route('tasks.automatedCreate')->with('success', $successMessage);
        }

        return redirect()->route('tasks.automated')->with('success', $successMessage);
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
     * List tasks deleted today (office timezone) so users can spot accidental deletions.
     * Visibility: admins see everything; others see only rows where they were assignor or in assign_to,
     * plus anything they themselves deleted today.
     */
    public function todayDeletedData(Request $request)
    {
        $user = Auth::user();
        if (!$user || !TaskPolicy::userCanAccessTaskMaintenanceTools($user)) {
            return response()->json(['data' => [], 'count' => 0], $user ? 403 : 401);
        }

        $todayStart = TaskBusinessTime::todayStart();
        $todayEnd = TaskBusinessTime::todayEnd();

        $query = DeletedTask::query()
            ->whereBetween('deleted_at', [$todayStart, $todayEnd]);

        // True total (not affected by the row limit), and a breakdown so the UI can show
        // "1,103 auto-expired, 19 manual" — useful when the daily-auto cleanup just ran.
        $totalCount = (clone $query)->count();
        $autoCount = (clone $query)->whereRaw('LOWER(deleted_by_email) = ?', ['system@auto'])->count();
        $manualCount = max(0, $totalCount - $autoCount);

        // Cap rows actually rendered to keep the modal snappy. The badge / footer still show the
        // true total above so the user knows nothing is hidden — they can use Refresh after reverting.
        $rowLimit = (int) $request->query('limit', 2000);
        $rowLimit = max(100, min($rowLimit, 5000));

        $rows = $query->orderBy('deleted_at', 'desc')->limit($rowLimit)->get();

        $data = $rows->map(function ($r) {
            return [
                'id' => $r->id,
                'original_task_id' => $r->original_task_id,
                'title' => (string) ($r->title ?? ''),
                'group' => (string) ($r->group ?? ''),
                'priority' => (string) ($r->priority ?? ''),
                'status' => (string) ($r->status ?? ''),
                'assignor' => (string) ($r->assignor ?? ''),
                'assignor_name' => (string) ($r->assignor_name ?? ''),
                'assign_to' => (string) ($r->assign_to ?? ''),
                'assignee_name' => (string) ($r->assignee_name ?? ''),
                'task_type' => (string) ($r->task_type ?? ''),
                'is_missed' => (int) ($r->is_missed ?? 0),
                'deleted_by_email' => (string) ($r->deleted_by_email ?? ''),
                'deleted_by_name' => (string) ($r->deleted_by_name ?? ''),
                'deleted_at' => $r->deleted_at ? \Carbon\Carbon::parse($r->deleted_at)->format('Y-m-d H:i:s') : null,
                'deleted_at_human' => $r->deleted_at ? TaskBusinessTime::formatDisplay(TaskBusinessTime::parse($r->deleted_at)) : '',
                'is_auto_expired' => strtolower((string) ($r->deleted_by_email ?? '')) === 'system@auto',
            ];
        });

        return response()->json([
            'data' => $data,
            'count' => $totalCount,
            'auto_count' => $autoCount,
            'manual_count' => $manualCount,
            'returned' => $data->count(),
            'truncated' => $totalCount > $data->count(),
        ]);
    }

    /**
     * Revert a task that was deleted today (office timezone). Looser permissions than the archive
     * {@see reviveDeletedTask()}: any visible user may undo their own / their tasks' same-day deletion,
     * including system-auto deletions from the daily expire job.
     */
    public function revertTodayDeletedTask($id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }
        if (!TaskPolicy::userCanAccessTaskMaintenanceTools($user)) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to revert deleted tasks.'], 403);
        }

        $deletedTask = DeletedTask::find($id);
        if (!$deletedTask) {
            return response()->json(['success' => false, 'message' => 'Deleted task not found.'], 404);
        }

        $result = $this->revertOneTodayDeleted($deletedTask, $user);

        if ($result['code'] === 200) {
            return response()->json(['success' => true, 'message' => 'Task reverted successfully.']);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to revert task.',
        ], $result['code']);
    }

    /**
     * Bulk-revert today's deletions. Accepts:
     *  - mode=auto    → only rows where deleted_by_email=system@auto (the daily-auto cleanup)
     *  - mode=manual  → only rows deleted by a real user
     *  - mode=all     → both
     *  - ids[]        → explicit IDs (takes precedence over mode)
     *
     * Each row is still permission-checked individually via {@see revertOneTodayDeleted()} so
     * non-admins can only revert tasks they're allowed to see.
     */
    public function bulkRevertTodayDeleted(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }
        if (!TaskPolicy::userCanAccessTaskMaintenanceTools($user)) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to revert deleted tasks.'], 403);
        }

        $mode = strtolower((string) $request->input('mode', 'all'));
        $ids = (array) $request->input('ids', []);
        $ids = array_values(array_filter(array_map('intval', $ids), fn ($v) => $v > 0));

        $todayStart = TaskBusinessTime::todayStart();
        $todayEnd = TaskBusinessTime::todayEnd();

        $query = DeletedTask::query()->whereBetween('deleted_at', [$todayStart, $todayEnd]);

        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        } elseif ($mode === 'auto') {
            $query->whereRaw('LOWER(deleted_by_email) = ?', ['system@auto']);
        } elseif ($mode === 'manual') {
            $query->where(function ($q) {
                $q->whereNull('deleted_by_email')
                  ->orWhere('deleted_by_email', '')
                  ->orWhereRaw('LOWER(deleted_by_email) != ?', ['system@auto']);
            });
        } elseif ($mode !== 'all') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid mode. Use auto, manual, all, or pass ids[].',
            ], 422);
        }

        // Hard cap so a runaway click can't try to revert tens of thousands at once.
        $hardCap = 2000;
        $candidates = $query->orderBy('id')->limit($hardCap + 1)->get();
        $candidateCount = $candidates->count();
        $hitCap = $candidateCount > $hardCap;
        if ($hitCap) {
            $candidates = $candidates->take($hardCap);
        }

        $reverted = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        foreach ($candidates as $task) {
            $result = $this->revertOneTodayDeleted($task, $user);
            if ($result['code'] === 200) {
                $reverted++;
            } elseif (in_array($result['code'], [403, 422], true)) {
                $skipped++;
            } else {
                $failed++;
                if (count($errors) < 5) {
                    $errors[] = "ID {$task->id}: " . ($result['message'] ?? 'unknown error');
                }
            }
        }

        $msg = "Reverted {$reverted} task(s).";
        if ($skipped > 0) {
            $msg .= " Skipped {$skipped} (no permission or not same-day).";
        }
        if ($failed > 0) {
            $msg .= " Failed {$failed}.";
        }
        if ($hitCap) {
            $msg .= " Hit safety cap of {$hardCap} per request — click again to continue.";
        }

        return response()->json([
            'success' => true,
            'reverted' => $reverted,
            'skipped' => $skipped,
            'failed' => $failed,
            'hit_cap' => $hitCap,
            'message' => $msg,
            'errors' => $errors,
        ]);
    }

    /**
     * Internal: revert one DeletedTask row (today only). Returns ['code' => httpStatus, 'message' => ?]
     * Does NOT commit a transaction across rows — each call is its own transaction so partial bulk
     * failures don't roll back everything.
     */
    private function revertOneTodayDeleted(DeletedTask $deletedTask, User $user): array
    {
        $isAdmin = strtolower($user->role ?? '') === 'admin';

        $todayStart = TaskBusinessTime::todayStart();
        $todayEnd = TaskBusinessTime::todayEnd();
        $deletedAt = $deletedTask->deleted_at ? \Carbon\Carbon::parse($deletedTask->deleted_at) : null;
        if (!$deletedAt || $deletedAt->lt($todayStart) || $deletedAt->gt($todayEnd)) {
            return [
                'code' => 422,
                'message' => 'This task was not deleted today and cannot be reverted here. Ask an admin to revive it from the Archive.',
            ];
        }

        if (!$isAdmin) {
            $isInvolved = $deletedTask->assignor === $user->email
                || ($deletedTask->assign_to && str_contains((string) $deletedTask->assign_to, $user->email))
                || $deletedTask->deleted_by_email === $user->email;
            if (!$isInvolved) {
                return [
                    'code' => 403,
                    'message' => 'You are not allowed to revert this task.',
                ];
            }
        }

        try {
            \DB::beginTransaction();

            $restored = false;
            if (!empty($deletedTask->original_task_id)) {
                $existing = Task::withTrashed()->find($deletedTask->original_task_id);
                if ($existing && $existing->trashed()) {
                    $existing->restore();
                    $existing->is_missed = 0;
                    $existing->is_missed_track = 0;
                    if (in_array($existing->status, ['Missed', 'Archived'], true)) {
                        $existing->status = 'Todo';
                    }
                    $existing->save();
                    $restored = true;
                }
            }

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
                    'status' => in_array($deletedTask->status, ['Missed', 'Archived', null], true) ? 'Todo' : $deletedTask->status,
                    'eta_time' => (int) ($deletedTask->eta_time ?? 0),
                    'start_date' => $deletedTask->start_date,
                    'completion_date' => $deletedTask->completion_date,
                    'due_date' => $deletedTask->completion_date,
                    'completion_day' => (int) ($deletedTask->completion_day ?? 0),
                    'etc_done' => (int) ($deletedTask->etc_done ?? 0),
                    'is_missed' => 0,
                    'is_missed_track' => 0,
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

            $deletedTask->delete();

            \DB::commit();

            return ['code' => 200];
        } catch (\Throwable $e) {
            \DB::rollBack();
            \Log::error('revertOneTodayDeleted failed', [
                'deleted_task_id' => $deletedTask->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'code' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Manually run the "expire daily automated tasks" cleanup (admin-only).
     * Mirrors the nightly {@see \App\Console\Commands\ExpireDailyAutomatedTasks} schedule so admins
     * can force a sweep without waiting for the scheduler.
     */
    public function expireDailyAutomatedTasks(Request $request)
    {
        $user = Auth::user();
        if (!TaskPolicy::userCanAccessTaskMaintenanceTools($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to run this cleanup.',
            ], 403);
        }

        try {
            \Illuminate\Support\Facades\Artisan::call('tasks:expire-daily-automated');
            $output = \Illuminate\Support\Facades\Artisan::output();

            $expired = 0;
            if (preg_match('/Expired:\s*(\d+)/i', $output, $m)) {
                $expired = (int) $m[1];
            }

            return response()->json([
                'success' => true,
                'expired' => $expired,
                'message' => $expired > 0
                    ? "Auto-deleted {$expired} incomplete daily task(s) and counted them as Missed."
                    : 'No incomplete daily automated tasks from earlier days.',
                'output' => trim($output),
            ]);
        } catch (\Throwable $e) {
            \Log::error('expireDailyAutomatedTasks failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Cleanup failed: ' . $e->getMessage(),
            ], 500);
        }
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

        // Show only tasks deleted in the last 30 days
        $query = DeletedTask::query()
            ->where('deleted_at', '>=', now()->subDays(30));
        
        if (!$isAdmin) {
            $query->where(function($q) use ($user) {
                $q->where('assignor', $user->email)
                  ->orWhere('assign_to', 'LIKE', '%' . $user->email . '%');
            });
        }

        $deletedTasks = $query->orderBy('deleted_at', 'desc')->get();

        // Performance optimization: Fetch all users in one query instead of N+1 queries
        $allEmails = [];
        foreach ($deletedTasks as $task) {
            if ($task->assignor) {
                $allEmails[] = $task->assignor;
            }
            if ($task->assign_to) {
                $allEmails[] = $task->assign_to;
            }
        }
        $allEmails = array_unique(array_filter($allEmails));
        
        // Single query to fetch all users
        $users = User::whereIn('email', $allEmails)
            ->select('email', 'avatar')
            ->get()
            ->keyBy('email');

        // Add avatar URLs for assignor and assignee; compute TAT (days from deleted_at to start_date/tidDate)
        $defaultAvatar = asset('images/users/avatar-2.jpg');
        $deletedTasks->each(function($task) use ($defaultAvatar, $users) {
            if ($task->assignor) {
                $assignorUser = $users->get($task->assignor);
                $task->assignor_avatar = $assignorUser && $assignorUser->avatar
                    ? asset('storage/' . $assignorUser->avatar)
                    : $defaultAvatar;
            } else {
                $task->assignor_avatar = null;
            }
            if ($task->assign_to) {
                $assigneeUser = $users->get($task->assign_to);
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
        return TaskPolicy::userCanAccessTaskMaintenanceTools($user);
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

    /**
     * ---------------------------------------------------------------------
     * Designation-level R&R (Task Summary "R&R" magnifying-glass column)
     * ---------------------------------------------------------------------
     *
     * The Task Summary R&R modal is keyed on the user's designation. All
     * users sharing a designation see the same list of items (seeded by
     * AI the first time a designation is opened, manually edited
     * afterwards), but each user owns their own progress (status + note)
     * on each item.
     */

    /**
     * Return designation-level R&R items + the requested user's progress.
     * Used by the Task Summary R&R modal as its primary data source.
     */
    public function getDesignationRR(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'user_email' => 'nullable|email',
            'designation' => 'nullable|string|max:191',
        ]);

        $user = null;
        if (! empty($validated['user_id'])) {
            $user = User::find($validated['user_id']);
        } elseif (! empty($validated['user_email'])) {
            $user = User::where('email', $validated['user_email'])->first();
        }

        $designation = trim((string) ($validated['designation']
            ?? ($user->designation ?? '')));

        if ($designation === '') {
            return response()->json([
                'designation' => '',
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'designation' => null,
                ] : null,
                'items' => [],
                'needs_ai_seed' => false,
                'message' => 'No designation set for this user. Set a designation on the user record first.',
            ]);
        }

        $items = DesignationRrItem::where('designation', $designation)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $progressByItem = [];
        if ($user && $items->isNotEmpty()) {
            $progressByItem = UserRrProgress::where('user_id', $user->id)
                ->whereIn('designation_rr_item_id', $items->pluck('id'))
                ->get()
                ->keyBy('designation_rr_item_id');
        }

        $payload = $items->map(function (DesignationRrItem $item) use ($progressByItem, $user) {
            $progress = $user
                ? ($progressByItem[$item->id] ?? null)
                : null;

            return [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'sort_order' => $item->sort_order,
                'source' => $item->source,
                'status' => $progress ? $progress->status : UserRrProgress::STATUS_PENDING,
                'note' => $progress ? $progress->note : null,
                'done_at' => $progress && $progress->done_at ? $progress->done_at->toDateTimeString() : null,
            ];
        })->values();

        $done = $payload->where('status', UserRrProgress::STATUS_DONE)->count();
        $inProgress = $payload->where('status', UserRrProgress::STATUS_IN_PROGRESS)->count();
        $total = $payload->count();

        return response()->json([
            'designation' => $designation,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'designation' => $user->designation,
            ] : null,
            'items' => $payload,
            'needs_ai_seed' => $items->isEmpty(),
            'progress' => [
                'total' => $total,
                'done' => $done,
                'in_progress' => $inProgress,
                'percent' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
            ],
        ]);
    }

    /**
     * Ask OpenAI for a starter list of R&R items for the given designation
     * and persist them as `source = ai`. Idempotent: if items already exist
     * for the designation we return the existing list untouched.
     */
    public function generateDesignationRR(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'designation' => 'required|string|max:191',
        ]);

        $designation = trim($validated['designation']);
        if ($designation === '') {
            return response()->json([
                'success' => false,
                'message' => 'Designation is required.',
            ], 422);
        }

        $existing = DesignationRrItem::where('designation', $designation)->count();
        if ($existing > 0) {
            return response()->json([
                'success' => true,
                'created' => 0,
                'message' => 'R&R already exists for this designation.',
            ]);
        }

        $generated = $this->generateRRViaOpenAi($designation);
        if (empty($generated)) {
            // AI failed (no key, network error, parse error). Fall back to
            // a generic starter set so the UI never breaks.
            $generated = $this->fallbackRRStarterSet($designation);
        }

        $createdById = optional(Auth::user())->id;
        $rows = [];
        foreach (array_values($generated) as $idx => $item) {
            $title = trim((string) ($item['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $rows[] = DesignationRrItem::create([
                'designation' => $designation,
                'title' => mb_substr($title, 0, 500),
                'description' => isset($item['description']) ? trim((string) $item['description']) : null,
                'sort_order' => $idx + 1,
                'source' => 'ai',
                'created_by' => $createdById,
            ]);
        }

        return response()->json([
            'success' => true,
            'created' => count($rows),
            'designation' => $designation,
        ]);
    }

    /**
     * Add a single manual R&R item to a designation (from the modal "+" button).
     */
    public function addDesignationRRItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'designation' => 'required|string|max:191',
            'title' => 'required|string|max:500',
            'description' => 'nullable|string|max:2000',
        ]);

        $designation = trim($validated['designation']);
        $nextOrder = (int) DesignationRrItem::where('designation', $designation)->max('sort_order') + 1;

        $item = DesignationRrItem::create([
            'designation' => $designation,
            'title' => trim($validated['title']),
            'description' => isset($validated['description']) ? trim($validated['description']) : null,
            'sort_order' => $nextOrder,
            'source' => 'manual',
            'created_by' => optional(Auth::user())->id,
        ]);

        return response()->json([
            'success' => true,
            'item' => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'sort_order' => $item->sort_order,
                'source' => $item->source,
                'status' => UserRrProgress::STATUS_PENDING,
                'note' => null,
                'done_at' => null,
            ],
        ]);
    }

    /**
     * Ask AI to suggest a single new R&R item for a designation, aware of
     * the items already defined so it doesn't duplicate. Used by the
     * "Ask AI" button next to "Add" in the R&R modal.
     */
    public function suggestDesignationRRItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'designation' => 'required|string|max:191',
            'hint' => 'nullable|string|max:500', // optional draft text from the input box
        ]);

        $designation = trim($validated['designation']);
        $hint = isset($validated['hint']) ? trim((string) $validated['hint']) : '';
        if ($designation === '') {
            return response()->json([
                'success' => false,
                'message' => 'Designation is required.',
            ], 422);
        }

        $existingTitles = DesignationRrItem::where('designation', $designation)
            ->orderBy('sort_order')->orderBy('id')
            ->pluck('title')
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->values()
            ->all();

        $result = $this->suggestOneRRViaOpenAi($designation, $existingTitles, $hint);
        $suggestion = $result['suggestion'] ?? null;

        if (empty($suggestion) || empty(trim((string) ($suggestion['title'] ?? '')))) {
            $reason = $result['error'] ?? null;
            $msg = 'AI could not generate a new responsibility. Try again or add one manually.';
            if ($reason && (config('app.debug') || app()->environment('local'))) {
                $msg .= ' (' . $reason . ')';
            }
            return response()->json([
                'success' => false,
                'message' => $msg,
                'debug' => app()->environment('local') ? $reason : null,
            ], 502);
        }

        $createdById = optional(Auth::user())->id;
        $nextOrder = (int) DesignationRrItem::where('designation', $designation)->max('sort_order') + 1;

        $item = DesignationRrItem::create([
            'designation' => $designation,
            'title' => mb_substr(trim((string) $suggestion['title']), 0, 500),
            'description' => isset($suggestion['description']) ? trim((string) $suggestion['description']) : null,
            'sort_order' => $nextOrder,
            'source' => 'ai',
            'created_by' => $createdById,
        ]);

        return response()->json([
            'success' => true,
            'item' => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'sort_order' => $item->sort_order,
                'source' => $item->source,
                'status' => UserRrProgress::STATUS_PENDING,
                'note' => null,
                'done_at' => null,
            ],
        ]);
    }

    /**
     * Ask OpenAI for ONE additional, non-duplicate R&R bullet for a designation.
     *
     * The optional $hint is treated as the user's draft / intent — if
     * provided, AI is instructed to refine it into a proper bullet (so
     * "Ask AI" works as "refine what I just typed" when the box has text).
     *
     * @param  array<int,string>  $existingTitles
     * @return array{suggestion: array{title:string, description?:string|null}|null, error: string|null}
     */
    protected function suggestOneRRViaOpenAi(string $designation, array $existingTitles, string $hint = ''): array
    {
        $existingList = empty($existingTitles)
            ? '(none yet)'
            : implode("\n", array_map(fn ($t) => '- ' . $t, $existingTitles));

        $system = 'You are an HR + operations expert. Given a job designation, the Roles & '
            . 'Responsibilities already defined for it, and (optionally) the user\'s draft text, '
            . 'produce ONE R&R bullet that is: (a) action-oriented (start with a verb), '
            . '(b) specific and trackable so a manager can mark it Done, (c) NOT a duplicate or '
            . 'paraphrase of any existing bullet. If the user gave a draft, REFINE it into a clean '
            . 'bullet rather than discarding the intent. Return ONLY valid JSON: '
            . '{"item":{"title":"string (<=120 chars)","description":"string (<=240 chars, optional context)"}}';

        $userMsg = "Designation: {$designation}\nExisting R&R bullets:\n{$existingList}";
        if ($hint !== '') {
            $userMsg .= "\n\nUser's draft text to refine into one R&R bullet:\n{$hint}";
        } else {
            $userMsg .= "\n\nSuggest one additional R&R bullet.";
        }

        $ai = $this->callAiJson($system, $userMsg, 60, 0.4);
        if ($ai['text'] === null) {
            return ['suggestion' => null, 'error' => $ai['error'] ?? 'AI call failed.'];
        }

        $text = $ai['text'];
        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            // As a final fallback, treat the raw text as the title.
            $fallbackTitle = mb_substr(trim($text), 0, 120);
            if ($fallbackTitle !== '') {
                return [
                    'suggestion' => ['title' => $fallbackTitle, 'description' => null],
                    'error' => null,
                ];
            }
            \Log::warning('R&R suggest: AI returned non-JSON', ['text' => $text]);
            return ['suggestion' => null, 'error' => 'AI returned non-JSON.'];
        }

        // Accept many shapes — the model occasionally wraps the bullet differently.
        $row = $decoded['item']
            ?? $decoded['responsibility']
            ?? $decoded['result']
            ?? (isset($decoded['items'][0]) && is_array($decoded['items'][0]) ? $decoded['items'][0] : null)
            ?? $decoded;
        if (! is_array($row)) {
            return ['suggestion' => null, 'error' => 'AI response was the wrong shape.'];
        }

        $title = trim((string) (
            $row['title']
                ?? $row['name']
                ?? $row['responsibility']
                ?? $row['bullet']
                ?? $row['text']
                ?? ''
        ));
        if ($title === '') {
            return ['suggestion' => null, 'error' => 'AI did not return a title.'];
        }
        return [
            'suggestion' => [
                'title' => $title,
                'description' => isset($row['description']) ? trim((string) $row['description']) : null,
            ],
            'error' => null,
        ];
    }

    /**
     * Edit a single R&R item (title and/or description).  Used by the
     * inline-edit pencil in the R&R modal.
     */
    public function updateDesignationRRItem(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:2000',
        ]);

        $item = DesignationRrItem::findOrFail($id);

        if (array_key_exists('title', $validated) && $validated['title'] !== null && trim($validated['title']) !== '') {
            $item->title = mb_substr(trim($validated['title']), 0, 500);
        }
        if (array_key_exists('description', $validated)) {
            $item->description = $validated['description'] !== null
                ? trim($validated['description'])
                : null;
        }
        $item->save();

        return response()->json([
            'success' => true,
            'item' => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
            ],
        ]);
    }

    /**
     * Delete an R&R item from a designation. Cascades user progress rows.
     */
    public function deleteDesignationRRItem(int $id): JsonResponse
    {
        $item = DesignationRrItem::findOrFail($id);
        $item->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Append a snapshot row to user_score_history for one (user, score_type).
     *
     * Called from the three CL toggle endpoints right after a successful
     * write so the lifetime-graph dot has fresh data with no cron / job.
     */
    protected function snapshotUserScore(int $userId, string $scoreType, int $percent): void
    {
        $percent = max(0, min(100, (int) $percent));
        try {
            UserScoreHistory::create([
                'user_id' => $userId,
                'score_type' => $scoreType,
                'percent' => $percent,
                'captured_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Never break a toggle save because of history-snapshot issues.
            \Log::warning('snapshotUserScore failed', [
                'user_id' => $userId,
                'score_type' => $scoreType,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update a user's progress on a single R&R item (pending / in_progress / done).
     * Used both when toggling status and when saving the per-item note.
     */
    public function updateUserRRProgress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'designation_rr_item_id' => 'required|integer|exists:designation_rr_items,id',
            'status' => ['nullable', Rule::in(UserRrProgress::STATUSES)],
            'note' => 'nullable|string|max:2000',
        ]);

        $payload = [];
        if (array_key_exists('status', $validated) && $validated['status'] !== null) {
            $payload['status'] = $validated['status'];
            $payload['done_at'] = $validated['status'] === UserRrProgress::STATUS_DONE ? now() : null;
        }
        if (array_key_exists('note', $validated)) {
            $payload['note'] = $validated['note'];
        }

        if (empty($payload)) {
            return response()->json(['success' => false, 'message' => 'Nothing to update.'], 422);
        }

        $progress = UserRrProgress::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'designation_rr_item_id' => $validated['designation_rr_item_id'],
            ],
            $payload
        );

        return response()->json([
            'success' => true,
            'progress' => [
                'id' => $progress->id,
                'status' => $progress->status,
                'note' => $progress->note,
                'done_at' => $progress->done_at ? $progress->done_at->toDateTimeString() : null,
            ],
        ]);
    }

    /**
     * Call a JSON-returning LLM. Tries OpenAI first (gpt-4o-mini class),
     * and on ANY failure (missing key, 401, network, empty body) falls back
     * to Anthropic Claude. Returns the raw text the model produced (still
     * needs JSON parsing by the caller).
     *
     * Strips ```json fences automatically since Claude often wraps its
     * answers in markdown even when asked not to.
     *
     * @return array{text: string|null, error: string|null, provider: string|null}
     */
    protected function callAiJson(string $system, string $userMsg, int $timeoutSeconds = 60, float $temperature = 0.4): array
    {
        $stripFences = static function (string $t): string {
            $t = trim($t);
            $t = preg_replace('/^```(?:json)?\s*/i', '', $t) ?? $t;
            $t = preg_replace('/\s*```\s*$/i', '', $t) ?? $t;
            return trim($t);
        };

        // ---------- Try OpenAI first ----------
        $lastError = null;
        $openAiKey = config('services.openai.key');
        if (is_string($openAiKey) && $openAiKey !== '') {
            $headers = OpenAiRequest::authHeaders();
            $model = (string) config('services.openai.title_master_stack_model', 'gpt-4o-mini');
            try {
                $response = Http::withHeaders($headers)
                    ->timeout($timeoutSeconds)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => $model,
                        'temperature' => $temperature,
                        'response_format' => ['type' => 'json_object'],
                        'messages' => [
                            ['role' => 'system', 'content' => $system],
                            ['role' => 'user', 'content' => $userMsg],
                        ],
                    ]);
                if ($response->successful()) {
                    $text = (string) ($response->json('choices.0.message.content') ?? '');
                    $text = $stripFences($text);
                    if ($text !== '') {
                        return ['text' => $text, 'error' => null, 'provider' => 'openai'];
                    }
                    $lastError = 'OpenAI returned an empty response.';
                } else {
                    $msg = (string) ($response->json('error.message') ?? '');
                    $lastError = 'OpenAI ' . $response->status() . ($msg !== '' ? ': ' . $msg : '');
                    \Log::warning('callAiJson: OpenAI failed, will try Claude', [
                        'status' => $response->status(),
                        'error' => $msg,
                    ]);
                }
            } catch (\Throwable $e) {
                $lastError = 'OpenAI exception: ' . $e->getMessage();
                \Log::warning('callAiJson: OpenAI exception, will try Claude', ['msg' => $e->getMessage()]);
            }
        } else {
            $lastError = 'OpenAI key not configured.';
        }

        // ---------- Fall back to Claude ----------
        $claudeKey = config('services.anthropic.key');
        if (! is_string($claudeKey) || $claudeKey === '') {
            return [
                'text' => null,
                'error' => ($lastError ?: 'No AI provider configured.') . ' (Claude key also missing)',
                'provider' => null,
            ];
        }
        $claudeModel = (string) config('services.anthropic.model', 'claude-haiku-4-5-20251001');
        $version = (string) config('services.anthropic.version', '2023-06-01');
        try {
            $response = Http::withHeaders([
                    'x-api-key' => $claudeKey,
                    'anthropic-version' => $version,
                    'content-type' => 'application/json',
                ])
                ->timeout($timeoutSeconds)
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $claudeModel,
                    'max_tokens' => 2048,
                    'temperature' => $temperature,
                    // Reinforce JSON-only on Claude (it often wraps in fences).
                    'system' => $system . "\n\nIMPORTANT: Return ONLY a single valid JSON object. No prose, no preamble, no markdown code fences.",
                    'messages' => [
                        ['role' => 'user', 'content' => $userMsg],
                    ],
                ]);
            if (! $response->successful()) {
                $err = (string) ($response->json('error.message') ?? '');
                $err = $err !== '' ? $err : ('HTTP ' . $response->status());
                \Log::warning('callAiJson: Claude failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [
                    'text' => null,
                    'error' => ($lastError ? $lastError . ' · ' : '') . 'Claude error: ' . $err,
                    'provider' => 'claude',
                ];
            }
            $text = '';
            foreach ((array) $response->json('content', []) as $block) {
                if (is_array($block) && (($block['type'] ?? '') === 'text') && isset($block['text'])) {
                    $text .= (string) $block['text'];
                }
            }
            $text = $stripFences($text);
            if ($text === '') {
                return [
                    'text' => null,
                    'error' => ($lastError ? $lastError . ' · ' : '') . 'Claude returned an empty response.',
                    'provider' => 'claude',
                ];
            }
            return ['text' => $text, 'error' => null, 'provider' => 'claude'];
        } catch (\Throwable $e) {
            \Log::error('callAiJson: Claude call failed', ['msg' => $e->getMessage()]);
            return [
                'text' => null,
                'error' => ($lastError ? $lastError . ' · ' : '') . 'Claude network error: ' . $e->getMessage(),
                'provider' => 'claude',
            ];
        }
    }

    /**
     * Generate the initial R&R list for a designation. Uses {@see callAiJson()}
     * which prefers OpenAI but transparently falls back to Claude.
     *
     * @return array<int, array{title: string, description?: string|null}>
     */
    protected function generateRRViaOpenAi(string $designation): array
    {
        $system = 'You are an HR + operations expert. Given a job designation, produce a concise, '
            . 'practical list of 8 to 12 day-to-day Roles & Responsibilities. Each item must be '
            . 'action-oriented (start with a verb), specific to the role, and trackable so a manager '
            . 'can mark it Done. Return ONLY valid JSON with this exact shape: '
            . '{"items":[{"title":"string (<=120 chars)","description":"string (<=240 chars, optional context)"}]}';

        $userMsg = "Designation: {$designation}\nGenerate the R&R list for this designation.";

        $ai = $this->callAiJson($system, $userMsg, 60, 0.3);
        if ($ai['text'] === null) {
            \Log::warning('Designation R&R: AI call failed', ['error' => $ai['error']]);
            return [];
        }

        $decoded = json_decode($ai['text'], true);
        if (! is_array($decoded)) {
            \Log::warning('Designation R&R: AI returned non-JSON', ['text' => $ai['text']]);
            return [];
        }

        $items = $decoded['items'] ?? $decoded;
        if (! is_array($items)) {
            return [];
        }

        $clean = [];
        foreach ($items as $row) {
            if (is_string($row)) {
                $clean[] = ['title' => $row, 'description' => null];
                continue;
            }
            if (! is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? $row['name'] ?? $row['responsibility'] ?? ''));
            if ($title === '') {
                continue;
            }
            $clean[] = [
                'title' => $title,
                'description' => isset($row['description']) ? trim((string) $row['description']) : null,
            ];
        }

        return $clean;
    }

    /**
     * Generic starter R&R used when AI is unavailable so the modal always
     * has something to show on first open.
     *
     * @return array<int, array{title: string, description: string|null}>
     */
    protected function fallbackRRStarterSet(string $designation): array
    {
        return [
            ['title' => 'Define daily / weekly priorities for the ' . $designation . ' role', 'description' => null],
            ['title' => 'Own deliverables and report progress to the reporting manager', 'description' => null],
            ['title' => 'Collaborate with cross-functional teams to unblock dependencies', 'description' => null],
            ['title' => 'Maintain documentation / SOPs for repeatable tasks', 'description' => null],
            ['title' => 'Identify process improvements and raise them to the team lead', 'description' => null],
            ['title' => 'Respond to escalations within the agreed SLA', 'description' => null],
        ];
    }

    /**
     * ---------------------------------------------------------------------
     * CL R&R — Checklist of checkpoints under each designation R&R item
     * ---------------------------------------------------------------------
     *
     * Sits one level under {@see getDesignationRR()}: every R&R item gets
     * a set of weighted checkpoints (AI-seeded, manually editable). A
     * user's progress is the boolean check state on each checkpoint, which
     * is then rolled up via weightages into per-item and overall scores.
     *
     * Score formula
     * -------------
     *  item_score    = sum(weightage of checked checkpoints in item)
     *                / sum(weightage of all checkpoints in item) * 100
     *  overall_score = sum(weightage of checked checkpoints across all items)
     *                / sum(weightage of all checkpoints across all items) * 100
     */

    /**
     * Return the full checklist payload for a user: every R&R item with
     * its checkpoints, the user's check state, and per-item + overall scores.
     */
    public function getDesignationChecklist(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'designation' => 'nullable|string|max:191',
        ]);

        $user = null;
        if (! empty($validated['user_id'])) {
            $user = User::find($validated['user_id']);
        }

        $designation = trim((string) ($validated['designation']
            ?? ($user->designation ?? '')));

        if ($designation === '') {
            return response()->json([
                'designation' => '',
                'user' => $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'designation' => null] : null,
                'items' => [],
                'needs_rr_seed' => true,
                'needs_checklist_seed' => false,
                'message' => 'No designation set for this user. Set a designation on the user record first.',
                'overall' => ['percent' => 0, 'earned' => 0, 'total' => 0, 'checked' => 0, 'count' => 0],
            ]);
        }

        $items = DesignationRrItem::with('checkpoints')
            ->where('designation', $designation)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($items->isEmpty()) {
            // The R&R hasn't been seeded yet — the CL R&R modal will prompt
            // the user to open the R&R modal first.
            return response()->json([
                'designation' => $designation,
                'user' => $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'designation' => $user->designation] : null,
                'items' => [],
                'needs_rr_seed' => true,
                'needs_checklist_seed' => false,
                'overall' => ['percent' => 0, 'earned' => 0, 'total' => 0, 'checked' => 0, 'count' => 0],
            ]);
        }

        return response()->json(
            $this->buildRRChecklistPayload($designation, $items, $user)
        );
    }

    /**
     * Build the full checklist response payload — used both by the GET
     * endpoint and after AI generation so the modal can hot-swap state.
     */
    protected function buildRRChecklistPayload(string $designation, $items, ?User $user): array
    {
        // Pre-fetch progress in one query keyed by checkpoint id.
        $progressByCheckpoint = collect();
        if ($user) {
            $checkpointIds = $items->flatMap(function (DesignationRrItem $i) {
                return $i->checkpoints->pluck('id');
            });
            if ($checkpointIds->isNotEmpty()) {
                $progressByCheckpoint = UserRrCheckpointProgress::where('user_id', $user->id)
                    ->whereIn('designation_rr_checkpoint_id', $checkpointIds)
                    ->get()
                    ->keyBy('designation_rr_checkpoint_id');
            }
        }

        $overallEarned = 0;
        $overallTotal = 0;
        $overallChecked = 0;
        $overallCount = 0;
        $needsChecklistSeed = false;

        $itemRows = $items->map(function (DesignationRrItem $item) use ($progressByCheckpoint, &$overallEarned, &$overallTotal, &$overallChecked, &$overallCount, &$needsChecklistSeed) {
            $itemTotal = 0;
            $itemEarned = 0;
            $itemChecked = 0;
            if ($item->checkpoints->isEmpty()) {
                $needsChecklistSeed = true;
            }

            $checkpoints = $item->checkpoints->map(function (DesignationRrCheckpoint $cp) use ($progressByCheckpoint, &$itemTotal, &$itemEarned, &$itemChecked) {
                $weight = max(1, (int) $cp->weightage);
                $progress = $progressByCheckpoint->get($cp->id);
                $checked = $progress ? (bool) $progress->checked : false;
                $itemTotal += $weight;
                if ($checked) {
                    $itemEarned += $weight;
                    $itemChecked++;
                }
                return [
                    'id' => $cp->id,
                    'title' => $cp->title,
                    'description' => $cp->description,
                    'weightage' => $weight,
                    'sort_order' => $cp->sort_order,
                    'source' => $cp->source,
                    'checked' => $checked,
                    'note' => $progress ? $progress->note : null,
                    'checked_at' => $progress && $progress->checked_at ? $progress->checked_at->toDateTimeString() : null,
                ];
            })->values();

            $overallEarned += $itemEarned;
            $overallTotal += $itemTotal;
            $overallChecked += $itemChecked;
            $overallCount += $item->checkpoints->count();

            return [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'sort_order' => $item->sort_order,
                'checkpoints' => $checkpoints,
                'score' => [
                    'percent' => $itemTotal > 0 ? (int) round(($itemEarned / $itemTotal) * 100) : 0,
                    'earned' => $itemEarned,
                    'total' => $itemTotal,
                    'checked' => $itemChecked,
                    'count' => $item->checkpoints->count(),
                ],
            ];
        })->values();

        return [
            'designation' => $designation,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'designation' => $user->designation,
            ] : null,
            'items' => $itemRows,
            'needs_rr_seed' => false,
            'needs_checklist_seed' => $needsChecklistSeed,
            'overall' => [
                'percent' => $overallTotal > 0 ? (int) round(($overallEarned / $overallTotal) * 100) : 0,
                'earned' => $overallEarned,
                'total' => $overallTotal,
                'checked' => $overallChecked,
                'count' => $overallCount,
            ],
        ];
    }

    /**
     * Ask AI for checkpoint sets per R&R item in a designation.
     *
     * Body params:
     *  - designation (required)
     *  - item_id     (optional) — regenerate just this single R&R item
     *  - force       (optional bool) — wipe existing checkpoints first (refresh)
     */
    public function generateDesignationChecklist(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'designation' => 'required|string|max:191',
            'item_id' => 'nullable|integer|exists:designation_rr_items,id',
            'force' => 'nullable|boolean',
        ]);

        $designation = trim($validated['designation']);
        $force = (bool) ($validated['force'] ?? false);

        $itemsQuery = DesignationRrItem::where('designation', $designation);
        if (! empty($validated['item_id'])) {
            $itemsQuery->where('id', $validated['item_id']);
        }
        $items = $itemsQuery->orderBy('sort_order')->orderBy('id')->get();

        if ($items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No R&R items found for this designation. Open the R&R modal first to seed them.',
            ], 422);
        }

        $totalCreated = 0;
        foreach ($items as $item) {
            $existing = $item->checkpoints()->count();
            if ($existing > 0 && ! $force) {
                continue; // Don't duplicate; refresh requires force=true.
            }
            if ($force && $existing > 0) {
                $item->checkpoints()->delete();
            }

            $generated = $this->generateChecklistViaOpenAi($designation, $item->title, $item->description);
            if (empty($generated)) {
                $generated = $this->fallbackChecklistStarterSet($item->title);
            }

            $createdById = optional(Auth::user())->id;
            foreach (array_values($generated) as $idx => $cp) {
                $title = trim((string) ($cp['title'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $weight = (int) ($cp['weightage'] ?? 1);
                $weight = max(1, min(10, $weight));
                DesignationRrCheckpoint::create([
                    'designation_rr_item_id' => $item->id,
                    'title' => mb_substr($title, 0, 500),
                    'description' => isset($cp['description']) ? trim((string) $cp['description']) : null,
                    'weightage' => $weight,
                    'sort_order' => $idx + 1,
                    'source' => 'ai',
                    'created_by' => $createdById,
                ]);
                $totalCreated++;
            }
        }

        return response()->json([
            'success' => true,
            'created' => $totalCreated,
            'designation' => $designation,
        ]);
    }

    /**
     * Add a single manual checkpoint to a designation R&R item.
     */
    public function addDesignationChecklistItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'designation_rr_item_id' => 'required|integer|exists:designation_rr_items,id',
            'title' => 'required|string|max:500',
            'description' => 'nullable|string|max:2000',
            'weightage' => 'nullable|integer|min:1|max:10',
        ]);

        $itemId = (int) $validated['designation_rr_item_id'];
        $nextOrder = (int) DesignationRrCheckpoint::where('designation_rr_item_id', $itemId)->max('sort_order') + 1;

        $cp = DesignationRrCheckpoint::create([
            'designation_rr_item_id' => $itemId,
            'title' => trim($validated['title']),
            'description' => isset($validated['description']) ? trim($validated['description']) : null,
            'weightage' => (int) ($validated['weightage'] ?? 1),
            'sort_order' => $nextOrder,
            'source' => 'manual',
            'created_by' => optional(Auth::user())->id,
        ]);

        return response()->json([
            'success' => true,
            'checkpoint' => [
                'id' => $cp->id,
                'designation_rr_item_id' => $cp->designation_rr_item_id,
                'title' => $cp->title,
                'description' => $cp->description,
                'weightage' => $cp->weightage,
                'sort_order' => $cp->sort_order,
                'source' => $cp->source,
                'checked' => false,
                'note' => null,
                'checked_at' => null,
            ],
        ]);
    }

    /**
     * Ask AI to suggest a single new CL R&R checkpoint for one R&R item,
     * aware of the checkpoints already attached so it doesn't duplicate
     * existing ones.
     */
    public function suggestDesignationChecklistItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'designation_rr_item_id' => 'required|integer|exists:designation_rr_items,id',
        ]);

        $itemId = (int) $validated['designation_rr_item_id'];
        $item = DesignationRrItem::findOrFail($itemId);

        $existingTitles = DesignationRrCheckpoint::where('designation_rr_item_id', $itemId)
            ->orderBy('sort_order')->orderBy('id')
            ->pluck('title')
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->values()
            ->all();

        $suggestion = $this->suggestOneCheckpointViaOpenAi(
            $item->designation,
            $item->title,
            $item->description,
            $existingTitles
        );

        if (empty($suggestion) || empty(trim((string) ($suggestion['title'] ?? '')))) {
            return response()->json([
                'success' => false,
                'message' => 'AI could not generate a new checkpoint. Try again or add one manually.',
            ], 502);
        }

        $weight = (int) ($suggestion['weightage'] ?? 1);
        $weight = max(1, min(10, $weight));
        $nextOrder = (int) DesignationRrCheckpoint::where('designation_rr_item_id', $itemId)->max('sort_order') + 1;

        $cp = DesignationRrCheckpoint::create([
            'designation_rr_item_id' => $itemId,
            'title' => mb_substr(trim((string) $suggestion['title']), 0, 500),
            'description' => isset($suggestion['description']) ? trim((string) $suggestion['description']) : null,
            'weightage' => $weight,
            'sort_order' => $nextOrder,
            'source' => 'ai',
            'created_by' => optional(Auth::user())->id,
        ]);

        return response()->json([
            'success' => true,
            'checkpoint' => [
                'id' => $cp->id,
                'designation_rr_item_id' => $cp->designation_rr_item_id,
                'title' => $cp->title,
                'description' => $cp->description,
                'weightage' => $cp->weightage,
                'sort_order' => $cp->sort_order,
                'source' => $cp->source,
                'checked' => false,
                'note' => null,
                'checked_at' => null,
            ],
        ]);
    }

    /**
     * Ask OpenAI for ONE additional, non-duplicate checkpoint under a CL R&R item.
     *
     * @param  array<int,string>  $existingTitles
     * @return array{title:string, description?:string|null, weightage?:int}|null
     */
    protected function suggestOneCheckpointViaOpenAi(string $designation, string $rrTitle, ?string $rrDescription, array $existingTitles): ?array
    {
        $existingList = empty($existingTitles)
            ? '(none yet)'
            : implode("\n - ", array_map(fn ($t) => '- ' . $t, $existingTitles));

        $system = 'You build evaluation checklists. Given a job designation, one Roles & Responsibilities '
            . 'item, and the checkpoints already attached to that item, suggest ONE additional checkpoint '
            . 'that is: (a) action-oriented (start with a verb), (b) unambiguous and individually verifiable, '
            . '(c) NOT a duplicate or paraphrase of any existing checkpoint, (d) sized so a manager can tick '
            . 'it off. Assign a weightage 1–10 reflecting relative importance (10 = critical, 1 = nice-to-have). '
            . 'Return ONLY valid JSON: '
            . '{"checkpoint":{"title":"string (<=140 chars)","description":"string (<=200 chars, optional)","weightage":1-10}}';

        $userMsg = "Designation: {$designation}\nR&R item: {$rrTitle}";
        if ($rrDescription) {
            $userMsg .= "\nR&R description: {$rrDescription}";
        }
        $userMsg .= "\nExisting checkpoints:\n{$existingList}\n\nSuggest one additional checkpoint.";

        $ai = $this->callAiJson($system, $userMsg, 60, 0.4);
        if ($ai['text'] === null) {
            \Log::warning('CL R&R suggest: AI call failed', ['error' => $ai['error']]);
            return null;
        }

        $decoded = json_decode($ai['text'], true);
        if (! is_array($decoded)) {
            \Log::warning('CL R&R suggest: AI returned non-JSON', ['text' => $ai['text']]);
            return null;
        }

        // Accept several response shapes: {checkpoint:{...}}, {item:{...}}, or the bare object.
        $row = $decoded['checkpoint'] ?? $decoded['item'] ?? $decoded;
        if (! is_array($row)) {
            return null;
        }

        $title = trim((string) ($row['title'] ?? $row['name'] ?? $row['checkpoint'] ?? ''));
        if ($title === '') {
            return null;
        }
        return [
            'title' => $title,
            'description' => isset($row['description']) ? trim((string) $row['description']) : null,
            'weightage' => isset($row['weightage']) ? (int) $row['weightage'] : 1,
        ];
    }

    /**
     * Delete a CL R&R checkpoint. Cascades user check progress.
     */
    public function deleteDesignationChecklistItem(int $id): JsonResponse
    {
        $cp = DesignationRrCheckpoint::findOrFail($id);
        $cp->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Update the checkpoint's weightage (1–10) from the modal.
     */
    public function updateDesignationChecklistItem(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'weightage' => 'nullable|integer|min:1|max:10',
            'title' => 'nullable|string|max:500',
        ]);

        $cp = DesignationRrCheckpoint::findOrFail($id);
        if (array_key_exists('weightage', $validated) && $validated['weightage'] !== null) {
            $cp->weightage = max(1, min(10, (int) $validated['weightage']));
        }
        if (array_key_exists('title', $validated) && $validated['title'] !== null && trim($validated['title']) !== '') {
            $cp->title = trim($validated['title']);
        }
        $cp->save();

        return response()->json([
            'success' => true,
            'checkpoint' => [
                'id' => $cp->id,
                'weightage' => $cp->weightage,
                'title' => $cp->title,
            ],
        ]);
    }

    /**
     * Upsert a user's check state for one checkpoint.
     */
    public function toggleUserChecklistProgress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'designation_rr_checkpoint_id' => 'required|integer|exists:designation_rr_checkpoints,id',
            'checked' => 'required|boolean',
            'note' => 'nullable|string|max:2000',
        ]);

        $progress = UserRrCheckpointProgress::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'designation_rr_checkpoint_id' => $validated['designation_rr_checkpoint_id'],
            ],
            [
                'checked' => (bool) $validated['checked'],
                'checked_at' => $validated['checked'] ? now() : null,
                'note' => $validated['note'] ?? null,
            ]
        );

        $user = User::find($validated['user_id']);
        if ($user) {
            $this->snapshotUserScore(
                (int) $user->id,
                UserScoreHistory::TYPE_CLRR,
                $this->computeUserClrrPercent($user)
            );
        }

        return response()->json([
            'success' => true,
            'progress' => [
                'id' => $progress->id,
                'checked' => $progress->checked,
                'note' => $progress->note,
                'checked_at' => $progress->checked_at ? $progress->checked_at->toDateTimeString() : null,
            ],
        ]);
    }

    /**
     * Ask OpenAI for 4–8 weighted checkpoints under one R&R item.
     *
     * @return array<int, array{title: string, description?: string|null, weightage?: int}>
     */
    protected function generateChecklistViaOpenAi(string $designation, string $rrTitle, ?string $rrDescription = null): array
    {
        $system = 'You build evaluation checklists. Given a job designation and ONE Roles & Responsibilities '
            . 'item, produce 4 to 8 concrete, observable checkpoints a manager can tick off to confirm the '
            . 'person is meeting the responsibility. Each checkpoint must be action-oriented (start with a verb), '
            . 'unambiguous, and individually verifiable. Assign each a weightage 1–10 reflecting relative '
            . 'importance (10 = critical, 1 = nice-to-have); higher weightage = bigger contribution to the score. '
            . 'Return ONLY valid JSON: '
            . '{"checkpoints":[{"title":"string (<=140 chars)","description":"string (<=200 chars, optional)","weightage":1-10}]}';

        $userMsg = "Designation: {$designation}\nR&R item: {$rrTitle}";
        if ($rrDescription) {
            $userMsg .= "\nR&R description: {$rrDescription}";
        }
        $userMsg .= "\nGenerate the evaluation checklist.";

        $ai = $this->callAiJson($system, $userMsg, 60, 0.3);
        if ($ai['text'] === null) {
            \Log::warning('CL R&R: AI call failed', ['error' => $ai['error']]);
            return [];
        }

        $decoded = json_decode($ai['text'], true);
        if (! is_array($decoded)) {
            \Log::warning('CL R&R: AI returned non-JSON', ['text' => $ai['text']]);
            return [];
        }

        $rows = $decoded['checkpoints'] ?? $decoded;
        if (! is_array($rows)) {
            return [];
        }

        $clean = [];
        foreach ($rows as $row) {
            if (is_string($row)) {
                $clean[] = ['title' => $row, 'weightage' => 1];
                continue;
            }
            if (! is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? $row['name'] ?? $row['checkpoint'] ?? ''));
            if ($title === '') {
                continue;
            }
            $clean[] = [
                'title' => $title,
                'description' => isset($row['description']) ? trim((string) $row['description']) : null,
                'weightage' => isset($row['weightage']) ? (int) $row['weightage'] : 1,
            ];
        }
        return $clean;
    }

    /**
     * Generic checklist starter when AI is unavailable.
     *
     * @return array<int, array{title: string, weightage: int}>
     */
    protected function fallbackChecklistStarterSet(string $rrTitle): array
    {
        return [
            ['title' => 'Document the standard operating procedure for: ' . $rrTitle, 'weightage' => 3],
            ['title' => 'Demonstrate the activity end-to-end with the reporting manager', 'weightage' => 5],
            ['title' => 'Track progress / output in the agreed tracker (sheet / tool)', 'weightage' => 4],
            ['title' => 'Report blockers within 24 hours of identifying them', 'weightage' => 3],
            ['title' => 'Review outcomes weekly and propose at least one improvement', 'weightage' => 2],
        ];
    }

    /**
     * ---------------------------------------------------------------------
     * CL Gen — Global checklist applied to every team member
     * ---------------------------------------------------------------------
     *
     * One shared list (attendance, communication, helpfulness, ETC/ATC,
     * overdues, TAT etc.) seeded by AI on first open. Each user owns their
     * own boolean check state per item; weightages roll up into a single
     * "General score" per user.
     *
     * Score formula
     * -------------
     *  general_score = sum(weightage of checked items)
     *                / sum(weightage of all items) * 100
     */

    /**
     * Return the global checklist + the given user's check state + score.
     */
    public function getGeneralChecklist(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $user = ! empty($validated['user_id']) ? User::find($validated['user_id']) : null;

        $items = GeneralChecklistItem::orderBy('sort_order')->orderBy('id')->get();

        return response()->json(
            $this->buildGeneralChecklistPayload($items, $user)
        );
    }

    /**
     * Build the General checklist payload (used both by GET and after AI seed).
     */
    protected function buildGeneralChecklistPayload($items, ?User $user): array
    {
        $progressByItem = collect();
        if ($user && $items->isNotEmpty()) {
            $progressByItem = UserGeneralChecklistProgress::where('user_id', $user->id)
                ->whereIn('general_checklist_item_id', $items->pluck('id'))
                ->get()
                ->keyBy('general_checklist_item_id');
        }

        $earned = 0;
        $total = 0;
        $checkedCount = 0;
        $rows = $items->map(function (GeneralChecklistItem $item) use ($progressByItem, &$earned, &$total, &$checkedCount) {
            $weight = max(1, (int) $item->weightage);
            $progress = $progressByItem->get($item->id);
            $checked = $progress ? (bool) $progress->checked : false;
            $total += $weight;
            if ($checked) {
                $earned += $weight;
                $checkedCount++;
            }
            return [
                'id' => $item->id,
                'category' => $item->category,
                'title' => $item->title,
                'description' => $item->description,
                'weightage' => $weight,
                'sort_order' => $item->sort_order,
                'source' => $item->source,
                'checked' => $checked,
                'note' => $progress ? $progress->note : null,
                'checked_at' => $progress && $progress->checked_at ? $progress->checked_at->toDateTimeString() : null,
            ];
        })->values();

        return [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'designation' => $user->designation,
            ] : null,
            'items' => $rows,
            'needs_seed' => $items->isEmpty(),
            'score' => [
                'percent' => $total > 0 ? (int) round(($earned / $total) * 100) : 0,
                'earned' => $earned,
                'total' => $total,
                'checked' => $checkedCount,
                'count' => $rows->count(),
            ],
        ];
    }

    /**
     * Ask AI for a starter global checklist applicable to every team member.
     *
     * Body params:
     *  - force (optional bool) — wipe existing checklist and re-seed.
     */
    public function generateGeneralChecklist(Request $request): JsonResponse
    {
        $force = (bool) $request->input('force', false);
        $existing = GeneralChecklistItem::count();
        if ($existing > 0 && ! $force) {
            return response()->json([
                'success' => true,
                'created' => 0,
                'message' => 'General checklist already exists.',
            ]);
        }
        if ($force && $existing > 0) {
            GeneralChecklistItem::query()->delete();
        }

        $generated = $this->generateGeneralChecklistViaOpenAi();
        if (empty($generated)) {
            $generated = $this->fallbackGeneralChecklistStarterSet();
        }

        $createdById = optional(Auth::user())->id;
        $created = 0;
        foreach (array_values($generated) as $idx => $row) {
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $weight = (int) ($row['weightage'] ?? 1);
            $weight = max(1, min(10, $weight));
            GeneralChecklistItem::create([
                'category' => isset($row['category']) ? mb_substr(trim((string) $row['category']), 0, 100) : null,
                'title' => mb_substr($title, 0, 500),
                'description' => isset($row['description']) ? trim((string) $row['description']) : null,
                'weightage' => $weight,
                'sort_order' => $idx + 1,
                'source' => 'ai',
                'created_by' => $createdById,
            ]);
            $created++;
        }

        return response()->json([
            'success' => true,
            'created' => $created,
        ]);
    }

    /**
     * Add a manual checkpoint to the global checklist.
     */
    public function addGeneralChecklistItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:2000',
            'weightage' => 'nullable|integer|min:1|max:10',
        ]);

        $nextOrder = (int) GeneralChecklistItem::max('sort_order') + 1;

        $item = GeneralChecklistItem::create([
            'category' => isset($validated['category']) ? trim($validated['category']) : null,
            'title' => trim($validated['title']),
            'description' => isset($validated['description']) ? trim($validated['description']) : null,
            'weightage' => (int) ($validated['weightage'] ?? 1),
            'sort_order' => $nextOrder,
            'source' => 'manual',
            'created_by' => optional(Auth::user())->id,
        ]);

        return response()->json([
            'success' => true,
            'item' => [
                'id' => $item->id,
                'category' => $item->category,
                'title' => $item->title,
                'description' => $item->description,
                'weightage' => $item->weightage,
                'sort_order' => $item->sort_order,
                'source' => $item->source,
                'checked' => false,
                'note' => null,
                'checked_at' => null,
            ],
        ]);
    }

    /**
     * Update weightage (and/or title/category) of a single general item.
     */
    public function updateGeneralChecklistItem(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:100',
            'weightage' => 'nullable|integer|min:1|max:10',
        ]);

        $item = GeneralChecklistItem::findOrFail($id);
        if (array_key_exists('title', $validated) && $validated['title'] !== null && trim($validated['title']) !== '') {
            $item->title = trim($validated['title']);
        }
        if (array_key_exists('category', $validated)) {
            $item->category = $validated['category'] !== null ? trim($validated['category']) : null;
        }
        if (array_key_exists('weightage', $validated) && $validated['weightage'] !== null) {
            $item->weightage = max(1, min(10, (int) $validated['weightage']));
        }
        $item->save();

        return response()->json([
            'success' => true,
            'item' => [
                'id' => $item->id,
                'category' => $item->category,
                'title' => $item->title,
                'weightage' => $item->weightage,
            ],
        ]);
    }

    /**
     * Delete a general checklist item (cascades user progress).
     */
    public function deleteGeneralChecklistItem(int $id): JsonResponse
    {
        $item = GeneralChecklistItem::findOrFail($id);
        $item->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Upsert a user's check state for one general item.
     */
    public function toggleUserGeneralChecklistProgress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'general_checklist_item_id' => 'required|integer|exists:general_checklist_items,id',
            'checked' => 'required|boolean',
            'note' => 'nullable|string|max:2000',
        ]);

        $progress = UserGeneralChecklistProgress::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'general_checklist_item_id' => $validated['general_checklist_item_id'],
            ],
            [
                'checked' => (bool) $validated['checked'],
                'checked_at' => $validated['checked'] ? now() : null,
                'note' => $validated['note'] ?? null,
            ]
        );

        $user = User::find($validated['user_id']);
        if ($user) {
            $this->snapshotUserScore(
                (int) $user->id,
                UserScoreHistory::TYPE_CLGEN,
                $this->computeUserClGenPercent($user)
            );
        }

        return response()->json([
            'success' => true,
            'progress' => [
                'id' => $progress->id,
                'checked' => $progress->checked,
                'note' => $progress->note,
                'checked_at' => $progress->checked_at ? $progress->checked_at->toDateTimeString() : null,
            ],
        ]);
    }

    /**
     * Ask OpenAI for a starter global checklist applicable to every team member.
     *
     * @return array<int, array{title: string, description?: string|null, weightage?: int, category?: string|null}>
     */
    protected function generateGeneralChecklistViaOpenAi(): array
    {
        $system = 'You are a performance / HR expert. Produce a single comprehensive evaluation checklist '
            . 'that applies to EVERY team member regardless of designation. Cover at minimum: Attendance & '
            . 'Punctuality, Communication, Helpfulness to colleagues/seniors/juniors, ETC vs ATC accuracy '
            . '(estimated vs actual time), Overdues average, TAT (turn-around time) average, ownership, '
            . 'documentation, learning / training, escalation discipline. 12 to 18 items total. '
            . 'Each item: action-oriented, individually verifiable (a manager should be able to mark it '
            . 'Done or Not yet). Assign each a weightage 1–10 (10 = most critical) and a short category '
            . '(e.g. "Attendance", "Communication", "Productivity", "Quality", "Teamwork", "Learning"). '
            . 'Return ONLY valid JSON: '
            . '{"items":[{"category":"string","title":"string (<=140 chars)","description":"string (<=200 chars, optional)","weightage":1-10}]}';

        $userMsg = 'Generate the global team-wide evaluation checklist now.';

        $ai = $this->callAiJson($system, $userMsg, 90, 0.3);
        if ($ai['text'] === null) {
            \Log::warning('CL Gen: AI call failed', ['error' => $ai['error']]);
            return [];
        }

        $decoded = json_decode($ai['text'], true);
        if (! is_array($decoded)) {
            \Log::warning('CL Gen: AI returned non-JSON', ['text' => $ai['text']]);
            return [];
        }

        $rows = $decoded['items'] ?? $decoded;
        if (! is_array($rows)) {
            return [];
        }

        $clean = [];
        foreach ($rows as $row) {
            if (is_string($row)) {
                $clean[] = ['title' => $row, 'weightage' => 1, 'category' => null];
                continue;
            }
            if (! is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? $row['name'] ?? ''));
            if ($title === '') {
                continue;
            }
            $clean[] = [
                'title' => $title,
                'category' => isset($row['category']) ? trim((string) $row['category']) : null,
                'description' => isset($row['description']) ? trim((string) $row['description']) : null,
                'weightage' => isset($row['weightage']) ? (int) $row['weightage'] : 1,
            ];
        }
        return $clean;
    }

    /**
     * Generic team-wide checklist starter when AI is unavailable.
     *
     * @return array<int, array{title: string, weightage: int, category: string}>
     */
    protected function fallbackGeneralChecklistStarterSet(): array
    {
        return [
            ['category' => 'Attendance', 'title' => 'On-time arrival ≥ 95% in last 30 days', 'weightage' => 8],
            ['category' => 'Attendance', 'title' => 'No unplanned absences in last 30 days', 'weightage' => 6],
            ['category' => 'Communication', 'title' => 'Responds to messages within 2 working hours', 'weightage' => 7],
            ['category' => 'Communication', 'title' => 'Status updates posted daily on tasks in progress', 'weightage' => 6],
            ['category' => 'Teamwork', 'title' => 'Helps colleagues / juniors at least twice a week', 'weightage' => 6],
            ['category' => 'Teamwork', 'title' => 'Supports seniors when extra workload arises', 'weightage' => 5],
            ['category' => 'Productivity', 'title' => 'ETC vs ATC variance within ±20% on most tasks', 'weightage' => 8],
            ['category' => 'Productivity', 'title' => 'Overdue tasks count < 5 at any time', 'weightage' => 9],
            ['category' => 'Productivity', 'title' => 'Average TAT within team target', 'weightage' => 8],
            ['category' => 'Quality', 'title' => 'Rework rate < 10% in last 30 days', 'weightage' => 6],
            ['category' => 'Quality', 'title' => 'Documentation / SOP updates owned for each completed task', 'weightage' => 4],
            ['category' => 'Learning', 'title' => 'Completes assigned training within deadline', 'weightage' => 5],
            ['category' => 'Discipline', 'title' => 'Escalates blockers within 24 hours of identifying them', 'weightage' => 7],
            ['category' => 'Discipline', 'title' => 'Follows the agreed daily / weekly reporting cadence', 'weightage' => 6],
        ];
    }

    /**
     * ---------------------------------------------------------------------
     * CL Mgr — Senior / Manager checklist, per-designation, with juniors
     * ---------------------------------------------------------------------
     *
     * Per-designation weighted checkpoints aimed at leadership duties
     * (training, auditing, monitoring, assigning, follow-ups, on-time
     * delivery, etc). Each manager owns a list of juniors they oversee;
     * the manager's final score blends their own checkpoint score with
     * the average of their juniors' overall scores.
     *
     * Combined Mgr score formula
     * --------------------------
     *  own_score      = sum(weightage of checked CL Mgr items)
     *                 / sum(weightage of all CL Mgr items) * 100
     *
     *  junior_score   = average over juniors of:
     *                     (CL R&R overall % + CL Gen %) / 2
     *
     *  combined_score = own_score * OWN_WEIGHT + junior_score * JUNIORS_WEIGHT
     *                   (OWN_WEIGHT + JUNIORS_WEIGHT = 1.0)
     *
     * Defaults: 60% own / 40% juniors. With no juniors the formula falls
     * back to 100% own_score so single-contributor managers aren't
     * penalised for not having a team.
     */
    public const CL_MGR_OWN_WEIGHT = 0.60;
    public const CL_MGR_JUNIORS_WEIGHT = 0.40;

    /**
     * Return CL Mgr checkpoints + the user's check state + their juniors
     * (with each junior's blended team-score) + combined Mgr score.
     */
    public function getDesignationMgrChecklist(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'designation' => 'nullable|string|max:191',
        ]);

        $user = ! empty($validated['user_id']) ? User::find($validated['user_id']) : null;
        $designation = trim((string) ($validated['designation'] ?? ($user->designation ?? '')));

        if ($designation === '') {
            return response()->json([
                'designation' => '',
                'user' => $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'designation' => null] : null,
                'items' => [],
                'needs_seed' => true,
                'juniors' => [],
                'eligible_juniors' => [],
                'own_score' => ['percent' => 0, 'earned' => 0, 'total' => 0],
                'juniors_score' => ['percent' => 0, 'count' => 0],
                'combined_score' => ['percent' => 0, 'own_weight' => self::CL_MGR_OWN_WEIGHT, 'juniors_weight' => self::CL_MGR_JUNIORS_WEIGHT],
                'message' => 'No designation set for this user. Set a designation on the user record first.',
            ]);
        }

        $items = DesignationMgrCheckpoint::where('designation', $designation)
            ->orderBy('sort_order')->orderBy('id')->get();

        return response()->json(
            $this->buildMgrChecklistPayload($designation, $items, $user)
        );
    }

    /**
     * Build the CL Mgr response payload — used by both the GET endpoint
     * and after AI generation so the modal can hot-swap state.
     */
    protected function buildMgrChecklistPayload(string $designation, $items, ?User $user): array
    {
        // ---------------------- Own (manager) checkpoints -----------------------
        $progressByCheckpoint = collect();
        if ($user && $items->isNotEmpty()) {
            $progressByCheckpoint = UserMgrCheckpointProgress::where('user_id', $user->id)
                ->whereIn('designation_mgr_checkpoint_id', $items->pluck('id'))
                ->get()
                ->keyBy('designation_mgr_checkpoint_id');
        }

        $ownEarned = 0;
        $ownTotal = 0;
        $ownChecked = 0;
        $rows = $items->map(function (DesignationMgrCheckpoint $cp) use ($progressByCheckpoint, &$ownEarned, &$ownTotal, &$ownChecked) {
            $weight = max(1, (int) $cp->weightage);
            $progress = $progressByCheckpoint->get($cp->id);
            $checked = $progress ? (bool) $progress->checked : false;
            $ownTotal += $weight;
            if ($checked) {
                $ownEarned += $weight;
                $ownChecked++;
            }
            return [
                'id' => $cp->id,
                'category' => $cp->category,
                'title' => $cp->title,
                'description' => $cp->description,
                'weightage' => $weight,
                'sort_order' => $cp->sort_order,
                'source' => $cp->source,
                'checked' => $checked,
                'note' => $progress ? $progress->note : null,
                'checked_at' => $progress && $progress->checked_at ? $progress->checked_at->toDateTimeString() : null,
            ];
        })->values();

        $ownPercent = $ownTotal > 0 ? (int) round(($ownEarned / $ownTotal) * 100) : 0;

        // ------------------------ Juniors + their scores ------------------------
        $juniorsPayload = [];
        $juniorsAvgPercent = 0;
        $juniorIds = [];
        if ($user) {
            $juniorIds = ManagerJunior::where('manager_user_id', $user->id)
                ->pluck('junior_user_id')
                ->all();
        }

        if (! empty($juniorIds)) {
            $juniors = User::whereIn('id', $juniorIds)->get(['id', 'name', 'email', 'designation', 'avatar']);
            $sum = 0;
            $cnt = 0;
            foreach ($juniors as $j) {
                $clrr = $this->computeUserClrrPercent($j);
                $clgen = $this->computeUserClGenPercent($j);
                $blend = (int) round(($clrr + $clgen) / 2);
                $sum += $blend;
                $cnt++;
                $juniorsPayload[] = [
                    'id' => $j->id,
                    'name' => $j->name,
                    'email' => $j->email,
                    'designation' => $j->designation,
                    'avatar' => $j->avatar,
                    'clrr_percent' => $clrr,
                    'clgen_percent' => $clgen,
                    'blend_percent' => $blend,
                ];
            }
            $juniorsAvgPercent = $cnt > 0 ? (int) round($sum / $cnt) : 0;
        }

        // -------------------- Pool of eligible juniors (UI) --------------------
        $eligible = [];
        if ($user) {
            $eligible = User::query()
                ->where('is_active', true)
                ->whereNull('deactivated_at')
                ->where('id', '!=', $user->id)
                ->whereNotIn('id', $juniorIds)
                ->orderBy('name')
                ->limit(500)
                ->get(['id', 'name', 'email', 'designation'])
                ->map(function (User $u) {
                    return [
                        'id' => $u->id,
                        'name' => $u->name,
                        'email' => $u->email,
                        'designation' => $u->designation,
                    ];
                })
                ->values()
                ->all();
        }

        // ----------------------------- Combined ---------------------------------
        $combined = $ownPercent;
        $juniorsWeight = self::CL_MGR_JUNIORS_WEIGHT;
        $ownWeight = self::CL_MGR_OWN_WEIGHT;
        if (! empty($juniorsPayload)) {
            $combined = (int) round(($ownPercent * $ownWeight) + ($juniorsAvgPercent * $juniorsWeight));
        }

        return [
            'designation' => $designation,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'designation' => $user->designation,
            ] : null,
            'items' => $rows,
            'needs_seed' => $items->isEmpty(),
            'juniors' => $juniorsPayload,
            'eligible_juniors' => $eligible,
            'own_score' => [
                'percent' => $ownPercent,
                'earned' => $ownEarned,
                'total' => $ownTotal,
                'checked' => $ownChecked,
                'count' => $rows->count(),
            ],
            'juniors_score' => [
                'percent' => $juniorsAvgPercent,
                'count' => count($juniorsPayload),
            ],
            'combined_score' => [
                'percent' => $combined,
                'own_weight' => $ownWeight,
                'juniors_weight' => $juniorsWeight,
            ],
        ];
    }

    /**
     * Compute a single user's CL R&R overall percent across all their
     * designation's R&R items' checkpoints. Mirrors the formula in
     * {@see buildRRChecklistPayload()} but trimmed to just a percent.
     */
    protected function computeUserClrrPercent(User $user): int
    {
        $designation = trim((string) ($user->designation ?? ''));
        if ($designation === '') {
            return 0;
        }
        $checkpointIds = DesignationRrCheckpoint::whereIn(
            'designation_rr_item_id',
            DesignationRrItem::where('designation', $designation)->pluck('id')
        )->pluck('id', 'id');
        if ($checkpointIds->isEmpty()) {
            return 0;
        }
        $weights = DesignationRrCheckpoint::whereIn('id', $checkpointIds)->pluck('weightage', 'id');
        $progress = UserRrCheckpointProgress::where('user_id', $user->id)
            ->whereIn('designation_rr_checkpoint_id', $checkpointIds)
            ->get(['designation_rr_checkpoint_id', 'checked'])
            ->keyBy('designation_rr_checkpoint_id');

        $earned = 0; $total = 0;
        foreach ($weights as $id => $w) {
            $w = max(1, (int) $w);
            $total += $w;
            $p = $progress->get($id);
            if ($p && $p->checked) {
                $earned += $w;
            }
        }
        return $total > 0 ? (int) round(($earned / $total) * 100) : 0;
    }

    /**
     * Compute a single user's CL Gen (global) percent.
     */
    protected function computeUserClGenPercent(User $user): int
    {
        $weights = GeneralChecklistItem::pluck('weightage', 'id');
        if ($weights->isEmpty()) {
            return 0;
        }
        $progress = UserGeneralChecklistProgress::where('user_id', $user->id)
            ->whereIn('general_checklist_item_id', $weights->keys())
            ->get(['general_checklist_item_id', 'checked'])
            ->keyBy('general_checklist_item_id');

        $earned = 0; $total = 0;
        foreach ($weights as $id => $w) {
            $w = max(1, (int) $w);
            $total += $w;
            $p = $progress->get($id);
            if ($p && $p->checked) {
                $earned += $w;
            }
        }
        return $total > 0 ? (int) round(($earned / $total) * 100) : 0;
    }

    /**
     * Ask AI for the CL Mgr checklist for a designation.
     * Body params: designation (req), force (optional bool — wipe existing first).
     */
    public function generateDesignationMgrChecklist(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'designation' => 'required|string|max:191',
            'force' => 'nullable|boolean',
        ]);

        $designation = trim($validated['designation']);
        $force = (bool) ($validated['force'] ?? false);

        $existing = DesignationMgrCheckpoint::where('designation', $designation)->count();
        if ($existing > 0 && ! $force) {
            return response()->json([
                'success' => true,
                'created' => 0,
                'message' => 'CL Mgr already exists for this designation.',
            ]);
        }
        if ($force && $existing > 0) {
            DesignationMgrCheckpoint::where('designation', $designation)->delete();
        }

        $generated = $this->generateMgrChecklistViaOpenAi($designation);
        if (empty($generated)) {
            $generated = $this->fallbackMgrChecklistStarterSet();
        }

        $createdById = optional(Auth::user())->id;
        $created = 0;
        foreach (array_values($generated) as $idx => $row) {
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $weight = (int) ($row['weightage'] ?? 1);
            $weight = max(1, min(10, $weight));
            DesignationMgrCheckpoint::create([
                'designation' => $designation,
                'category' => isset($row['category']) ? mb_substr(trim((string) $row['category']), 0, 100) : null,
                'title' => mb_substr($title, 0, 500),
                'description' => isset($row['description']) ? trim((string) $row['description']) : null,
                'weightage' => $weight,
                'sort_order' => $idx + 1,
                'source' => 'ai',
                'created_by' => $createdById,
            ]);
            $created++;
        }

        return response()->json([
            'success' => true,
            'created' => $created,
            'designation' => $designation,
        ]);
    }

    /** Add a manual CL Mgr checkpoint to a designation. */
    public function addDesignationMgrCheckpoint(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'designation' => 'required|string|max:191',
            'title' => 'required|string|max:500',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:2000',
            'weightage' => 'nullable|integer|min:1|max:10',
        ]);

        $designation = trim($validated['designation']);
        $nextOrder = (int) DesignationMgrCheckpoint::where('designation', $designation)->max('sort_order') + 1;

        $cp = DesignationMgrCheckpoint::create([
            'designation' => $designation,
            'category' => isset($validated['category']) ? trim($validated['category']) : null,
            'title' => trim($validated['title']),
            'description' => isset($validated['description']) ? trim($validated['description']) : null,
            'weightage' => (int) ($validated['weightage'] ?? 1),
            'sort_order' => $nextOrder,
            'source' => 'manual',
            'created_by' => optional(Auth::user())->id,
        ]);

        return response()->json([
            'success' => true,
            'item' => [
                'id' => $cp->id,
                'category' => $cp->category,
                'title' => $cp->title,
                'description' => $cp->description,
                'weightage' => $cp->weightage,
                'sort_order' => $cp->sort_order,
                'source' => $cp->source,
                'checked' => false,
                'note' => null,
                'checked_at' => null,
            ],
        ]);
    }

    /** Update CL Mgr checkpoint (title / category / weightage). */
    public function updateDesignationMgrCheckpoint(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:100',
            'weightage' => 'nullable|integer|min:1|max:10',
        ]);

        $cp = DesignationMgrCheckpoint::findOrFail($id);
        if (array_key_exists('title', $validated) && $validated['title'] !== null && trim($validated['title']) !== '') {
            $cp->title = trim($validated['title']);
        }
        if (array_key_exists('category', $validated)) {
            $cp->category = $validated['category'] !== null ? trim($validated['category']) : null;
        }
        if (array_key_exists('weightage', $validated) && $validated['weightage'] !== null) {
            $cp->weightage = max(1, min(10, (int) $validated['weightage']));
        }
        $cp->save();

        return response()->json([
            'success' => true,
            'item' => [
                'id' => $cp->id,
                'category' => $cp->category,
                'title' => $cp->title,
                'weightage' => $cp->weightage,
            ],
        ]);
    }

    /** Delete a CL Mgr checkpoint (cascades user progress). */
    public function deleteDesignationMgrCheckpoint(int $id): JsonResponse
    {
        $cp = DesignationMgrCheckpoint::findOrFail($id);
        $cp->delete();

        return response()->json(['success' => true]);
    }

    /** Upsert a manager's check state for one CL Mgr checkpoint. */
    public function toggleUserMgrProgress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'designation_mgr_checkpoint_id' => 'required|integer|exists:designation_mgr_checkpoints,id',
            'checked' => 'required|boolean',
            'note' => 'nullable|string|max:2000',
        ]);

        $progress = UserMgrCheckpointProgress::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'designation_mgr_checkpoint_id' => $validated['designation_mgr_checkpoint_id'],
            ],
            [
                'checked' => (bool) $validated['checked'],
                'checked_at' => $validated['checked'] ? now() : null,
                'note' => $validated['note'] ?? null,
            ]
        );

        $user = User::find($validated['user_id']);
        if ($user) {
            $designation = (string) ($user->designation ?? '');
            $mgrCheckpoints = $designation === ''
                ? collect()
                : DesignationMgrCheckpoint::where('designation', $designation)
                    ->orderBy('sort_order')->orderBy('id')->get();
            $payload = $this->buildMgrChecklistPayload($designation, $mgrCheckpoints, $user);
            $this->snapshotUserScore(
                (int) $user->id,
                UserScoreHistory::TYPE_CLMGR,
                (int) ($payload['combined_score']['percent'] ?? 0)
            );
        }

        return response()->json([
            'success' => true,
            'progress' => [
                'id' => $progress->id,
                'checked' => $progress->checked,
                'note' => $progress->note,
                'checked_at' => $progress->checked_at ? $progress->checked_at->toDateTimeString() : null,
            ],
        ]);
    }

    /** Add a junior under a manager (idempotent). */
    public function addManagerJunior(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'manager_user_id' => 'required|integer|exists:users,id',
            'junior_user_id' => 'required|integer|exists:users,id|different:manager_user_id',
        ]);

        ManagerJunior::firstOrCreate([
            'manager_user_id' => $validated['manager_user_id'],
            'junior_user_id' => $validated['junior_user_id'],
        ]);

        $j = User::find($validated['junior_user_id']);
        $clrr = $this->computeUserClrrPercent($j);
        $clgen = $this->computeUserClGenPercent($j);

        return response()->json([
            'success' => true,
            'junior' => [
                'id' => $j->id,
                'name' => $j->name,
                'email' => $j->email,
                'designation' => $j->designation,
                'avatar' => $j->avatar,
                'clrr_percent' => $clrr,
                'clgen_percent' => $clgen,
                'blend_percent' => (int) round(($clrr + $clgen) / 2),
            ],
        ]);
    }

    /** Remove a junior under a manager. */
    public function removeManagerJunior(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'manager_user_id' => 'required|integer|exists:users,id',
            'junior_user_id' => 'required|integer|exists:users,id',
        ]);

        ManagerJunior::where('manager_user_id', $validated['manager_user_id'])
            ->where('junior_user_id', $validated['junior_user_id'])
            ->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Ask OpenAI for the CL Mgr checklist for a single manager designation.
     *
     * @return array<int, array{title: string, description?: string|null, weightage?: int, category?: string|null}>
     */
    protected function generateMgrChecklistViaOpenAi(string $designation): array
    {
        $system = 'You are an HR / operations expert. Given a MANAGER / SENIOR designation, produce a '
            . 'practical leadership checklist a reporting manager can tick off to confirm the senior is '
            . 'doing their leadership duties. Cover at minimum: training & onboarding juniors, auditing '
            . 'their work, monitoring throughput, assigning tasks fairly, ensuring on-time delivery, '
            . 'following up on overdue tasks, mentoring / 1:1s, performance reviews, escalation handling, '
            . 'process improvement. 10 to 16 items. Each item: action-oriented (start with a verb), '
            . 'verifiable. Assign each a weightage 1–10 (10 = most critical) and a short category '
            . '(Training, Auditing, Delivery, Mentoring, Process, Reviews, etc). Return ONLY valid JSON: '
            . '{"items":[{"category":"string","title":"string (<=140 chars)","description":"string (<=200 chars, optional)","weightage":1-10}]}';

        $userMsg = "Designation: {$designation}\nGenerate the manager-level checklist for this designation.";

        $ai = $this->callAiJson($system, $userMsg, 90, 0.3);
        if ($ai['text'] === null) {
            \Log::warning('CL Mgr: AI call failed', ['error' => $ai['error']]);
            return [];
        }

        $decoded = json_decode($ai['text'], true);
        if (! is_array($decoded)) {
            \Log::warning('CL Mgr: AI returned non-JSON', ['text' => $ai['text']]);
            return [];
        }

        $rows = $decoded['items'] ?? $decoded;
        if (! is_array($rows)) {
            return [];
        }

        $clean = [];
        foreach ($rows as $row) {
            if (is_string($row)) {
                $clean[] = ['title' => $row, 'weightage' => 1, 'category' => null];
                continue;
            }
            if (! is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? $row['name'] ?? ''));
            if ($title === '') {
                continue;
            }
            $clean[] = [
                'title' => $title,
                'category' => isset($row['category']) ? trim((string) $row['category']) : null,
                'description' => isset($row['description']) ? trim((string) $row['description']) : null,
                'weightage' => isset($row['weightage']) ? (int) $row['weightage'] : 1,
            ];
        }
        return $clean;
    }

    /**
     * Generic CL Mgr starter when AI is unavailable.
     *
     * @return array<int, array{title: string, weightage: int, category: string}>
     */
    /**
     * Return the lifetime score history for a (user, score_type) pair —
     * powers the small line chart opened from the history dot next to
     * each CL column score chip.
     *
     * If the table has fewer than 2 points we still include the current
     * computed value as a "today" point so the chart isn't blank.
     */
    public function getUserScoreHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'score_type' => ['required', Rule::in(UserScoreHistory::TYPES)],
        ]);

        // Apply the same visibility rules as the rest of Task Summary so a
        // manager can't peek at a director's chart by hitting this URL.
        $viewer = Auth::user();
        $visible = $this->getTaskSummaryVisibleUserIds($viewer);
        if ($visible !== null && ! in_array((int) $validated['user_id'], $visible, true)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this user.',
            ], 403);
        }

        $user = User::find($validated['user_id']);
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }

        $rows = UserScoreHistory::query()
            ->where('user_id', $user->id)
            ->where('score_type', $validated['score_type'])
            ->orderBy('captured_at')
            ->limit(500)
            ->get(['percent', 'captured_at']);

        $points = $rows->map(function (UserScoreHistory $r) {
            return [
                't' => $r->captured_at ? $r->captured_at->toIso8601String() : null,
                'p' => (int) $r->percent,
            ];
        })->values();

        // Append a "now" point computed live so the chart always shows the
        // current value, even when history is empty.
        $current = 0;
        switch ($validated['score_type']) {
            case UserScoreHistory::TYPE_CLRR:
                $current = $this->computeUserClrrPercent($user);
                break;
            case UserScoreHistory::TYPE_CLGEN:
                $current = $this->computeUserClGenPercent($user);
                break;
            case UserScoreHistory::TYPE_CLMGR:
                $designation = (string) ($user->designation ?? '');
                $cps = $designation === ''
                    ? collect()
                    : DesignationMgrCheckpoint::where('designation', $designation)
                        ->orderBy('sort_order')->orderBy('id')->get();
                $payload = $this->buildMgrChecklistPayload($designation, $cps, $user);
                $current = (int) ($payload['combined_score']['percent'] ?? 0);
                break;
        }
        $points->push(['t' => now()->toIso8601String(), 'p' => $current]);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'designation' => $user->designation,
            ],
            'score_type' => $validated['score_type'],
            'current' => $current,
            'points' => $points,
        ]);
    }

    /**
     * Return a per-user "dashboard" payload for the magnifying-glass button
     * on the KPI column. Includes the user's own metrics + their scores
     * (R&R / CL R&R / CL Mgr / CL Gen) + every junior tagged under them
     * with the same metrics, so the modal can show what the user
     * effectively "owns" based on their tags.
     */
    public function getUserDashboard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $viewer = Auth::user();
        $visible = $this->getTaskSummaryVisibleUserIds($viewer);
        if ($visible !== null && ! in_array((int) $validated['user_id'], $visible, true)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this user.',
            ], 403);
        }

        $user = User::find($validated['user_id']);
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }

        $allRows = $this->getTaskSummaryMemberRows();
        $rowsById = [];
        foreach ($allRows as $r) {
            $rowsById[(int) ($r['user_id'] ?? 0)] = $r;
        }

        $juniorIds = ManagerJunior::where('manager_user_id', $user->id)
            ->pluck('junior_user_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $juniorUsers = empty($juniorIds)
            ? collect()
            : User::whereIn('id', $juniorIds)->orderBy('name')->get();

        // Reverse lookup: who manages this user (people who tagged them as a junior).
        $managerIds = ManagerJunior::where('junior_user_id', $user->id)
            ->pluck('manager_user_id')
            ->map(fn ($v) => (int) $v)
            ->all();
        $managerUsers = empty($managerIds)
            ? collect()
            : User::whereIn('id', $managerIds)->orderBy('name')->get();

        // Build the manager's own Mgr-combined score (own × 60% + juniors-avg × 40%).
        $mgrPayload = $this->buildMgrChecklistPayload(
            (string) ($user->designation ?? ''),
            DesignationMgrCheckpoint::where('designation', $user->designation)
                ->orderBy('sort_order')->orderBy('id')->get(),
            $user
        );

        // Profile-only extras (used by the Team Member Profile modal). Wrapped
        // in try/catch so a missing column on older deployments can't break
        // the response.
        $profile = [];
        try {
            $profile = [
                'phone' => $user->phone ?? null,
                'role' => $user->role ?? null, // system role (admin / user)
                'date_of_joining' => $user->date_of_joining ? $user->date_of_joining->toDateString() : null,
                'tenure_label' => $user->date_of_joining
                    ? \Carbon\Carbon::parse($user->date_of_joining)->diffForHumans(now(), ['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE])
                    : null,
                'resource_department' => optional($user->resourceDepartment ?? null)->name ?? null,
            ];
        } catch (\Throwable $e) {
            $profile = [
                'phone' => null,
                'role' => null,
                'date_of_joining' => null,
                'tenure_label' => null,
                'resource_department' => null,
            ];
        }

        return response()->json([
            'success' => true,
            'user' => $this->buildUserDashboardSnapshot($user, $rowsById),
            'profile' => $profile,
            'managers' => $managerUsers->map(function (User $m) use ($rowsById) {
                return $this->buildUserDashboardSnapshot($m, $rowsById);
            })->values(),
            'juniors' => $juniorUsers->map(function (User $j) use ($rowsById) {
                return $this->buildUserDashboardSnapshot($j, $rowsById);
            })->values(),
            'mgr' => [
                'own_percent' => $mgrPayload['own_score']['percent'] ?? 0,
                'juniors_avg_percent' => $mgrPayload['juniors_score']['percent'] ?? 0,
                'combined_percent' => $mgrPayload['combined_score']['percent'] ?? 0,
                'has_mgr_checklist' => empty($mgrPayload['needs_seed']),
            ],
        ]);
    }

    /**
     * Compose a per-user snapshot (used for the user being viewed and each
     * of their tagged juniors in the dashboard modal).
     */
    protected function buildUserDashboardSnapshot(User $user, array $rowsById): array
    {
        $row = $rowsById[$user->id] ?? null;

        $clrr = $this->computeUserClrrPercent($user);
        $clgen = $this->computeUserClGenPercent($user);

        // Per-user R&R progress percent (parent items, not checkpoints — gives
        // a quick "R&R progress" feel separate from the deeper CL R&R).
        $rrPercent = 0;
        $designation = trim((string) ($user->designation ?? ''));
        if ($designation !== '') {
            $rrItems = DesignationRrItem::where('designation', $designation)->pluck('id');
            if ($rrItems->isNotEmpty()) {
                $progressRows = UserRrProgress::where('user_id', $user->id)
                    ->whereIn('designation_rr_item_id', $rrItems)
                    ->get(['status']);
                $total = $rrItems->count();
                $done = $progressRows->filter(fn ($p) => $p->status === UserRrProgress::STATUS_DONE)->count();
                $rrPercent = $total > 0 ? (int) round(($done / $total) * 100) : 0;
            }
        }

        $avatar = $user->avatar
            ? asset('storage/' . $user->avatar)
            : asset('images/users/add-image-placeholder.svg');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'designation' => $user->designation,
            'org_level' => $user->org_level,
            'avatar' => $avatar,
            'metrics' => $row ? [
                'task' => (int) ($row['task'] ?? 0),
                'l30_hrs' => (float) ($row['l30_hrs'] ?? 0),
                'assignor_task' => (int) ($row['assignor_task'] ?? 0),
                'done' => (int) ($row['done'] ?? 0),
                'overdue' => (int) ($row['overdue'] ?? 0),
                'tat_l30_days' => $row['tat_l30_days'] ?? null,
                'tat_l30_count' => (int) ($row['tat_l30_count'] ?? 0),
                'missed_l30' => (int) ($row['missed_l30'] ?? 0),
                'a_task' => (int) ($row['a_task'] ?? 0),
                'a_task_h' => (int) ($row['a_task_h'] ?? 0),
                'need_approval' => (int) ($row['need_approval'] ?? 0),
            ] : [
                'task' => 0, 'l30_hrs' => 0, 'assignor_task' => 0,
                'done' => 0, 'overdue' => 0, 'tat_l30_days' => null, 'tat_l30_count' => 0,
                'missed_l30' => 0, 'a_task' => 0, 'a_task_h' => 0, 'need_approval' => 0,
            ],
            'scores' => [
                'rr_percent' => $rrPercent,
                'clrr_percent' => $clrr,
                'clgen_percent' => $clgen,
                // Quick "blend" used in the CL Mgr roll-up.
                'blend_percent' => (int) round(($clrr + $clgen) / 2),
            ],
        ];
    }

    /**
     * Update the user's organisational level (Task Summary "Role" column).
     *
     * Allowed values: 'mgr' | 'director' | 'exec' | null (cleared).
     *
     * Permission rules:
     *  - Admin / Director / Shobha → can edit anyone, any value.
     *  - Manager (org_level=mgr)   → can edit only users whose current role
     *                                is Exec or empty, and can only set the
     *                                new value to Exec or empty.
     *  - Executive / no role       → cannot edit (returns 403).
     */
    public function updateUserOrgLevel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'org_level' => ['nullable', 'string', Rule::in(['mgr', 'director', 'exec'])],
        ]);

        $viewer = Auth::user();
        $user = User::findOrFail($validated['user_id']);
        $newLevel = $validated['org_level'] ?? null;

        if (! $this->canChangeOrgLevelOf($viewer, $user, $newLevel)) {
            return response()->json([
                'success' => false,
                'message' => $this->orgLevelDenialReason($viewer, $user, $newLevel),
            ], 403);
        }

        $user->org_level = $newLevel;
        $user->save();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'org_level' => $user->org_level,
            ],
        ]);
    }

    /**
     * Can $viewer set $target's org_level to $newLevel? See updateUserOrgLevel
     * for the permission matrix.
     */
    protected function canChangeOrgLevelOf(?User $viewer, User $target, ?string $newLevel): bool
    {
        if (! $viewer) {
            return false;
        }

        // Anyone in the "edit any tag" set (admin / director / shobha) can
        // also reassign org_level on any user, to any value.
        if ($this->canEditOrgTags($viewer)) {
            return true;
        }

        $viewerLevel = strtolower((string) ($viewer->org_level ?? ''));
        if ($viewerLevel === 'mgr') {
            $targetLevel = strtolower((string) ($target->org_level ?? ''));
            $newLevelL = strtolower((string) ($newLevel ?? ''));
            $targetIsExecOrNone = $targetLevel === 'exec' || $targetLevel === '';
            $newIsExecOrNone = $newLevelL === 'exec' || $newLevelL === '';
            return $targetIsExecOrNone && $newIsExecOrNone;
        }

        // Exec / no role → not allowed.
        return false;
    }

    /** Human-friendly reason returned to the UI when a role change is denied. */
    protected function orgLevelDenialReason(?User $viewer, User $target, ?string $newLevel): string
    {
        if (! $viewer) {
            return 'Sign in to change roles.';
        }
        $viewerLevel = strtolower((string) ($viewer->org_level ?? ''));
        if ($viewerLevel === 'mgr') {
            $targetLevel = strtolower((string) ($target->org_level ?? ''));
            $newLevelL = strtolower((string) ($newLevel ?? ''));
            if (! ($targetLevel === 'exec' || $targetLevel === '')) {
                return 'Managers can only change Executives. Ask a Director to change ' . ($target->name ?? 'this user') . '.';
            }
            if (! ($newLevelL === 'exec' || $newLevelL === '')) {
                return 'Managers can only assign the Executive level. Ask a Director to promote ' . ($target->name ?? 'this user') . '.';
            }
        }
        return 'You are not allowed to change this user\'s role.';
    }

    /**
     * Lightweight juniors-tags payload for the Mgr tags modal (Task Summary
     * "Role" column dot). Returns the current juniors for the manager plus
     * an alphabetic list of every other active user as the "add tag"
     * dropdown source. Shares the manager_juniors pivot with CL Mgr.
     */
    public function getManagerJuniorsForTags(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $manager = User::findOrFail($validated['user_id']);

        $juniorIds = ManagerJunior::where('manager_user_id', $manager->id)
            ->pluck('junior_user_id')
            ->all();

        $juniors = empty($juniorIds)
            ? collect()
            : User::whereIn('id', $juniorIds)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'designation', 'avatar']);

        $eligible = User::query()
            ->where('is_active', true)
            ->whereNull('deactivated_at')
            ->where('id', '!=', $manager->id)
            ->whereNotIn('id', $juniorIds)
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'email', 'designation']);

        return response()->json([
            'success' => true,
            'manager' => [
                'id' => $manager->id,
                'name' => $manager->name,
                'email' => $manager->email,
                'designation' => $manager->designation,
                'org_level' => $manager->org_level,
            ],
            'juniors' => $juniors->map(function (User $u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'designation' => $u->designation,
                    'avatar' => $u->avatar,
                ];
            })->values(),
            'eligible' => $eligible->map(function (User $u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'designation' => $u->designation,
                ];
            })->values(),
        ]);
    }

    protected function fallbackMgrChecklistStarterSet(): array
    {
        return [
            ['category' => 'Training', 'title' => 'Onboards new juniors within the first 2 weeks', 'weightage' => 7],
            ['category' => 'Training', 'title' => 'Schedules at least 1 training / knowledge-share per month for the team', 'weightage' => 5],
            ['category' => 'Auditing', 'title' => 'Audits a random sample of juniors\' work weekly', 'weightage' => 7],
            ['category' => 'Auditing', 'title' => 'Reviews rework / defects with the responsible junior within 48 hours', 'weightage' => 6],
            ['category' => 'Monitoring', 'title' => 'Reviews team task board daily and re-prioritises if needed', 'weightage' => 8],
            ['category' => 'Delivery', 'title' => 'Tracks team\'s overdue count and clears it weekly', 'weightage' => 9],
            ['category' => 'Delivery', 'title' => 'Ensures team TAT stays within the agreed target', 'weightage' => 8],
            ['category' => 'Assigning', 'title' => 'Assigns tasks fairly based on capacity and skill', 'weightage' => 7],
            ['category' => 'Follow-ups', 'title' => 'Follows up on every overdue junior task within 24 hours', 'weightage' => 8],
            ['category' => 'Mentoring', 'title' => 'Holds 1:1 with every junior at least twice a month', 'weightage' => 6],
            ['category' => 'Reviews', 'title' => 'Submits monthly performance notes for each junior', 'weightage' => 5],
            ['category' => 'Process', 'title' => 'Owns at least one process-improvement initiative per quarter', 'weightage' => 4],
        ];
    }
}
