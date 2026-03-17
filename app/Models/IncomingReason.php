<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomingReason extends Model
{
    protected $table = 'incoming_reasons';

    protected $fillable = ['name', 'sort_order'];

    protected $casts = [
        'sort_order' => 'integer',
    ];
}
