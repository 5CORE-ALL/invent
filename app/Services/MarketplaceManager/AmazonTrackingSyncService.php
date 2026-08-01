<?php

namespace App\Services\MarketplaceManager;

use App\Models\AmazonOrder;
use App\Models\MarketplaceSyncSettings;
use App\Services\AmazonSpApiService;
use Illuminate\Support\Facades\Log;

/**
 * Tracking push stub — Amazon confirmShipment wiring is not enabled in MM yet.
 */
class AmazonTrackingSyncService
{
    public function __construct(
        protected AmazonSpApiService $amazonApi,
    ) {}

    /**
     * @return array{success: bool, skipped?: bool, message: string, action?: string|null}
     */
    public function pushTrackingForOrder(AmazonOrder $order): array
    {
        if (! self::canPushTracking()) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Push tracking to Amazon is Off in settings.',
            ];
        }

        return [
            'success' => false,
            'skipped' => true,
            'action' => 'not_implemented',
            'message' => 'Amazon tracking push is not implemented yet (Seller Central / confirmShipment not wired).',
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

        Log::info('AmazonTrackingSyncService: syncPending skipped (not implemented)');

        return [
            'success' => true,
            'message' => 'Amazon tracking push is not implemented yet.',
            'attempted' => 0,
            'pushed' => 0,
            'skipped' => 0,
        ];
    }

    /**
     * Alias for command/job naming parity with TopDawg.
     *
     * @return array{success: bool, message: string, attempted: int, pushed: int, skipped: int}
     */
    public function syncFromShopify(int $limit = 40): array
    {
        return $this->syncPending($limit);
    }

    public static function canPushTracking(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('amazon');

        return (bool) ($settings['order']['push_tracking_to_amazon'] ?? false);
    }
}
