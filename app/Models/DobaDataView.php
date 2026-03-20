<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DobaDataView extends Model
{
    protected $table = 'doba_data_view';
    protected $fillable = ['sku', 'value'];
    protected $casts = [
        'value' => 'array',
    ];
}