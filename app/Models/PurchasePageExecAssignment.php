<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchasePageExecAssignment extends Model
{
    protected $fillable = [
        'page_key',
        'assigned_exec',
    ];
}
