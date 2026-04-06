<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MfrgProgress extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'mfrg_progress';

    protected $fillable = [
        'parent',
        'sku',
        'qty',
        'rate',
        'supplier',
        'supplier_sku',
        'advance_amt',
        'adv_date',
        'pay_conf_date',
        'del_date',
        'delivery_date',
        'o_links',
        'value',
        'payment_pending',
        'photo_packing',
        'photo_int_sale',
        'total_cbm',
        'barcode_sku',
        'artwork_manual_book',
        'notes',
        'ready_to_ship',
        'pkg_inst',
        'u_manual',
        'compliance'
    ];

    protected $casts = [
        'adv_date' => 'date',
        'pay_conf_date' => 'date',
        'del_date' => 'date',
        'delivery_date' => 'date',
    ];

    public $timestamps = true;
}
