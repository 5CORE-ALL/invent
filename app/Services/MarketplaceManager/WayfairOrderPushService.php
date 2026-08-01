<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\WayfairDailyData;
use Illuminate\Support\Facades\Log;

class WayfairOrderPushService
{
    public ?string $lastFailureReason = null;

    public ?int $lastApiStatus = null;

    /**
     * @return array<string, mixed>
     */
    public function previewShopifyPush(WayfairDailyData $order): array
    {
        return [
            'success' => false,
            'dry_run' => true,
            'message' => 'Wayfair → Shopify order import preview is not fully implemented yet.',
            'order_id' => $order->po_number,
            'order_number' => $order->po_number,
            'sku' => $order->sku,
        ];
    }

    public function importToShopify(WayfairDailyData $order): ?string
    {
        Log::warning('WayfairOrderPushService: import not fully implemented', [
            'id' => $order->id,
            'po_number' => $order->po_number,
        ]);
        $this->lastFailureReason = 'Wayfair → Shopify order import is not fully implemented yet.';

        return null;
    }

    public static function canAutoSyncAddress(): bool
    {
        $settings = MarketplaceSyncSettings::getFor('wayfair');

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
            'message' => 'Wayfair address sync is not fully implemented yet.',
        ];
    }
}
