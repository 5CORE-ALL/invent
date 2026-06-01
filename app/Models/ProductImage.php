<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sku',
        'image_path',
        'original_name',
        'file_size',
        'mime_type',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'file_size'  => 'integer',
    ];

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }
}
