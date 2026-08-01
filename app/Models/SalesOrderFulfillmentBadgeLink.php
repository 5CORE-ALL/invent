<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderFulfillmentBadgeLink extends Model
{
    protected $table = 'sales_order_fulfillment_badge_links';

    protected $fillable = [
        'badge_key',
        'link',
    ];
}
