<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Marketplace extends Model
{
    protected $fillable = [
        'name',
        'code',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function imageMarketplaceMaps(): HasMany
    {
        return $this->hasMany(ImageMarketplaceMap::class, 'marketplace_id');
    }
}
