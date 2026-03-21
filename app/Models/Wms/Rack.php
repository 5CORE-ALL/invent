<?php

namespace App\Models\Wms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rack extends Model
{
    protected $table = 'racks';

    protected $fillable = ['zone_id', 'name', 'code', 'pick_priority'];

    protected $casts = [
        'pick_priority' => 'integer',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function shelves(): HasMany
    {
        return $this->hasMany(Shelf::class, 'rack_id');
    }
}
