<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Support\SuperAdminAccess;
use Illuminate\Auth\Access\Response;

class TaskPolicy
{
    /**
     * Known login emails for full task access (any action on any task).
     * Permission is always checked against the logged-in email — never the display name.
     */
    private const FULL_ACCESS_EMAILS = [
        'president@5core.com',          // Amarjit Singh
        'presiden@5core.com',           // Known typo login used by President
        'sr.manager@5core.com',         // Jasmine
        'inventory@5core.com',          // Ritu
        'ritu.kaur013@gmail.com',       // Ritu
        'sjoy7486@gmail.com',           // Joy (SJOY)
        'sourcing@5core.com',           // Joy Huang
        'ineetkalra@5core.com',         // Ineet / Innet
        'priyanka@5core.com',           // Priyanka
        'priyankakalra@5core.com',      // Priyanka Kalra
        'software5@5core.com',          // Shobha
        'mgr-operations@5core.com',     // Hritiksha
        'mgr-content@5core.com',        // Srimanta
        'support@5core.com',            // Jisan
        'mgr-advertisement@5core.com',  // Nishtha
    ];

    /**
     * Users.name tokens used only to look up extra emails from the users table
     * (in case a login email changed). Not used to grant access by display name.
     */
    private const FULL_ACCESS_NAME_NEEDLES = [
        'jasmine',
        'ritu',
        'joy',
        'sjoy',
        'ineet',
        'innet',
        'priyanka',
        'amarjit',
        'president',
        'shobha',
    ];

    /** @var list<string>|null */
    private static ?array $fullAccessEmailCache = null;

    public static function resetFullAccessEmailCache(): void
    {
        self::$fullAccessEmailCache = null;
    }

    public static function userCanDeleteCorrectiveTasks(?User $user): bool
    {
        return $user !== null && self::userHasFullTaskAccess($user);
    }

    public static function taskIsCorrectiveAction(Task $task): bool
    {
        return (bool) ($task->is_corrective_action ?? false);
    }

    public static function userCanAccessTaskMaintenanceTools(?User $user): bool
    {
        return $user !== null && self::userHasFullTaskAccess($user);
    }

    /**
     * Check if user is admin
     */
    private function isAdmin(User $user): bool
    {
        return SuperAdminAccess::is($user) || strtolower($user->role ?? '') === 'admin';
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
     * Older task rows store assignor as a display name; newer rows store an email.
     * Match either, case-insensitive, including first-name-only stored values
     * (e.g. assignor "Amarjit" vs users.name "Amarjit Singh").
     */
    public static function userIsAssignor(User $user, mixed $assignor): bool
    {
        $assignor = strtolower(trim((string) $assignor));
        if ($assignor === '') {
            return false;
        }

        $email = strtolower(trim((string) ($user->email ?? '')));
        if ($email !== '' && $assignor === $email) {
            return true;
        }

        $name = strtolower(trim((string) ($user->name ?? '')));
        if ($name === '') {
            return false;
        }
        if ($assignor === $name) {
            return true;
        }

        $nameFirst = strtolower(trim((string) (preg_split('/\s+/', $name, 2)[0] ?? '')));

        return $nameFirst !== '' && ! str_contains($assignor, ' ') && $assignor === $nameFirst;
    }

    /**
     * Look up the user recorded as assignor (email or name on older rows).
     *
     * @param  iterable<int, User>|null  $users
     */
    public static function findUserForAssignorValue(?string $assignor, $users = null): ?User
    {
        $value = trim((string) $assignor);
        if ($value === '') {
            return null;
        }

        $pool = $users ?? User::query()->get(['id', 'name', 'email', 'avatar', 'designation']);
        foreach ($pool as $candidate) {
            if ($candidate instanceof User && self::userIsAssignor($candidate, $value)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Single source of truth for delete (row + bulk).
     * Full-access emails can delete any task (including CA); assignors can delete their own.
     */
    public static function userCanDeleteTask(User $user, Task $task): bool
    {
        if (self::userHasFullTaskAccess($user)) {
            return true;
        }

        if (self::taskIsCorrectiveAction($task)) {
            return false;
        }

        return self::userIsAssignor($user, $task->assignor);
    }

    /**
     * Full task access: any action on any task. Checked by login email only.
     * Extra emails are pulled from users.name (Jasmine, Ritu, Joy, Ineet, Priyanka, Amarjit, Shobha).
     */
    public static function userHasFullTaskAccess(User $user): bool
    {
        return SuperAdminAccess::is($user) || self::userHasSpecialTaskPermission($user);
    }

    /**
     * @return list<string>
     */
    public static function fullAccessEmails(): array
    {
        if (self::$fullAccessEmailCache !== null) {
            return self::$fullAccessEmailCache;
        }

        $emails = [];
        foreach (self::FULL_ACCESS_EMAILS as $email) {
            $email = strtolower(trim($email));
            if ($email !== '') {
                $emails[$email] = $email;
            }
        }

        try {
            $users = User::query()
                ->select(['name', 'email'])
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->get();
            foreach ($users as $candidate) {
                if (! self::userNameMatchesFullAccessNeedles((string) ($candidate->name ?? ''))) {
                    continue;
                }
                $candidateEmail = strtolower(trim((string) ($candidate->email ?? '')));
                if ($candidateEmail !== '') {
                    $emails[$candidateEmail] = $candidateEmail;
                }
            }
        } catch (\Throwable $e) {
            // users table unavailable (tests / boot) — hardcoded emails still apply
        }

        self::$fullAccessEmailCache = array_values($emails);

        return self::$fullAccessEmailCache;
    }

    public static function userNameMatchesFullAccessNeedles(string $name): bool
    {
        $name = strtolower(trim($name));
        if ($name === '') {
            return false;
        }

        $tokens = preg_split('/\s+/', $name) ?: [];
        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token !== '' && in_array($token, self::FULL_ACCESS_NAME_NEEDLES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the logged-in email is on the full-access list.
     * Display name on the session user is never enough by itself.
     */
    public static function userHasSpecialTaskPermission(User $user): bool
    {
        $email = trim(strtolower((string) ($user->email ?? '')));

        return $email !== '' && in_array($email, self::fullAccessEmails(), true);
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
        if ($this->isAdmin($user) || self::userHasFullTaskAccess($user)) {
            return true;
        }

        // User can view if they are the assignor OR assignee (assignor may be name or email)
        return self::userIsAssignor($user, $task->assignor) || $this->userIsAssignee($user, $task);
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
        if (self::userHasFullTaskAccess($user)) {
            return true;
        }

        // Otherwise only the assignor can edit their own task (name or email).
        return self::userIsAssignor($user, $task->assignor);
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
        if ($this->isAdmin($user) || self::userHasFullTaskAccess($user)) {
            return true;
        }

        // User can update status if they are assignor OR assignee (assign_to can be comma-separated)
        return self::userIsAssignor($user, $task->assignor) || $this->userIsAssignee($user, $task);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Task $task): bool
    {
        return self::userCanDeleteTask($user, $task);
    }

    /**
     * Determine whether the user can perform bulk operations.
     */
    public function bulkUpdate(User $user): bool
    {
        return $this->isAdmin($user) || self::userHasFullTaskAccess($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Task $task): bool
    {
        return $this->isAdmin($user) || self::userHasFullTaskAccess($user);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Task $task): bool
    {
        return $this->isAdmin($user) || self::userHasFullTaskAccess($user);
    }
}
