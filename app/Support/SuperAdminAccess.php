<?php

namespace App\Support;

use App\Models\User;

class SuperAdminAccess
{
    /** @return list<string> */
    public static function emails(): array
    {
        return array_map('strtolower', config('super_admin.emails', []));
    }

    public static function is(?User $user = null): bool
    {
        $user ??= auth()->user();
        if (! $user) {
            return false;
        }

        $email = strtolower(trim((string) $user->email));

        return $email !== '' && in_array($email, self::emails(), true);
    }

    public static function allows(?User $user, array $allowedEmails): bool
    {
        if (self::is($user)) {
            return true;
        }

        if (! $user) {
            return false;
        }

        return TeamManagementAccess::emailMatches((string) $user->email, $allowedEmails);
    }

    public static function isTaskAdmin(?User $user = null): bool
    {
        $user ??= auth()->user();
        if (! $user) {
            return false;
        }

        return self::is($user) || strtolower((string) ($user->role ?? '')) === 'admin';
    }

    public static function isRoleAdmin(?User $user = null): bool
    {
        $user ??= auth()->user();
        if (! $user) {
            return false;
        }

        if (self::is($user)) {
            return true;
        }

        return in_array($user->role, ['admin', 'superadmin', 'manager'], true);
    }
}
