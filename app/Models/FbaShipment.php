<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FbaShipment extends Model
{
    use HasFactory;

    protected $table = 'fba_shipments';

    protected $fillable = [
        'shipment_id',
        'sku',
        'shipment_status',
        'status_code',
        'shipment_name',
        'destination_fc',
        'quantity_shipped',
        'quantity_received',
        'shipped_date',
        'dispatch_date',
        'fba_send',
        'listed',
        'live',
        'done',
        'last_api_sync'
    ];

    protected $casts = [
        'fba_send' => 'boolean',
        'listed' => 'boolean',
        'live' => 'boolean',
        'done' => 'boolean',
        'quantity_shipped' => 'integer',
        'quantity_received' => 'integer',
        'last_api_sync' => 'datetime',
        'shipped_date' => 'date',
        'dispatch_date' => 'date',
    ];
}
