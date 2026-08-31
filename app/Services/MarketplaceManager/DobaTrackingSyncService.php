<?php

namespace App\Services\MarketplaceManager;

use App\Models\DobaDailyData;
use App\Models\MarketplaceSyncSettings;
use Illuminate\Support\Facades\Log;

/**
 * Tracking push stub — Doba shipment API may be wired later.
 */
class DobaTrackingSyncService
{
    /**
     * @return array{success: bool, skipped?: bool, message: string, action?: string|null}
     */
    public function pushTrackingForOrder(DobaDailyData $order): array
    {
        unset($order);

        if (! self::canPushTracking()) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Push tracking to Doba is Off in settings.',
            ];
        }

        return [
            'success' => false,
            'skipped' => true,
            'action' => 'not_implemented',
            'message' => 'Doba tracking push is not implemented yet.',
        ];
    }

    /**
     * @return array{success: bool, message: string, attempted: int, pushed: int, skipped: int}
     */
    public function syncPending(int $limit = 40): array
    {
        unset($limit);

        if (! self::canPushTracking()) {
            return [
                'success' => true,
                'message' => 'Tracking push disabled.',
                'attempted' => 0,
                'pushed' => 0,
                'skipped' => 0,
            ];
        }

        Log::info('DobaTrackingSyncService: syncPending skipped (not implemented)');

        return [
            'success' => true,
            'message' => 'Doba tracking push is not implemented yet.',
            'attempted' => 0,
            'pushed' => 0,
            'skipped' => 0,
        ];
    }

    public static function canPushTracking(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('doba');

        return (bool) ($settings['order']['push_tracking_to_doba'] ?? true);
    }
}
