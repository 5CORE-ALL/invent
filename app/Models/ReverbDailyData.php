<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReverbDailyData extends Model
{
    use HasFactory;

    protected $table = 'reverb_daily_data';

    protected $fillable = [
        'order_number',
        'order_date',
        'period',
        'status',
        'sku',
        'display_sku',
        'title',
        'quantity',
        'unit_price',
        'product_subtotal',
        'amount',
        'shipping_amount',
        'tax_amount',
        'tax_rate',
        'selling_fee',
        'bump_fee',
        'direct_checkout_fee',
        'payout_amount',
        'buyer_id',
        'buyer_name',
        'buyer_email',
        'shipping_address',
        'shipping_city',
        'shipping_state',
        'shipping_country',
        'shipping_postal_code',
        'buyer_phone',
        'payment_method',
        'order_type',
        'order_source',
        'shipping_method',
        'shipment_status',
        'order_bundle_id',
        'product_id',
        'remaining_inventory',
        'local_pickup',
        'paid_at',
        'shipped_at',
        'created_at_api',
    ];

    protected $casts = [
        'order_date' => 'date',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'created_at_api' => 'datetime',
        'unit_price' => 'decimal:2',
        'product_subtotal' => 'decimal:2',
        'amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'selling_fee' => 'decimal:2',
        'bump_fee' => 'decimal:2',
        'direct_checkout_fee' => 'decimal:2',
        'payout_amount' => 'decimal:2',
        'local_pickup' => 'boolean',
    ];
}
