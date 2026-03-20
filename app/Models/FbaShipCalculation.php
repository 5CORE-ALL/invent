<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FbaShipCalculation extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'fba_sku',
        'fulfillment_fee',
        'fba_fee_manual',
        'send_cost',
        'in_charges',
        'fba_ship_calculation',
        'calculation_source',
    ];

    protected $casts = [
        'fulfillment_fee' => 'decimal:2',
        'fba_fee_manual' => 'decimal:2',
        'send_cost' => 'decimal:2',
        'in_charges' => 'decimal:2',
        'fba_ship_calculation' => 'decimal:2',
    ];
}
