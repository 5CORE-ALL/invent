<?php

namespace App\Services\MarketplaceManager;

use App\Models\AlibabaOrderMetric;
use App\Models\MarketplaceSyncSettings;
use Illuminate\Support\Facades\Log;

/**
 * Tracking push stub — Alibaba shipment API is not wired yet.
 */
class AlibabaTrackingSyncService
{
    /**
     * @return array{success: bool, skipped?: bool, message: string, action?: string|null}
     */
    public function pushTrackingForOrder(AlibabaOrderMetric $order): array
    {
        unset($order);

        if (! self::canPushTracking()) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Push tracking to Alibaba is Off in settings.',
            ];
        }

        return [
            'success' => false,
            'skipped' => true,
            'action' => 'not_implemented',
            'message' => 'Alibaba tracking push is not implemented yet (no ship API wired).',
        ];
    }

    /**
     * Bulk push Shopify fulfillment tracking → Alibaba for linked orders.
     *
     * @return array{success: bool, message: string, attempted: int, pushed: int, skipped: int}
     */
    public function syncFromShopify(int $limit = 40): array
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

        Log::info('AlibabaTrackingSyncService: syncFromShopify skipped (not implemented)');

        return [
            'success' => true,
            'message' => 'Alibaba tracking push is not implemented yet (no ship API wired).',
            'attempted' => 0,
            'pushed' => 0,
            'skipped' => 0,
        ];
    }

    public static function canPushTracking(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('alibaba');

        return (bool) ($settings['order']['push_tracking_to_alibaba'] ?? false);
    }

    public static function canAutoPush(?array $settings = null): bool
    {
        return self::canPushTracking($settings);
    }
}
