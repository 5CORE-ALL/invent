<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaireDataView extends Model
{
    protected $table = 'faire_data_views';
    protected $fillable = ['sku', 'value'];
    protected $casts = [
        'value' => 'array',
    ];
}
