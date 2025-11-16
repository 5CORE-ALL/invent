<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AccountHealthMaster extends Model
{
    use HasFactory;

    protected $table = 'account_health_master';

    protected $fillable = [
        'channel',
        'l30_sales',
        'l30_orders',
        'account_health_links',
        'remarks',
        'pre_fulfillment_cancel_rate',
        'odr',
        'fulfillment_rate',
        'late_shipment_rate',
        'valid_tracking_rate',
        'on_time_delivery_rate',
        'negative_feedback',
        'positive_feedback',
        'guarantee_claims',
        'refund_rate',
        'avg_processing_time',
        'message_time',
        'overall',
        'report_date',      
        'created_by',       
    ];

    public function channel()
    {
        return $this->belongsTo(ChannelMaster::class, 'channel');
    }
}

