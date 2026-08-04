<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\Temu2Order;
use Illuminate\Support\Facades\Log;

/**
 * Tracking push stub — Temu 2 shipment API is not wired yet.
 */
class Temu2TrackingSyncService
{
    /**
     * @return array{success: bool, skipped?: bool, message: string, action?: string|null}
     */
    public function pushTrackingForOrder(Temu2Order $order): array
    {
        unset($order);

        if (! self::canPushTracking()) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Push tracking to Temu 2 is Off in settings.',
            ];
        }

        return [
            'success' => false,
            'skipped' => true,
            'action' => 'not_implemented',
            'message' => 'Temu 2 tracking push is not implemented yet.',
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

        Log::info('Temu2TrackingSyncService: syncPending skipped (not implemented)');

        return [
            'success' => true,
            'message' => 'Temu 2 tracking push is not implemented yet.',
            'attempted' => 0,
            'pushed' => 0,
            'skipped' => 0,
        ];
    }

    public static function canPushTracking(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('temu2');

        return (bool) ($settings['order']['push_tracking_to_temu2'] ?? false);
    }
}
