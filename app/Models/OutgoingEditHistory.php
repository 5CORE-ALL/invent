<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutgoingEditHistory extends Model
{
    protected $table = 'outgoing_edit_history';

    public $timestamps = false;

    protected $fillable = [
        'inventory_id',
        'sku',
        'field',
        'old_value',
        'new_value',
        'updated_by',
        'updated_at',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
    ];

    public function inventory()
    {
        return $this->belongsTo(Inventory::class, 'inventory_id');
    }
}
