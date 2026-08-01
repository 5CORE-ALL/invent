<?php

namespace App\Services\MarketplaceManager;

use App\Models\Ebay1OrderMetric;
use App\Models\MarketplaceSyncSettings;
use App\Services\EbayApiService;
use Illuminate\Support\Facades\Log;

/**
 * Tracking push stub — eBay createShippingFulfillment / CompleteSale is not wired yet.
 */
class Ebay1TrackingSyncService
{
    public function __construct(
        protected EbayApiService $ebay1Api,
        protected Ebay1OrderDetailService $orderDetailService,
        protected Ebay1DetailFormatter $formatter,
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
        return [
            'success' => false,
            'skipped' => true,
            'action' => 'not_implemented',
            'message' => 'eBay 1 tracking push is not implemented yet (Sell Fulfillment createShippingFulfillment / CompleteSale).',
            'shopify_tracking' => null,
            'shopify_carrier' => null,
        ];
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
                'message' => 'Push Shopify tracking to eBay 1 is Off in settings (or not implemented).',
            ];
        }

        $limit = max(1, min(100, $limit));
        $lines = Ebay1OrderMetric::query()
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $processed = 0;
        $skipped = 0;
        foreach ($lines as $line) {
            $processed++;
            $result = $this->pushTrackingForOrder($line);
            if (! empty($result['skipped'])) {
                $skipped++;
            }
        }

        Log::info('Ebay1TrackingSyncService: stub pass', [
            'processed' => $processed,
            'skipped' => $skipped,
        ]);

        return [
            'success' => true,
            'processed' => $processed,
            'pushed' => 0,
            'skipped' => $skipped,
            'failed' => 0,
            'message' => 'Tracking push not implemented for eBay 1 yet. Checked '.$processed.' order(s).',
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
