<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentVerificationHistory extends Model
{
    protected $table = 'payment_verification_histories';

    protected $fillable = [
        'channel_key',
        'channel',
        'amount',
        'recorded_by',
    ];
}
