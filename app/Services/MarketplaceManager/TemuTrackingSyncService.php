<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\TemuOrder;
use Illuminate\Support\Facades\Log;

/**
 * Tracking push stub — Temu shipment API is not wired yet.
 */
class TemuTrackingSyncService
{
    /**
     * @return array{success: bool, skipped?: bool, message: string, action?: string|null}
     */
    public function pushTrackingForOrder(TemuOrder $order): array
    {
        unset($order);

        if (! self::canPushTracking()) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Push tracking to Temu is Off in settings.',
            ];
        }

        return [
            'success' => false,
            'skipped' => true,
            'action' => 'not_implemented',
            'message' => 'Temu tracking push is not implemented yet.',
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

        Log::info('TemuTrackingSyncService: syncPending skipped (not implemented)');

        return [
            'success' => true,
            'message' => 'Temu tracking push is not implemented yet.',
            'attempted' => 0,
            'pushed' => 0,
            'skipped' => 0,
        ];
    }

    public static function canPushTracking(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('temu');

        return (bool) ($settings['order']['push_tracking_to_temu'] ?? false);
    }
}
