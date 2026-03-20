<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemuDailyData extends Model
{
    use HasFactory;

    protected $table = 'temu_daily_data';

    protected $fillable = [
        'order_id',
        'order_status',
        'fulfillment_mode',
        'logistics_service_suggestion',
        'order_item_id',
        'order_item_status',
        'product_name_by_customer_order',
        'product_name',
        'variation',
        'contribution_sku',
        'sku_id',
        'quantity_purchased',
        'quantity_shipped',
        'quantity_to_ship',
        'recipient_name',
        'recipient_first_name',
        'recipient_last_name',
        'recipient_phone_number',
        'ship_address_1',
        'ship_address_2',
        'ship_address_3',
        'district',
        'ship_city',
        'ship_state',
        'ship_postal_code',
        'ship_country',
        'purchase_date',
        'latest_shipping_time',
        'latest_delivery_time',
        'iphone_serial_number',
        'virtual_email',
        'activity_goods_base_price',
        'base_price_total',
        'tracking_number',
        'carrier',
        'order_settlement_status',
        'keep_proof_of_shipment_before_delivery',
    ];

    protected $casts = [
        'purchase_date' => 'datetime',
        'latest_shipping_time' => 'datetime',
        'latest_delivery_time' => 'datetime',
        'quantity_purchased' => 'integer',
        'quantity_shipped' => 'integer',
        'quantity_to_ship' => 'integer',
        'activity_goods_base_price' => 'decimal:2',
        'base_price_total' => 'decimal:2',
    ];
}
