<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Macy's order lines in mirakl_daily_data (Marketplace Manager).
 */
class MacyOrderMetric extends Model
{
    protected $table = 'mirakl_daily_data';

    protected $fillable = [
        'channel_name',
        'channel_id',
        'order_id',
        'channel_order_id',
        'order_line_id',
        'status',
        'order_created_at',
        'order_updated_at',
        'period',
        'sku',
        'product_title',
        'quantity',
        'unit_price',
        'currency',
        'tax_amount',
        'shipping_price',
        'shipping_tax',
        'billing_first_name',
        'billing_last_name',
        'billing_street',
        'billing_city',
        'billing_state',
        'billing_zip',
        'billing_country',
        'shipping_first_name',
        'shipping_last_name',
        'shipping_street',
        'shipping_city',
        'shipping_state',
        'shipping_zip',
        'shipping_country',
        'shipping_carrier',
        'shipping_method',
        'shopify_order_id',
        'pushed_to_shopify_at',
        'import_status',
        'raw_payload',
    ];

    protected $casts = [
        'order_created_at' => 'datetime',
        'order_updated_at' => 'datetime',
        'pushed_to_shopify_at' => 'datetime',
        'unit_price' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_price' => 'decimal:2',
        'shipping_tax' => 'decimal:2',
        'raw_payload' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('macys_inc', function (Builder $query) {
            $query->where('channel_name', "Macy's, Inc.");
        });
    }

    public function lineAmount(): float
    {
        $qty = max(1, (int) ($this->quantity ?? 1));
        $unit = (float) ($this->unit_price ?? 0);

        return round($unit * $qty, 2);
    }

    public function displayOrderId(): string
    {
        return (string) ($this->order_id ?: $this->channel_order_id ?: $this->id);
    }
}
