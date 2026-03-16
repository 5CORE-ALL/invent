<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EbayOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'ebay_order_id',
        'item_id',
        'sku',
        'quantity',
        'price',
        'currency',
        'raw_data',
    ];

    public function order()
    {
        return $this->belongsTo(EbayOrder::class);
    }
}
