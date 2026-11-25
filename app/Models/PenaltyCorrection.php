<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenaltyCorrection extends Model
{
    protected $fillable = ['user_id', 'penalty_entry_id', 'note'];
}
