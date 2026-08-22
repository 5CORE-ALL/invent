<?php

namespace App\Support;

use App\Models\User;

/**
 * Account status from the users table (is_active + soft deletes).
 * Shared by Team Management (/users/add) and Salary (/payroll).
 */
class UserAccountStatus
{
    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    public const DELETED = 'deleted';

    public const NA = 'na';

    /**
     * @return self::ACTIVE|self::INACTIVE|self::DELETED|self::NA
     */
    public static function for(?User $user): string
    {
        if (! $user) {
            return self::NA;
        }

        if (method_exists($user, 'trashed') && $user->trashed()) {
            return self::DELETED;
        }

        // Read raw DB value so null (N/A) is not coerced by the boolean cast.
        $raw = $user->getAttributes()['is_active'] ?? null;
        if ($raw === null) {
            return self::NA;
        }

        return (int) $raw === 1 ? self::ACTIVE : self::INACTIVE;
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::DELETED => 'Deleted',
            default => 'N/A',
        };
    }

    /** CSS modifier for status dots: green / yellow / red / gray */
    public static function dotClass(string $status): string
    {
        return match ($status) {
            self::ACTIVE => 'tbl-dot--green',
            self::INACTIVE => 'tbl-dot--yellow',
            self::DELETED => 'tbl-dot--red',
            default => 'tbl-dot--gray',
        };
    }

    /**
     * Persist status on the users table (same source as /users/add).
     */
    public static function apply(User $user, string $status): void
    {
        if ($status === self::ACTIVE) {
            if ($user->trashed()) {
                $user->restore();
            }
            $user->is_active = true;
            $user->deactivated_at = null;
            $user->save();
            UserAccessControl::restore($user);

            return;
        }

        if ($status === self::INACTIVE) {
            if ($user->trashed()) {
                $user->restore();
            }
            $user->is_active = false;
            $user->deactivated_at = now();
            $user->save();
            UserAccessControl::revoke($user);

            return;
        }

        if ($status === self::DELETED) {
            $user->is_active = false;
            $user->deactivated_at = now();
            $user->save();
            UserAccessControl::revoke($user);
            if (! $user->trashed()) {
                $user->delete();
            }

            return;
        }

        if ($status === self::NA) {
            if ($user->trashed()) {
                $user->restore();
            }
            // Bypass boolean cast so null persists as N/A.
            \Illuminate\Support\Facades\DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'is_active' => null,
                    'deactivated_at' => null,
                    'updated_at' => now(),
                ]);
            $user->refresh();
        }
    }
}
