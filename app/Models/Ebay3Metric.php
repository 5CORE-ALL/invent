<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ebay3Metric extends Model
{
    use HasFactory;

    protected $table = 'ebay_3_metrics';

    protected $fillable = [
        'item_id',
        'sku',
        'ebay_l30',
        'ebay_l60',
        'ebay_price',
        'views',
    ];    
}
