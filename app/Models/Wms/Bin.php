<?php

namespace App\Models\Wms;

use App\Models\Inventory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bin extends Model
{
    protected $table = 'bins';

    protected $fillable = ['shelf_id', 'name', 'code', 'capacity', 'full_location_code'];

    protected $casts = [
        'capacity' => 'integer',
    ];

    public function shelf(): BelongsTo
    {
        return $this->belongsTo(Shelf::class, 'shelf_id');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'bin_id');
    }
}
