<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Orders entered by hand for marketplaces without an API. One row per SKU line.
 */
class OrderFulfillmentManualOrder extends Model
{
    public const STATUS_CREATED = 'Order Created';

    public const STATUS_FULFILLED = 'Fulfilled';

    public const STATUS_CANCELLED = 'Cancelled';

    protected $table = 'order_fulfillment_manual_orders';

    protected $fillable = [
        'marketplace',
        'order_id',
        'order_date',
        'sku',
        'qty',
        'unit_price',
        'paid',
        'amount',
        'reference',
        'customer_name',
        'customer_email',
        'customer_phone',
        'address1',
        'address2',
        'city',
        'state',
        'zip',
        'country',
        'notes',
        'status',
        'fulfilled_at',
        'created_by',
        'fulfilled_by',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'fulfilled_at' => 'datetime',
        'paid' => 'boolean',
        'qty' => 'integer',
        'amount' => 'decimal:2',
        'unit_price' => 'decimal:2',
    ];
}
