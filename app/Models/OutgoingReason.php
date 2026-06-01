<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutgoingReason extends Model
{
    protected $table = 'outgoing_reasons';

    protected $fillable = ['name', 'sort_order'];

    protected $casts = [
        'sort_order' => 'integer',
    ];
}
