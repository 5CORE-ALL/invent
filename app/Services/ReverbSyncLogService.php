<?php

namespace App\Services;

use App\Models\ReverbSyncLog;

class ReverbSyncLogService
{
    /**
     * Log Shopify → Reverb inventory update (e.g. from sync command or manual).
     */
    public function logShopifyToReverb(
        string $sku,
        ?int $oldReverbQty,
        int $newQty,
        ?string $reverbListingId = null,
        ?int $userId = null,
        string $message = 'manually changed by reverb sync'
    ): ReverbSyncLog {
        return ReverbSyncLog::logInventoryUpdate(
            ReverbSyncLog::SOURCE_SHOPIFY,
            $sku,
            $oldReverbQty,
            $newQty,
            $reverbListingId,
            $message,
            $userId,
            null
        );
    }

    /**
     * Log Reverb → Shopify (e.g. after pushing Reverb order to Shopify).
     */
    public function logOrderPushedToShopify(
        string $reverbOrderNumber,
        ?int $shopifyOrderId,
        ?string $sku = null,
        string $message = 'order created by reverb'
    ): ReverbSyncLog {
        return ReverbSyncLog::logOrderPushed($reverbOrderNumber, $shopifyOrderId, $sku, $message, null);
    }

    /**
     * Log manual inventory change from dashboard.
     */
    public function logManualChange(
        string $sku,
        ?int $oldQty,
        int $newQty,
        ?string $reverbListingId = null,
        ?int $userId = null
    ): ReverbSyncLog {
        return ReverbSyncLog::logInventoryUpdate(
            ReverbSyncLog::SOURCE_MANUAL,
            $sku,
            $oldQty,
            $newQty,
            $reverbListingId,
            'manually changed by reverb sync',
            $userId,
            null
        );
    }
}
