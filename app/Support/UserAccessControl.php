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
        try {
            $user->forceFill(['logined' => 0])->save();
        } catch (\Throwable) {
        }

        try {
            $user->tokens()->delete();
        } catch (\Throwable) {
        }

        try {
            app(AttendanceService::class)->clockOut($user);
        } catch (\Throwable) {
        }

        try {
            if (Schema::hasTable('attendance_devices')) {
                AttendanceDevice::query()
                    ->where('user_id', $user->id)
                    ->update(['is_active' => false]);
            }
        } catch (\Throwable) {
        }
    }
}
