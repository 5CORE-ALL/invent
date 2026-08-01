<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchasingPowerSale extends Model
{
    protected $table = 'purchasing_power_sales';

    protected $fillable = [
        'order_number', 'date_created', 'quantity', 'product_name',
        'status', 'amount', 'currency', 'product_sku', 'offer_sku',
        'brand', 'category_code', 'category_label', 'unit_price',
        'shipping_price', 'commission_rule_name', 'commission_excl_tax',
        'commission_incl_tax', 'amount_transferred', 'shipping_company',
        'tracking_number', 'tracking_url', 'customer_first_name',
        'customer_last_name', 'customer_city', 'customer_state',
        'customer_country', 'order_id',
        'shopify_order_id', 'pushed_to_shopify_at', 'import_status', 'raw_payload',
    ];

    protected $casts = [
        'date_created' => 'datetime',
        'pushed_to_shopify_at' => 'datetime',
        'raw_payload' => 'array',
    ];
}
