<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListingLqs extends Model
{
    use HasFactory;

    protected $table = 'listing_lqs';

    protected $fillable = [
        'sku',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];
}
