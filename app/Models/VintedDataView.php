<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VintedDataView extends Model
{
    protected $table = 'vinted_data_views';

    protected $fillable = ['sku', 'value'];

    protected $casts = [
        'value' => 'array',
    ];
}
