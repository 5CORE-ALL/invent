<?php

namespace App\Support;

use App\Models\User;
use App\Policies\TaskPolicy;

class TaskSelfAssign
{
    /**
     * True when the task has exactly one assignee and that person is the assignor.
     * Stored values may be an email, a full name, or a first name on older rows.
     */
    public static function isSamePerson(
        ?string $assignor,
        ?string $assignTo,
        ?User $assignorUser = null,
        ?User $assigneeUser = null
    ): bool {
        $assignor = trim((string) $assignor);
        $parts = self::assigneeParts($assignTo);
        if ($assignor === '' || count($parts) !== 1) {
            return false;
        }

        $assignee = $parts[0];
        if (strcasecmp($assignor, $assignee) === 0) {
            return true;
        }

        if ($assignorUser && $assigneeUser && (int) $assignorUser->id > 0 && (int) $assignorUser->id === (int) $assigneeUser->id) {
            return true;
        }

        if ($assigneeUser && TaskPolicy::userIsAssignor($assigneeUser, $assignor)) {
            return true;
        }

        if ($assignorUser && TaskPolicy::userIsAssignor($assignorUser, $assignee)) {
            return true;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function assigneeParts(?string $assignTo): array
    {
        $unique = [];
        foreach (array_map('trim', explode(',', (string) $assignTo)) as $part) {
            if ($part === '') {
                continue;
            }
            $key = strtolower($part);
            if (! isset($unique[$key])) {
                $unique[$key] = $part;
            }
        }

        return array_values($unique);
    }
}
