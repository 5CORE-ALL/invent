<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomingReturnAuditPoint extends Model
{
    protected $table = 'incoming_return_audit_points';

    protected $fillable = ['description', 'sort_order'];
}
