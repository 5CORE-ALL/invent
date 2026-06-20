<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaskPolicy
{
    /**
     * Emails with this permission can delete or modify anybody's task (case-insensitive).
     * president@5core.com and software5@5core.com (plus each task's own assignor) may
     * edit/delete tasks.
     */
    private const SPECIAL_TASK_DELETE_MODIFY_EMAILS = [
        'president@5core.com',
        'software5@5core.com',
    ];

    /**
     * Names from the users table that get the same full-access permission as the
     * special emails above. Intentionally empty — only the president override and
     * the task's assignor may edit/delete.
     */
    private const SPECIAL_TASK_DELETE_MODIFY_NAMES = [];

    /** Cleanup Missed Daily, Today Deleted, and related revert/archive tools. */
    private const TASK_MAINTENANCE_TOOL_EMAILS = [
        'president@5core.com',
        'presiden@5core.com',
        'software5@5core.com',
    ];

    public static function userCanAccessTaskMaintenanceTools(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $email = strtolower(trim((string) ($user->email ?? '')));

        return $email !== '' && in_array($email, self::TASK_MAINTENANCE_TOOL_EMAILS, true);
    }

    /**
     * Check if user is admin
     */
    private function isAdmin(User $user): bool
    {
        return strtolower($user->role ?? '') === 'admin';
    }

    /**
     * assign_to may be a single email or comma-separated emails (shared task).
     */
    private function userIsAssignee(User $user, Task $task): bool
    {
        $assignTo = trim((string) ($task->assign_to ?? ''));
        if ($assignTo === '') {
            return false;
        }
        $needle = strtolower(trim((string) ($user->email ?? '')));
        if ($needle === '') {
            return false;
        }
        foreach (array_map('trim', explode(',', $assignTo)) as $part) {
            if ($part !== '' && strtolower($part) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has special permission to delete/modify any task.
     *
     * Resolution order (all sourced from the users table):
     *   1. users.email matches one of SPECIAL_TASK_DELETE_MODIFY_EMAILS
     *   2. users.name (full string OR first token) matches one of
     *      SPECIAL_TASK_DELETE_MODIFY_NAMES — lets us grant access by
     *      person-name (e.g. "Hritiksha" → row "Hritiksha Deb") without
     *      having to know their current login email.
     */
    public static function userHasSpecialTaskPermission(User $user): bool
    {
        $email = trim(strtolower((string) ($user->email ?? '')));
        if ($email !== '' && in_array($email, self::SPECIAL_TASK_DELETE_MODIFY_EMAILS, true)) {
            return true;
        }

        $name = trim(strtolower((string) ($user->name ?? '')));
        if ($name === '') {
            return false;
        }
        if (in_array($name, self::SPECIAL_TASK_DELETE_MODIFY_NAMES, true)) {
            return true;
        }
        $firstToken = trim((string) (preg_split('/\s+/', $name, 2)[0] ?? ''));
        return $firstToken !== '' && in_array($firstToken, self::SPECIAL_TASK_DELETE_MODIFY_NAMES, true);
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can access the task list page
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Task $task): bool
    {
        // Admin can view all tasks
        if ($this->isAdmin($user)) {
            return true;
        }

        // User can view if they are the assignor OR assignee (old table uses emails; assign_to can list several)
        return $task->assignor === $user->email || $this->userIsAssignee($user, $task);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // All authenticated users can create tasks
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * Full edit (title, group, date, assignee, priority, etc): assignor +
     * president override only. Assignees use {@see updateLinks()} to add
     * reference/SOP links so they can deliver and review their own work.
     */
    public function update(User $user, Task $task): bool
    {
        // President override can modify any task.
        if (self::userHasSpecialTaskPermission($user)) {
            return true;
        }

        // Otherwise only the assignor (task creator) can edit their own task.
        return $task->assignor === $user->email;
    }

    /**
     * Determine whether the user can update only the link/reference fields
     * (l1/l2/training/video/form/report/checklist/pl/process) on a task.
     *
     * Granted to anyone who can do a full update plus the task's assignee(s),
     * so assignees can attach proof links to make review/done verification
     * easier. Title, group, dates, assignee and priority remain locked for
     * assignees and must go through {@see update()}.
     */
    public function updateLinks(User $user, Task $task): bool
    {
        if ($this->update($user, $task)) {
            return true;
        }

        return $this->userIsAssignee($user, $task);
    }

    /**
     * Determine whether the user can update only the status.
     */
    public function updateStatus(User $user, Task $task): bool
    {
        // Admin can update status on all tasks
        if ($this->isAdmin($user)) {
            return true;
        }

        // User can update status if they are assignor OR assignee (assign_to can be comma-separated)
        return $task->assignor === $user->email || $this->userIsAssignee($user, $task);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Task $task): bool
    {
        // Special permission: listed emails can delete any task
        if (self::userHasSpecialTaskPermission($user)) {
            return true;
        }

        // Only the assignor (task creator) can delete - even admins cannot delete unless they are the assignor
        return $task->assignor === $user->email;
    }

    /**
     * Determine whether the user can perform bulk operations.
     */
    public function bulkUpdate(User $user): bool
    {
        // Only admin can perform bulk operations
        return $this->isAdmin($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Task $task): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Task $task): bool
    {
        return $this->isAdmin($user);
    }
}
