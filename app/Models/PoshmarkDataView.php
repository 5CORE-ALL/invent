<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PoshmarkDataView extends Model
{
    protected $table = 'poshmark_data_views';
    protected $fillable = ['sku', 'value'];
    protected $casts = [
        'value' => 'array',
    ];
}
