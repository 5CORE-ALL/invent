<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdsCategory extends Model
{
    protected $table = 'ads_categories';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
