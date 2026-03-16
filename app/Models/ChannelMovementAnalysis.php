<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelMovementAnalysis extends Model
{
    use HasFactory;

    protected $table = 'channel_movement_analysis';

    protected $fillable = [
        'channel_name',
        'month',
        'system_data',
        'site_amount',
        'receipt_amount',
        'expense_percentage',
        'ours_percentage',
    ];
}
