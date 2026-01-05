<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalmartOrderData extends Model
{
    use HasFactory;

    protected $table = 'walmart_order_data';

    protected $fillable = [
        'sku', 'po_number', 'order_number', 'order_date', 'ship_by', 'delivery_date',
        'customer_name', 'customer_address', 'qty', 'item_cost', 'shipping_cost',
        'tax', 'status', 'carrier', 'tracking_number', 'tracking_url',
        'item_description', 'shipping_method', 'fulfillment_entity'
    ];

    protected $casts = [
        'order_date' => 'date',
        'ship_by' => 'date',
        'delivery_date' => 'date',
        'qty' => 'integer',
        'item_cost' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'tax' => 'decimal:2',
    ];
}

