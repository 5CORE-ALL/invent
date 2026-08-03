<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShortTitle extends Model
{
    protected $table = 'short_titles';

    protected $fillable = [
        'sku',
        'short_title',
        'source_amazon_title',
    ];
}
