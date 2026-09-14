<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use App\Models\Permission;

class PermissionHelper
{
    public static function forgetUserPermissions($userId): void
    {
        Cache::forget('user_permissions_' . $userId);
        Cache::forget('user_column_permissions_' . $userId);
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
}