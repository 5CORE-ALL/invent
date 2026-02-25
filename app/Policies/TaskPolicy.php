<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaskPolicy
{
    /**
     * Emails with this permission can delete or modify anybody's task (case-insensitive).
     */
    private const SPECIAL_TASK_DELETE_MODIFY_EMAILS = [
        'ritu.kaur013@gmail.com',
        'sr.manager@5core.com',
        'sjoy7486@gmail.com',
        'inventory@5core.com',
        'president@5core.com',
        'ineetkalra@5core.com',
    ];

    /**
     * Check if user is admin
     */
    private function isAdmin(User $user): bool
    {
        return strtolower($user->role ?? '') === 'admin';
    }

    /**
     * Check if user has special permission to delete/modify any task (by email).
     */
    public static function userHasSpecialTaskPermission(User $user): bool
    {
        $email = trim(strtolower((string) ($user->email ?? '')));
        return in_array($email, self::SPECIAL_TASK_DELETE_MODIFY_EMAILS, true);
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

        // User can view if they are the assignor OR assignee (old table uses emails)
        return $task->assignor === $user->email || $task->assign_to === $user->email;
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
     */
    public function update(User $user, Task $task): bool
    {
        // Admin can update all tasks
        if ($this->isAdmin($user)) {
            return true;
        }

        // Special permission: listed emails can modify any task
        if (self::userHasSpecialTaskPermission($user)) {
            return true;
        }

        // User can update only if they are the assignor (task creator) - old table uses emails
        return $task->assignor === $user->email;
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

        // User can update status if they are assignor OR assignee - old table uses emails
        return $task->assignor === $user->email || $task->assign_to === $user->email;
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
