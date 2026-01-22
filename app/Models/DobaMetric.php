<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DobaMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'item_id',
        'quantity_l30',
        'quantity_l60',
        'quantity_l7',
        'quantity_l7_prev',
        'anticipated_income',
        'self_pick_price',
        'msrp',
        'map',
        'impressions',
        'clicks',
        'order_count_l30',
        'order_count_l60',
        'order_count_l7',
        'order_count_l7_prev',
    ];
}
