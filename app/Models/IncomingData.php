<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncomingData extends Model
{
    use HasFactory;

     protected $table = 'incoming_data'; 

    protected $fillable = [
        'sku',
        'quantity',
        'warehouse_id',
        'reason',
        'approved_by',
        'approved_at',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function warehouseTo()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse');
    }
}
