<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EbayCompetitorItem extends Model
{
    use HasFactory;

    protected $table = 'ebay_competitor_items';

    protected $fillable = [
        'marketplace',
        'search_query',
        'item_id',
        'link',
        'title',
        'price',
        'condition',
        'seller_name',
        'seller_rating',
        'position',
        'image',
        'shipping_cost',
        'location',
    ];
}
