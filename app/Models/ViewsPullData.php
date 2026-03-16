<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ViewsPullData extends Model
{
    use HasFactory;

    protected $table = 'views_pull_data';

    protected $fillable = [
        'sku',
        'parent',
        'temu',
        'wayfair',
        'tiktok',
        'walmart',
        'aliexpress',
    ];
}
