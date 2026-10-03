<?php

namespace App\Support;

use App\Models\User;
use App\Policies\TaskPolicy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Authorization for automate_tasks templates (the Automated Tasks tab).
 * Scheduling and execution jobs do not use this — they run as the system.
 */
class AutomatedTaskAccess
{
    public static function hasAdminAccess(?User $user): bool
    {
        return TaskPolicy::userHasAutomatedTaskAdminAccess($user);
    }

    public static function visibleTasks(User $user): Collection
    {
        $tasks = DB::table('automate_tasks')->orderByDesc('id')->get();

        return self::filterVisible($user, $tasks);
    }

    /**
     * @param  iterable<int, object>  $tasks
     */
    public static function filterVisible(User $user, iterable $tasks): Collection
    {
        $tasks = collect($tasks)->values();
        if (TaskPolicy::userHasAutomatedTaskAdminAccess($user)) {
            return $tasks;
        }

        $visible = [];
        foreach ($tasks as $task) {
            if (TaskPolicy::userCanViewAutomatedTask($user, $task)) {
                $visible[(int) $task->id] = true;
            }
        }

        // Subtask templates belong to the parent the user is allowed to see.
        $added = true;
        while ($added) {
            $added = false;
            foreach ($tasks as $task) {
                $id = (int) $task->id;
                if (isset($visible[$id])) {
                    continue;
                }
                $parentId = (int) ($task->parent_task_id ?? 0);
                if ($parentId > 0 && isset($visible[$parentId])) {
                    $visible[$id] = true;
                    $added = true;
                }
            }
        }

        return $tasks->filter(fn ($task) => isset($visible[(int) $task->id]))->values();
    }

    /**
     * @param  array<int, object>|null  $byId
     */
    public static function canView(User $user, object $task, ?array $byId = null): bool
    {
        if (TaskPolicy::userCanViewAutomatedTask($user, $task)) {
            return true;
        }

        return self::ancestorAllows($user, $task, $byId, 'view');
    }

    /**
     * @param  array<int, object>|null  $byId
     */
    public static function canModify(User $user, object $task, ?array $byId = null): bool
    {
        if (TaskPolicy::userCanModifyAutomatedTask($user, $task)) {
            return true;
        }

        return self::ancestorAllows($user, $task, $byId, 'modify');
    }

    /**
     * @param  array<int, object>|null  $byId
     */
    public static function canDelete(User $user, object $task, ?array $byId = null): bool
    {
        if (TaskPolicy::userCanDeleteAutomatedTask($user, $task)) {
            return true;
        }

        return self::ancestorAllows($user, $task, $byId, 'delete');
    }

    public static function find(int $id): ?object
    {
        $task = DB::table('automate_tasks')->where('id', $id)->first();

        return $task ?: null;
    }

    /**
     * @param  array<int, object>|null  $byId
     */
    private static function ancestorAllows(User $user, object $task, ?array $byId, string $ability): bool
    {
        $parentId = (int) ($task->parent_task_id ?? 0);
        $guard = 0;

        while ($parentId > 0 && $guard < 25) {
            $guard++;
            $parent = self::row($parentId, $byId);
            if (! $parent) {
                return false;
            }

            $allowed = match ($ability) {
                'modify' => TaskPolicy::userCanModifyAutomatedTask($user, $parent),
                'delete' => TaskPolicy::userCanDeleteAutomatedTask($user, $parent),
                default => TaskPolicy::userCanViewAutomatedTask($user, $parent),
            };
            if ($allowed) {
                return true;
            }

            $parentId = (int) ($parent->parent_task_id ?? 0);
        }

        return false;
    }

    /**
     * @param  array<int, object>|null  $byId
     */
    private static function row(int $id, ?array $byId): ?object
    {
        if ($byId !== null) {
            return $byId[$id] ?? null;
        }

        return self::find($id);
    }
}
