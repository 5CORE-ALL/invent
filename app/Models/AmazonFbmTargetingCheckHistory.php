<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmazonFbmTargetingCheckHistory extends Model
{
    protected $table = 'amazon_fbm_targeting_check_histories';

    public $timestamps = false;

    protected $fillable = [
        'sku',
        'type',
        'campaign',
        'issue',
        'remark',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
