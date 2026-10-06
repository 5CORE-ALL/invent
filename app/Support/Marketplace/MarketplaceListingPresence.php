<?php

namespace App\Support\Marketplace;

/**
 * A marketplace row is a listing only while the seller portal still has it.
 * Deleted, draft, failed, and never-published rows are missing listings.
 */
final class MarketplaceListingPresence
{
    /**
     * @return list<string>
     */
    public static function absentStatuses(): array
    {
        return [
            'deleted',
            'delete',
            'draft',
            'failed',
            'pending',
            'unpublished',
            'archived',
            'retired',
            'service_delete',
            'delisted',
            'not_listed',
            'unlisted',
            'missing',
        ];
    }

    public static function isAbsent(?string $status): bool
    {
        $status = strtolower(trim((string) $status));
        $status = str_replace([' ', '-'], '_', $status);
        if ($status === '') {
            return false;
        }

        return in_array($status, self::absentStatuses(), true);
    }
}
