<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TiktokOrderMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_date',
        'status',
        'amount',
        'display_sku',
        'sku',
        'quantity',
        'order_number',
    ];
}
