<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MacyDataView extends Model
{
    use HasFactory;
    protected $table = 'macy_data_view';
    protected $fillable = ['sku', 'value'];
    protected $casts = [
        'value' => 'array',
    ];
}
