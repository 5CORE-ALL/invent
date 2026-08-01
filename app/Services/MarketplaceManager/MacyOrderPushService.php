<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\MacyOrderMetric;
use Illuminate\Support\Facades\Log;

class MacyOrderPushService
{
    public ?string $lastFailureReason = null;

    public ?int $lastApiStatus = null;

    /**
     * @return array<string, mixed>
     */
    public function previewShopifyPush(MacyOrderMetric $order): array
    {
        return [
            'success' => false,
            'dry_run' => true,
            'message' => 'Macy\'s → Shopify order import preview is not fully implemented yet.',
            'order_id' => $order->order_id,
            'order_number' => $order->channel_order_id,
            'sku' => $order->sku,
        ];
    }

    public function importToShopify(MacyOrderMetric $order): ?string
    {
        Log::warning('MacyOrderPushService: import not fully implemented', [
            'id' => $order->id,
            'order_id' => $order->order_id,
        ]);
        $this->lastFailureReason = 'Macy\'s → Shopify order import is not fully implemented yet.';

        return null;
    }

    public static function canAutoSyncAddress(): bool
    {
        $settings = MarketplaceSyncSettings::getFor('macy');

        return (bool) ($settings['order']['sync_address_to_shopify'] ?? false);
    }

    /**
     * @return array{success: bool, checked: int, updated: int, skipped: int, failed: int, message: string}
     */
    public function syncPendingAddressesToShopify(int $limit = 40): array
    {
        unset($limit);

        return [
            'success' => true,
            'checked' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'message' => 'Macy\'s address sync is not fully implemented yet.',
        ];
    }
}
