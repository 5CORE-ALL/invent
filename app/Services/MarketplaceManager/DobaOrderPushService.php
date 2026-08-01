<?php

namespace App\Services\MarketplaceManager;

use App\Models\DobaDailyData;
use App\Models\MarketplaceSyncSettings;
use Illuminate\Support\Facades\Log;

class DobaOrderPushService
{
    public ?string $lastFailureReason = null;

    public ?int $lastApiStatus = null;

    /**
     * @return array<string, mixed>
     */
    public function previewShopifyPush(DobaDailyData $order): array
    {
        return [
            'success' => false,
            'dry_run' => true,
            'message' => 'Doba → Shopify order import preview is not fully implemented yet.',
            'order_id' => $order->order_no,
            'order_number' => $order->platform_order_no ?? $order->order_no,
            'sku' => $order->sku,
        ];
    }

    public function importToShopify(DobaDailyData $order): ?string
    {
        Log::warning('DobaOrderPushService: import not fully implemented', [
            'id' => $order->id,
            'order_no' => $order->order_no,
        ]);
        $this->lastFailureReason = 'Doba → Shopify order import is not fully implemented yet.';

        return null;
    }

    public static function canAutoSyncAddress(): bool
    {
        $settings = MarketplaceSyncSettings::getFor('doba');

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
            'message' => 'Doba address sync is not fully implemented yet.',
        ];
    }
}
