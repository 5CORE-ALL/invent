<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\Temu2Order;
use Illuminate\Support\Facades\Log;

class Temu2OrderPushService
{
    public ?string $lastFailureReason = null;

    public ?int $lastApiStatus = null;

    /**
     * @return array<string, mixed>
     */
    public function previewShopifyPush(Temu2Order $order): array
    {
        return [
            'success' => false,
            'dry_run' => true,
            'message' => 'Temu → Shopify order import preview is not fully implemented yet.',
            'order_sn' => $order->order_sn,
            'parent_order_sn' => $order->parent_order_sn,
            'sku' => $order->ext_code,
        ];
    }

    public function importToShopify(Temu2Order $order): ?string
    {
        Log::warning('Temu2OrderPushService: import not fully implemented', [
            'id' => $order->id,
            'parent_order_sn' => $order->parent_order_sn,
        ]);
        $this->lastFailureReason = 'Temu → Shopify order import is not fully implemented yet.';

        return null;
    }

    public static function canAutoSyncAddress(): bool
    {
        $settings = MarketplaceSyncSettings::getFor('temu2');

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
            'message' => 'Temu address sync is not fully implemented yet.',
        ];
    }
}
