<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReverbDataView extends Model
{
    use HasFactory;

    protected $table = 'reverb_data_view';

    protected $fillable = ['sku', 'value'];

    protected $casts = [
        'value' => 'array',
    ];
}
