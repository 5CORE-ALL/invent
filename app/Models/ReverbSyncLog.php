<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReverbSyncLog extends Model
{
    protected $table = 'reverb_sync_logs';

    protected $fillable = [
        'source',
        'action',
        'sku',
        'reverb_listing_id',
        'shopify_inventory_item_id',
        'shopify_order_id',
        'reverb_order_number',
        'old_quantity',
        'new_quantity',
        'user_id',
        'message',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'old_quantity' => 'integer',
        'new_quantity' => 'integer',
    ];

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_REVERB = 'reverb';
    public const SOURCE_SHOPIFY = 'shopify';

    public const ACTION_INVENTORY_UPDATE = 'inventory_update';
    public const ACTION_ORDER_CREATED = 'order_created';
    public const ACTION_ORDER_PUSHED = 'order_pushed';

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public static function logInventoryUpdate(
        string $source,
        string $sku,
        ?int $oldQty,
        int $newQty,
        ?string $reverbListingId = null,
        ?string $message = null,
        ?int $userId = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'source' => $source,
            'action' => self::ACTION_INVENTORY_UPDATE,
            'sku' => $sku,
            'reverb_listing_id' => $reverbListingId,
            'old_quantity' => $oldQty,
            'new_quantity' => $newQty,
            'user_id' => $userId,
            'message' => $message ?? ($source === self::SOURCE_MANUAL ? 'manually changed by reverb sync' : null),
            'metadata' => $metadata,
        ]);
    }

    public static function logOrderPushed(
        string $reverbOrderNumber,
        ?int $shopifyOrderId,
        ?string $sku = null,
        ?string $message = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'source' => self::SOURCE_REVERB,
            'action' => self::ACTION_ORDER_PUSHED,
            'sku' => $sku,
            'reverb_order_number' => $reverbOrderNumber,
            'shopify_order_id' => $shopifyOrderId,
            'message' => $message ?? 'order created by reverb',
            'metadata' => $metadata,
        ]);
    }
}
