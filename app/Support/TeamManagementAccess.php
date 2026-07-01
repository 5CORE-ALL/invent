<?php

namespace App\Support;

use App\Models\User;

class TeamManagementAccess
{
    public static function emailMatches(string $email, array $list): bool
    {
        $normalized = strtolower(trim($email));

        return in_array($normalized, array_map('strtolower', $list), true);
    }

    protected static function granted(?User $user, array $list): bool
    {
        if (SuperAdminAccess::is($user)) {
            return true;
        }

        $user ??= auth()->user();
        if (! $user) {
            return false;
        }

        return self::emailMatches((string) $user->email, $list);
    }

    public static function canView(?User $user = null): bool
    {
        return self::granted($user, config('team_management.viewer_emails', []));
    }

    public static function canViewSalary(?User $user = null): bool
    {
        return self::granted($user, config('team_management.salary_viewer_emails', []));
    }

    public static function canEdit(?User $user = null): bool
    {
        return self::granted($user, config('team_management.editor_emails', []));
    }

    /** Edit users plus salary tab, import/export, and salary tools. */
    public static function canManageSalary(?User $user = null): bool
    {
        return self::canEdit($user) && self::canViewSalary($user);
    }

    /** See the Resume column on the Users table. */
    public static function canViewResume(?User $user = null): bool
    {
        return self::granted($user, config('team_management.resume_viewer_emails', []));
    }

    /** Add / edit / delete a user's resume file. */
    public static function canEditResume(?User $user = null): bool
    {
        return self::granted($user, config('team_management.resume_editor_emails', []));
    }
}
