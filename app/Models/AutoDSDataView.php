<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoDSDataView extends Model
{
    protected $table = 'auto_ds_data_views';
    protected $fillable = ['sku', 'value'];
    protected $casts = [
        'value' => 'array',
    ];
}
