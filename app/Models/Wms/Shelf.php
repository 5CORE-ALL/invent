<?php

namespace App\Models\Wms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shelf extends Model
{
    protected $table = 'shelves';

    protected $fillable = ['rack_id', 'name', 'code'];

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class, 'rack_id');
    }

    public function bins(): HasMany
    {
        return $this->hasMany(Bin::class, 'shelf_id');
    }
}
