<?php

namespace App\Services\MarketplaceManager;

use App\Models\Ebay1OrderMetric;
use App\Models\MarketplaceSyncSettings;
use Illuminate\Support\Facades\Log;

/**
 * Push Shopify tracking to eBay 1 via Sell Fulfillment createShippingFulfillment.
 */
class Ebay1TrackingSyncService
{
    public function __construct(
        protected EbaySellFulfillmentTracking $fulfillment,
    ) {}

    /**
     * @return array{
     *   success: bool,
     *   skipped?: bool,
     *   action?: string|null,
     *   message: string,
     *   shopify_tracking?: string|null,
     *   shopify_carrier?: string|null
     * }
     */
    public function pushTrackingForOrder(Ebay1OrderMetric $line): array
    {
        return $this->fulfillment->pushForChannel('ebay1', $line);
    }

    /**
     * @return array{success: bool, processed: int, pushed: int, skipped: int, failed: int, message: string}
     */
    public function syncPendingFromShopify(int $limit = 40): array
    {
        if (! self::canAutoPush()) {
            return [
                'success' => true,
                'processed' => 0,
                'pushed' => 0,
                'skipped' => 0,
                'failed' => 0,
                'message' => 'Push Shopify tracking to eBay 1 is Off in settings.',
            ];
        }

        $limit = max(1, min(100, $limit));
        $rows = Ebay1OrderMetric::query()
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->orderByDesc('id')
            ->limit($limit * 5)
            ->get();

        $unique = [];
        foreach ($rows as $row) {
            $orderId = trim((string) $row->order_id);
            if ($orderId === '' || isset($unique[$orderId])) {
                continue;
            }
            $unique[$orderId] = $row;
            if (count($unique) >= $limit) {
                break;
            }
        }

        $processed = 0;
        $pushed = 0;
        $skipped = 0;
        $failed = 0;
        foreach ($unique as $line) {
            $processed++;
            $result = $this->pushTrackingForOrder($line);
            if (! empty($result['success']) && empty($result['skipped'])) {
                $pushed++;
            } elseif (! empty($result['skipped'])) {
                $skipped++;
            } else {
                $failed++;
            }
            usleep(250000);
        }

        Log::info('Ebay1TrackingSyncService: completed', [
            'processed' => $processed,
            'pushed' => $pushed,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);

        return [
            'success' => $failed === 0,
            'processed' => $processed,
            'pushed' => $pushed,
            'skipped' => $skipped,
            'failed' => $failed,
            'message' => "Tracking sync: checked {$processed}, pushed {$pushed}, skipped {$skipped}, failed {$failed}.",
        ];
    }

    public static function canAutoPush(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('ebay1');

        return (bool) ($settings['order']['push_tracking_to_ebay1']
            ?? $settings['order']['push_tracking_to_shein']
            ?? false);
    }
}
