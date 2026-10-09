<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoAdsMasterCreator extends Model
{
    public $timestamps = false;

    protected $table = 'video_ads_master_creators';

    protected $fillable = [
        'video_ads_master_id',
        'user_id',
        'created_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
