<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One GOFO exception (damaged / oversize / discarded parcel) tracked by mail date + tracking number.
 */
class GofoException extends Model
{
    protected $table = 'gofo_exceptions';

    protected $guarded = ['id'];

    protected $casts = [
        'mail_date' => 'date:Y-m-d',
        'quantity' => 'integer',
        'sku_price' => 'float',
        'loss_amount' => 'float',
        'loss_manual' => 'boolean',
        'attachments' => 'array',
    ];
}
