<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MercariDailyData extends Model
{
    use HasFactory;

    protected $table = 'mercari_daily_data';

    protected $fillable = [
        'item_id',
        'sold_date',
        'canceled_date',
        'completed_date',
        'item_title',
        'order_status',
        'shipped_to_state',
        'shipped_from_state',
        'item_price',
        'buyer_shipping_fee',
        'seller_shipping_fee',
        'mercari_selling_fee',
        'payment_processing_fee_charged_to_seller',
        'shipping_adjustment_fee',
        'penalty_fee',
        'net_seller_proceeds',
        'sales_tax_charged_to_buyer',
        'merchant_fees_charged_to_buyer',
        'service_fee_charged_to_buyer',
        'buyer_protection_charged_to_buyer',
        'payment_processing_fee_charged_to_buyer',
    ];

    protected $casts = [
        'sold_date' => 'datetime',
        'canceled_date' => 'datetime',
        'completed_date' => 'datetime',
        'item_price' => 'decimal:2',
        'buyer_shipping_fee' => 'decimal:2',
        'seller_shipping_fee' => 'decimal:2',
        'mercari_selling_fee' => 'decimal:2',
        'payment_processing_fee_charged_to_seller' => 'decimal:2',
        'shipping_adjustment_fee' => 'decimal:2',
        'penalty_fee' => 'decimal:2',
        'net_seller_proceeds' => 'decimal:2',
        'sales_tax_charged_to_buyer' => 'decimal:2',
        'merchant_fees_charged_to_buyer' => 'decimal:2',
        'service_fee_charged_to_buyer' => 'decimal:2',
        'buyer_protection_charged_to_buyer' => 'decimal:2',
        'payment_processing_fee_charged_to_buyer' => 'decimal:2',
    ];
}








