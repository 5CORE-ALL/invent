<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnSeaTransit extends Model
{
    protected $table = 'on_sea_transit';
    
    protected $fillable = [
        'container_sl_no', 'bl_check', 'bl_link', 'isf', 'etd', 'port_arrival',
        'eta_date_ohio', 'status', 'isf_usa_agent', 'duty_calcu',
        'invoice_send_to_dominic', 'arrival_notice_email', 'remarks'
    ];
}
