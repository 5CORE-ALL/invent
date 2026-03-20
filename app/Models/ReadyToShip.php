<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // ✅ Correct import

class ReadyToShip extends Model
{
    use HasFactory, SoftDeletes; // ✅ Add trait here

    protected $table = 'ready_to_ship';

    protected $fillable = [
        'parent',
        'sku',
        'qty',
        'rate',
        'supplier',
        'supplier_sku',
        'rec_qty',
        'cbm',
        'area',
        'shipd_cbm_in_cont',
        'payment',
        'payment_confirmation',
        'model_number',
        'photo_mail_send',
        'followup_delivery',
        'packing_list',
        'container_rfq',
        'quote_result',
        'pay_term',
        'transit_inv_status',
        'auth_user',
    ];

    protected $dates = ['deleted_at']; 
    
}
