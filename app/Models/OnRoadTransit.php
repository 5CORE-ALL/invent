<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnRoadTransit extends Model
{
    protected $table = 'on_road_transit';

    protected $fillable = [
        'container_sl_no',
        'supplier_pay_against_bl',
        'cha_china_pay',
        'duty',
        'freight_due',
        'fwdr_usa_due',
        'cbp_form_7501',
        'transport_rfq',
        'freight_hold',
        'customs_hold',
        'pay_usa_cha',
        'inform_sam',
        'date_of_cont_return',
        'inv_verification',
        'qc_verification',
        'claims_if_any',
        'status',
    ];
}
