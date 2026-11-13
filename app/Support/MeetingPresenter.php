<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/** Small display helpers shared by the Meeting Room pages. */
class MeetingPresenter
{
    /** Profile photo URL, or null when the employee has none (the view then shows an initials avatar). */
    public static function avatarUrl(?User $user): ?string
    {
        if (! $user || blank($user->avatar)) {
            return null;
        }
        $avatar = (string) $user->avatar;
        if (Str::startsWith($avatar, ['http://', 'https://', '/'])) {
            return $avatar;
        }

        return asset('storage/'.$avatar);
    }

    public static function initials(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $out = '';
        foreach (array_slice(array_filter($parts), 0, 2) as $p) {
            $out .= mb_strtoupper(mb_substr($p, 0, 1));
        }

        return $out !== '' ? $out : '?';
    }

    /** Stable pleasant colour per person for the fallback avatar. */
    public static function colour(?string $name): string
    {
        $palette = ['#2563eb', '#0891b2', '#7c3aed', '#db2777', '#ea580c', '#16a34a', '#4f46e5', '#0d9488'];

        return $palette[abs(crc32(mb_strtolower((string) $name))) % count($palette)];
    }

    /** Same data as JSON for the floating widget. */
    public static function userCard(User $u): array
    {
        return [
            'id' => (int) $u->id,
            'name' => (string) $u->name,
            'designation' => (string) ($u->designation ?? ''),
            'avatar' => self::avatarUrl($u),
            'initials' => self::initials($u->name),
            'colour' => self::colour($u->name),
        ];
    }
}
