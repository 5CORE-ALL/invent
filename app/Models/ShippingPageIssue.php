<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingPageIssue extends Model
{
    protected $table = 'shipping_page_issues';

    protected $fillable = [
        'o_date',
        'o_number',
        'channel',
        'sku',
        'pin_code',
        'zone',
        'state',
        'amount_received',
        'amount_paid',
        'action_taken',
    ];

    protected $casts = [
        'o_date' => 'date',
    ];
}
