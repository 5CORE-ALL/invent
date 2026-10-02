<?php

namespace App\Support;

use App\Models\AttendanceDevice;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

    /**
     * Reactivation is treated as a new person taking over the account: every credential the
     * previous holder could still be using is destroyed, so a forgotten desktop agent on an old
     * laptop cannot keep uploading under the reused id once the new holder clocks in.
     *
     * @return array{tokens: int, devices: int}
     */
    public static function resetForNewHolder(User $user): array
    {
        AttendanceForceLogout::clear($user);
        app(AttendanceService::class)->clockOutAll($user);

        $tokens = 0;
        try {
            $tokens = (int) $user->tokens()->delete();
        } catch (\Throwable) {
        }

        $devices = 0;
        try {
            if (Schema::hasTable('attendance_devices')) {
                $devices = (int) AttendanceDevice::query()->where('user_id', $user->id)->delete();
            }
        } catch (\Throwable) {
        }

        try {
            // logined = 0 signs out every open browser session; a new remember token invalidates
            // "remember me" cookies of the previous holder.
            $user->forceFill(['logined' => 0])->setRememberToken(Str::random(60));
            $user->save();
        } catch (\Throwable) {
        }

        return ['tokens' => $tokens, 'devices' => $devices];
    }
}
