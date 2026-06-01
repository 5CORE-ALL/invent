<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundReason extends Model
{
    protected $table = 'refund_reasons';

    protected $fillable = ['name', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];
}
