<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmazonListingStatus extends Model
{
    use HasFactory;

    protected $table = 'amazon_listing_statuses';

    protected $fillable = ['sku', 'value'];

    protected $casts = [
        'value' => 'array', 
    ];
}
