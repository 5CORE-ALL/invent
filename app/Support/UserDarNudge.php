<?php

namespace App\Support;

final class UserDarNudge
{
    /**
     * @return list<string>
     */
    public static function logoutMessages(): array
    {
        return [
            'File today\'s DAR before you leave. Keep your score above 90% to stay eligible for promotions and incentives.',
            'A skipped DAR pulls your score down. Stay above 90% so promotions and incentives stay within reach.',
            'Don\'t log out without your Daily Activity Report. 90%+ DAR is how promotions and incentives get earned.',
            'Aim for 90% or more. Consistent DAR is how promotions and incentive rewards get unlocked.',
            'Take one minute to fill DAR. Keeping it above 90% shows the consistency we reward with promotions and incentives.',
        ];
    }
}
