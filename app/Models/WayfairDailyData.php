<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WayfairDailyData extends Model
{
    use HasFactory;

    protected $table = 'wayfair_daily_data';

    protected $fillable = [
        'po_number',
        'po_date',
        'period',
        'status',
        'sku',
        'quantity',
        'unit_price',
        'total_price',
        'estimated_ship_date',
        'customer_name',
        'customer_address1',
        'customer_address2',
        'customer_city',
        'customer_state',
        'customer_postal_code',
        'customer_country',
        'customer_phone',
        'ship_speed',
        'carrier_code',
        'warehouse_id',
        'warehouse_name',
        'event_id',
        'event_type',
        'event_name',
        'packing_slip_url',
    ];

    protected $casts = [
        'po_date' => 'date',
        'estimated_ship_date' => 'date',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];
}
