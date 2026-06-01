<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnSeaTransitDetailsHistory extends Model
{
    use HasFactory;
    
    protected $table = 'on_sea_transit_details_history';
    
    protected $fillable = [
        'on_sea_transit_id',
        'container_sl_no',
        'user_name',
        'old_value',
        'new_value',
        'changed_at'
    ];
    
    protected $casts = [
        'changed_at' => 'datetime',
    ];
    
    public function onSeaTransit()
    {
        return $this->belongsTo(OnSeaTransit::class, 'on_sea_transit_id');
    }
}
