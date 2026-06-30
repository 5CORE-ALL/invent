<?php

namespace App\Support;

use App\Models\ManagerJunior;
use App\Models\User;

class AttendanceAccess
{
    public static function emailMatches(string $email, array $list): bool
    {
        $normalized = strtolower(trim($email));

        return in_array($normalized, array_map('strtolower', $list), true);
    }

    public static function isInternalEmployee(?User $user = null): bool
    {
        $user ??= auth()->user();
        if (! $user || ! ($user->is_active ?? true)) {
            return false;
        }

        $domain = (string) config('attendance.internal_email_domain', '@5core.com');
        if (str_ends_with(strtolower((string) $user->email), strtolower($domain))) {
            return true;
        }

        // Team members included in salary/payroll views
        return (bool) ($user->show_in_salary ?? false);
    }

    /** Whether the Attendance sidebar section should appear. */
    public static function canSeeMenu(?User $user = null): bool
    {
        $user ??= auth()->user();
        if (! $user) {
            return false;
        }

        if (self::emailMatches((string) $user->email, config('attendance.menu_emails', []))) {
            return true;
        }

        return self::isInternalEmployee($user)
            || self::canMonitor($user)
            || self::canAdmin($user);
    }

    public static function canMonitor(?User $user = null): bool
    {
        $user ??= auth()->user();
        if (! $user) {
            return false;
        }

        if (self::emailMatches((string) $user->email, config('attendance.monitor_emails', []))) {
            return true;
        }

        return in_array($user->role, ['admin', 'superadmin', 'manager'], true);
    }

    public static function canAdmin(?User $user = null): bool
    {
        $user ??= auth()->user();
        if (! $user) {
            return false;
        }

        return self::emailMatches((string) $user->email, config('attendance.admin_emails', []));
    }

    /**
     * User IDs the current user may view in the monitor dashboard.
     *
     * @return int[]|null null = all active internal employees
     */
    public static function viewableUserIds(?User $user = null): ?array
    {
        $user ??= auth()->user();
        if (! $user) {
            return [];
        }

        if (self::canMonitor($user) && (
            self::emailMatches((string) $user->email, config('attendance.monitor_emails', []))
            || self::canAdmin($user)
        )) {
            return null;
        }

        if (in_array($user->role, ['admin', 'superadmin', 'manager'], true)) {
            $juniorIds = ManagerJunior::query()
                ->where('manager_user_id', $user->id)
                ->pluck('junior_user_id')
                ->all();

            return array_values(array_unique(array_merge([$user->id], $juniorIds)));
        }

        return [$user->id];
    }

    public static function canViewUser(int $targetUserId, ?User $viewer = null): bool
    {
        $viewer ??= auth()->user();
        if (! $viewer) {
            return false;
        }

        if ($viewer->id === $targetUserId) {
            return true;
        }

        $allowed = self::viewableUserIds($viewer);

        return $allowed === null || in_array($targetUserId, $allowed, true);
    }
}
