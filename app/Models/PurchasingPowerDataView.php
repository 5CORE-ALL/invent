<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchasingPowerDataView extends Model
{
    use HasFactory;

    protected $table = 'purchasing_power_data_views';

    protected $fillable = ['sku', 'value'];

    protected $casts = ['value' => 'array'];
}
