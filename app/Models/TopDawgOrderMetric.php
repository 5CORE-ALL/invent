<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopDawgOrderMetric extends Model
{
    use HasFactory;

    protected $table = 'topdawg_order_metrics';

    protected $fillable = [
        'order_date',
        'order_paid_at',
        'status',
        'amount',
        'display_sku',
        'sku',
        'quantity',
        'order_number',
    ];

    protected $casts = [
        'order_paid_at' => 'datetime',
    ];
}
