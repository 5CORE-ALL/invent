<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class AttendanceForceLogout
{
    public static function flag(User $user): void
    {
        Cache::put(self::key($user->id), now()->getTimestamp(), now()->addDay());
    }

    public static function clear(User $user): void
    {
        Cache::forget(self::key($user->id));
    }

    public static function isFlagged(User $user): bool
    {
        return Cache::has(self::key($user->id));
    }

    private static function key(int $userId): string
    {
        return 'attendance:force-logout:'.$userId;
    }
}
