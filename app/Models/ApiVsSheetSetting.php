<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiVsSheetSetting extends Model
{
    protected $table = 'api_vs_sheet_settings';

    protected $fillable = [
        'channel_id',
        'download_source',
        'upload_source',
        'price_api_2w',
        'price_api_2w_sheet_link',
        'updated_by',
    ];

    public function channel()
    {
        return $this->belongsTo(ChannelMaster::class, 'channel_id');
    }
}
