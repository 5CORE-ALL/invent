<?php

namespace App\Support;

use App\Models\AttendanceDevice;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use Illuminate\Support\Facades\Schema;

/**
 * Revoke web sessions, desktop-agent tokens, and attendance tracking
 * when an account is deactivated or deleted.
 */
class UserAccessControl
{
    public static function revoke(User $user): void
    {
        AttendanceForceLogout::flag($user);

        try {
            $user->forceFill(['logined' => 0])->save();
        } catch (\Throwable) {
        }

        app(AttendanceService::class)->clockOutAll($user);

        try {
            if (Schema::hasTable('attendance_devices')) {
                AttendanceDevice::query()
                    ->where('user_id', $user->id)
                    ->update(['is_active' => false]);
            }
        } catch (\Throwable) {
        }
    }

    public static function restore(User $user): void
    {
        AttendanceForceLogout::clear($user);

        try {
            $user->forceFill(['logined' => 1])->save();
        } catch (\Throwable) {
        }

        try {
            if (Schema::hasTable('attendance_devices')) {
                AttendanceDevice::query()
                    ->where('user_id', $user->id)
                    ->update(['is_active' => true]);
            }
        } catch (\Throwable) {
        }
    }
}
