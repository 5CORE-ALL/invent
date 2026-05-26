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

    public static function canView(?User $user = null): bool
    {
        $user ??= auth()->user();
        if (! $user) {
            return false;
        }

        return self::emailMatches(
            (string) $user->email,
            config('team_management.viewer_emails', [])
        );
    }

    public static function canViewSalary(?User $user = null): bool
    {
        $user ??= auth()->user();
        if (! $user) {
            return false;
        }

        return self::emailMatches(
            (string) $user->email,
            config('team_management.salary_viewer_emails', [])
        );
    }

    public static function canEdit(?User $user = null): bool
    {
        $user ??= auth()->user();
        if (! $user) {
            return false;
        }

        return self::emailMatches(
            (string) $user->email,
            config('team_management.editor_emails', [])
        );
    }
}
