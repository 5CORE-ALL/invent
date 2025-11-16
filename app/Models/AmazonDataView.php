<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonDataView extends Model
{
    protected $table = 'amazon_data_view';

    protected $fillable = [
        'sku',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];
}