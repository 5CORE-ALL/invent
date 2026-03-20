<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NeweggB2BDataView extends Model
{
    protected $table = 'newegg_b2b_data_views';
    protected $fillable = ['sku', 'value'];
    protected $casts = [
        'value' => 'array',
    ];
}
