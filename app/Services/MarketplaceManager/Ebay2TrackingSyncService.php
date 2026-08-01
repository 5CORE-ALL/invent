<?php

namespace App\Services\MarketplaceManager;

use App\Models\Ebay2OrderMetric;
use App\Models\MarketplaceSyncSettings;
use App\Services\EbayTwoApiService;
use Illuminate\Support\Facades\Log;

/**
 * Tracking push stub — eBay createShippingFulfillment / CompleteSale is not wired yet.
 */
class Ebay2TrackingSyncService
{
    public function __construct(
        protected EbayTwoApiService $ebay2Api,
        protected Ebay2OrderDetailService $orderDetailService,
        protected Ebay2DetailFormatter $formatter,
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
    public function pushTrackingForOrder(Ebay2OrderMetric $line): array
    {
        return [
            'success' => false,
            'skipped' => true,
            'action' => 'not_implemented',
            'message' => 'eBay 2 tracking push is not implemented yet (Sell Fulfillment createShippingFulfillment / CompleteSale).',
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
                'message' => 'Push Shopify tracking to eBay 2 is Off in settings (or not implemented).',
            ];
        }

        $limit = max(1, min(100, $limit));
        $lines = Ebay2OrderMetric::query()
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

        Log::info('Ebay2TrackingSyncService: stub pass', [
            'processed' => $processed,
            'skipped' => $skipped,
        ]);

        return [
            'success' => true,
            'processed' => $processed,
            'pushed' => 0,
            'skipped' => $skipped,
            'failed' => 0,
            'message' => 'Tracking push not implemented for eBay 2 yet. Checked '.$processed.' order(s).',
        ];
    }

    public static function canAutoPush(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('ebay2');

        return (bool) ($settings['order']['push_tracking_to_ebay2']
            ?? $settings['order']['push_tracking_to_shein']
            ?? false);
    }
}
