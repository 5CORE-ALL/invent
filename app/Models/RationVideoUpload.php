<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RationVideoUpload extends Model
{
    protected $fillable = ['ratio', 'sku', 'original_name', 'path', 'size', 'mime', 'uploaded_by'];

    protected $casts = [
        'size' => 'integer',
    ];
}
