<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VeeqoBillingShipment extends Model
{
    protected $table = 'veeqo_billing_shipments';

    protected $guarded = ['id'];

    protected $casts = [
        'charges' => 'array',
        'carrier_fees' => 'array',
        'raw_shipment' => 'array',
        'tracking_billed' => 'boolean',
        'tracking_cancelled' => 'boolean',
        'tracking_used' => 'boolean',
        'has_return_label' => 'boolean',
        'order_created_at' => 'datetime',
        'order_shipped_at' => 'datetime',
        'shipment_created_at' => 'datetime',
        'outbound_shipment_date' => 'datetime',
        'in_transit_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'returned_to_sender_at' => 'datetime',
        'veeqo_updated_at' => 'datetime',
    ];
}
