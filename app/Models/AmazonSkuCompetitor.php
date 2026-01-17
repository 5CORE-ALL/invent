<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmazonSkuCompetitor extends Model
{
    use HasFactory;

    protected $table = 'amazon_sku_competitors';

    protected $fillable = [
        'sku',
        'asin',
        'marketplace',
        'product_title',
        'product_link',
        'price',
    ];
}
