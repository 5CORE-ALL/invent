<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'code', 'name', 'status', 'fields',
    ];
    
    protected $casts = [
        'fields' => 'array',
    ];
}
