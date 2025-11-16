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
        'anticipated_income',
        'impressions',
        'clicks',
    ];
}
