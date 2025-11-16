<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MfrgProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent',
        'sku',
        'qty',
        'rate',
        'supplier',
        'advance_amt',
        'adv_date',
        'pay_conf_date',
        'del_date',
        'o_links',
        'value',
        'payment_pending',
        'photo_packing',
        'photo_int_sale',
        'total_cbm',
        'barcode_sku',
        'artwork_manual_book',
        'notes',
        'ready_to_ship'
    ];

    protected $casts = [
        'adv_date' => 'date',
        'pay_conf_date' => 'date',
        'del_date' => 'date',
    ];

    public $timestamps = true;
}
