<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NeweggOrder extends Model
{
    use HasFactory;

    protected $table = 'newegg_orders';

    protected $fillable = [
        'seller_id',
        'order_number',
        'seller_order_number',
        'invoice_number',
        'order_downloaded',
        'order_date',
        'auto_void_time',
        'order_status',
        'order_status_description',
        'customer_name',
        'customer_phone_number',
        'customer_email_address',
        'on_time_ship_due_date',
        'deliver_due_date',
        'ship_to_first_name',
        'ship_to_last_name',
        'ship_to_company',
        'ship_to_address1',
        'ship_to_address2',
        'ship_to_city_name',
        'ship_to_state_code',
        'ship_to_zip_code',
        'ship_to_country_code',
        'ship_service',
        'signature_required',
        'currency_code',
        'order_item_amount',
        'shipping_amount',
        'discount_amount',
        'refund_amount',
        'sales_tax',
        'order_total_amount',
        'order_qty',
        'is_auto_void',
        'sales_channel',
        'fulfillment_option',
        'raw_json',
    ];

    protected $casts = [
        'order_downloaded'   => 'boolean',
        'signature_required' => 'boolean',
        'is_auto_void'       => 'boolean',
        'order_date'         => 'datetime',
        'auto_void_time'     => 'datetime',
        'on_time_ship_due_date' => 'date',
        'deliver_due_date'   => 'date',
        'order_item_amount'  => 'decimal:2',
        'shipping_amount'    => 'decimal:2',
        'discount_amount'    => 'decimal:2',
        'refund_amount'      => 'decimal:2',
        'sales_tax'          => 'decimal:2',
        'order_total_amount' => 'decimal:2',
        'raw_json'           => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(NeweggOrderItem::class, 'order_number', 'order_number');
    }
}
