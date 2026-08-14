<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ebay2Metric extends Model
{
    use HasFactory;

    protected $table = 'ebay_2_metrics';
    
    protected $fillable = [
        'item_id',
        'sku',
        'ebay_title',
        'ebay_link',
        'ebay_l30',
        'ebay_l60',
        'ebay_l7',
        'ebay_price',
        'views',
        'l7_views',
        'l1_views',
        'report_range',
        'ebay_stock',
        'bullet_points',
    ];

    /** MM alias: product_id ↔ item_id */
    public function getProductIdAttribute($value = null)
    {
        return $this->attributes['item_id'] ?? $value;
    }

    public function setProductIdAttribute($value): void
    {
        $this->attributes['item_id'] = $value;
    }

    /** MM alias: product_name ↔ ebay_title */
    public function getProductNameAttribute($value = null)
    {
        return $this->attributes['ebay_title'] ?? $value;
    }

    public function setProductNameAttribute($value): void
    {
        $this->attributes['ebay_title'] = $value;
    }

    /** MM alias: price ↔ ebay_price */
    public function getPriceAttribute($value = null)
    {
        return $this->attributes['ebay_price'] ?? $value;
    }

    public function setPriceAttribute($value): void
    {
        $this->attributes['ebay_price'] = $value;
    }
}
