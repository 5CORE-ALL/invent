<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SheinSheetData extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'price',
        'roi',
        'l30',
        'buy_link',
        's_link',
        'views_clicks',
        'lmp',
        'link1',
        'link2',
        'link3',
        'link4',
        'link5',
        'shopify_sheinl30',
        'shopify_sheinl60',
        'shopify_price',
    ];
}

