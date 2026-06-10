<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NeweggOrderItem extends Model
{
    use HasFactory;

    protected $table = 'newegg_order_items';

    protected $fillable = [
        'order_number',
        'seller_part_number',
        'newegg_item_number',
        'mfr_part_number',
        'upc_code',
        'description',
        'ordered_qty',
        'shipped_qty',
        'unit_price',
        'extend_unit_price',
        'extend_shipping_charge',
        'status',
        'status_description',
        'raw_json',
    ];

    protected $casts = [
        'unit_price'             => 'decimal:2',
        'extend_unit_price'      => 'decimal:2',
        'extend_shipping_charge' => 'decimal:2',
        'raw_json'               => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(NeweggOrder::class, 'order_number', 'order_number');
    }
}
