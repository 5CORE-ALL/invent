<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopDawgDataView extends Model
{
    protected $table = 'topdawg_data_views';

    protected $fillable = ['sku', 'value'];

    protected $casts = [
        'value' => 'array',
    ];
}
