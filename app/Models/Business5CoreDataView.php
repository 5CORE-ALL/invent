<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Business5CoreDataView extends Model
{
    protected $table = 'business5core_data_views';
    protected $fillable = ['sku', 'value'];
    protected $casts = [
        'value' => 'array',
    ];
}
