<?php

namespace App\Helpers;

use App\Models\Permission;
use App\Models\UserPermissionGrant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class PermissionHelper
{
    /** @var array<int, array<string, list<string>>> per-request memo of the person-level grants */
    protected static array $grantMemo = [];

    public static function forgetUserPermissions($userId): void
    {
        Cache::forget('user_permissions_' . $userId);
        Cache::forget('user_column_permissions_' . $userId);
        unset(self::$grantMemo[(int) $userId]);
    }

    public static function cacheUserPermissions($userId)
    {
        self::forgetUserPermissions($userId);

        $user = \App\Models\User::find($userId);
        if ($user) {
            $permission = Permission::where('role', $user->role)->first();
            if ($permission) {
                Cache::put('user_permissions_' . $userId, $permission->permissions, now()->addHours(2));
                Cache::put('user_column_permissions_' . $userId, $permission->culomn_permission, now()->addHours(2));
            }
        }
    }

    /**
     * Extra access given to this person by name: [module => [actions]].
     *
     * @return array<string, list<string>>
     */
    public static function grantsForUser($userId): array
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return [];
        }
        if (array_key_exists($userId, self::$grantMemo)) {
            return self::$grantMemo[$userId];
        }

        $grants = [];
        try {
            if (Schema::hasTable('user_permission_grants')) {
                foreach (UserPermissionGrant::query()->where('user_id', $userId)->get(['module', 'actions']) as $row) {
                    $actions = array_values(array_intersect(UserPermissionGrant::ACTIONS, (array) $row->actions));
                    if ($actions !== []) {
                        $grants[(string) $row->module] = $actions;
                    }
                }
            }
        } catch (\Throwable $e) {
            // A missing table or a database hiccup must never break the page: fall back to the role only.
            $grants = [];
        }

        return self::$grantMemo[$userId] = $grants;
    }

    /**
     * The person's role permissions plus whatever was granted to them personally.
     * A grant only ever adds actions; it never removes anything the role already allows.
     *
     * @param  array<string, mixed>|null  $rolePermissions  [module => [actions]] for the person's role
     * @return array<string, mixed>
     */
    public static function mergeWithGrants($userId, ?array $rolePermissions): array
    {
        $merged = is_array($rolePermissions) ? $rolePermissions : [];

        foreach (self::grantsForUser($userId) as $module => $actions) {
            $existing = isset($merged[$module]) && is_array($merged[$module]) ? $merged[$module] : [];
            // Some modules (for example product_lists) store a list of column names, not view/create/edit/delete.
            if (array_diff($existing, UserPermissionGrant::ACTIONS) !== []) {
                continue;
            }
            $merged[$module] = array_values(array_unique(array_merge($existing, $actions)));
        }

        return $merged;
    }
}
