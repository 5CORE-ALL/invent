<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EbayOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'ebay_order_id',
        'order_date',
        'status',
        'total_amount',
        'currency',
        'period',
        'raw_data',
    ];

    public function items()
    {
        return $this->hasMany(EbayOrderItem::class);
    }
}
