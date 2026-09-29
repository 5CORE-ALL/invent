<?php

namespace App\Support;

use App\Models\User;

class SocialMediaAccess
{
    public static function canView(User $user): bool
    {
        return $user->is5CoreMember() || self::canManage($user);
    }

    public static function canManage(User $user): bool
    {
        if ($user->isDirector()) {
            return true;
        }

        $emails = array_map('strtolower', config('social_media.manager_emails', []));

        return in_array(strtolower((string) $user->email), $emails, true);
    }
}
