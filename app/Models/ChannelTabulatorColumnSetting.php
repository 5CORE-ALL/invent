<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelTabulatorColumnSetting extends Model
{
    protected $table = 'channel_tabulator_column_settings';

    protected $fillable = [
        'channel_name',
        'visibility',
        'column_order',
    ];

    protected $casts = [
        'visibility' => 'array',
        'column_order' => 'array',
    ];
}
