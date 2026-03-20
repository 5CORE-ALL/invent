<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EbayDataView extends Model
{
    protected $table = 'ebay_data_view';
    protected $fillable = ['sku', 'value'];
    protected $casts = [
        'value' => 'array',
    ];
}