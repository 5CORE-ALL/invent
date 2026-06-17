<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundRecordEditHistory extends Model
{
    protected $table = 'refund_record_edit_history';

    public $timestamps = false;

    protected $fillable = [
        'refund_record_id', 'sku', 'field', 'old_value', 'new_value', 'updated_by', 'updated_at',
    ];

    protected $casts = ['updated_at' => 'datetime'];
}
