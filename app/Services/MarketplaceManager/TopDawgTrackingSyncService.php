<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\TopDawgOrderMetric;
use App\Services\TopDawgApiService;
use Illuminate\Support\Facades\Log;

/**
 * Tracking push stub — TopDawg shipment API is not confirmed/wired yet.
 */
class TopDawgTrackingSyncService
{
    public function __construct(
        protected TopDawgApiService $topdawgApi,
    ) {}

    /**
     * @return array{success: bool, skipped?: bool, message: string, action?: string|null}
     */
    public function pushTrackingForOrder(TopDawgOrderMetric $order): array
    {
        if (! self::canPushTracking()) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Push tracking to TopDawg is Off in settings.',
            ];
        }

        return [
            'success' => false,
            'skipped' => true,
            'action' => 'not_implemented',
            'message' => 'TopDawg tracking push is not implemented yet (no confirmed ship API).',
        ];
    }

    /**
     * @return array{success: bool, message: string, attempted: int, pushed: int, skipped: int}
     */
    public function syncPending(int $limit = 40): array
    {
        if (! self::canPushTracking()) {
            return [
                'success' => true,
                'message' => 'Tracking push disabled.',
                'attempted' => 0,
                'pushed' => 0,
                'skipped' => 0,
            ];
        }

        Log::info('TopDawgTrackingSyncService: syncPending skipped (not implemented)');

        return [
            'success' => true,
            'message' => 'TopDawg tracking push is not implemented yet.',
            'attempted' => 0,
            'pushed' => 0,
            'skipped' => 0,
        ];
    }

    public static function canPushTracking(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('topdawg');

        return (bool) ($settings['order']['push_tracking_to_topdawg'] ?? false);
    }
}
