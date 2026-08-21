<?php

namespace App\Support;

use App\Models\User;

class UserSettingsAccess
{
    /** @return list<string> */
    public static function adminEmails(): array
    {
        return array_map('strtolower', config('user_settings.admin_emails', []));
    }

    public static function canManage(?User $user = null): bool
    {
        if (SuperAdminAccess::is($user)) {
            return true;
        }

        $user ??= auth()->user();
        if (! $user) {
            return false;
        }

        return TeamManagementAccess::emailMatches((string) $user->email, self::adminEmails());
    }
}
