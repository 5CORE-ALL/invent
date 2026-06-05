<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RfqForm extends Model
{
    protected $fillable = [
        'name',
        'title',
        'slug',
        'main_image',
        'subtitle',
        'fields',
        'dimension_inner',
        'product_dimension',
        'package_dimension',
        'created_by',
        'updated_by',
        'linked_skus',
    ];

    protected $casts = [
        'fields' => 'array',
        'linked_skus' => 'array',
    ];

}

