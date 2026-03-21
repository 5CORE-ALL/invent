<?php

namespace App\Services\Wms;

use App\Models\User;

class WmsAuthorization
{
    public static function isAdmin(User $user): bool
    {
        return in_array($user->role, ['admin', 'superadmin'], true);
    }

    public static function isWarehouseManager(User $user): bool
    {
        return in_array($user->role, ['warehouse_manager', 'manager'], true);
    }

    public static function isWarehouseStaff(User $user): bool
    {
        return in_array($user->role, ['warehouse_staff', 'staff'], true);
    }

    public static function canAdjustStock(User $user): bool
    {
        return self::isAdmin($user) || self::isWarehouseManager($user);
    }

    public static function canMoveStock(User $user): bool
    {
        return self::isAdmin($user) || self::isWarehouseManager($user) || self::isWarehouseStaff($user);
    }

    /**
     * PICK without prior lock: managers may bypass; staff must use locks.
     */
    public static function canPickWithoutLock(User $user): bool
    {
        return self::isAdmin($user) || self::isWarehouseManager($user);
    }
}
