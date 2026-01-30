<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TikTokDataView extends Model
{
    use HasFactory;
    
    protected $table = 'tiktok_data_view';
    
    protected $fillable = ['sku', 'value'];
    
    protected $casts = [
        'value' => 'array',
    ];
}
