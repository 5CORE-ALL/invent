<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmazonFbmTargetingCheck extends Model
{
    protected $table = 'amazon_fbm_targeting_checks';

    protected $fillable = [
        'sku',
        'type',
        'checked',
        'campaign',
        'issue',
        'remark',
        'user_id',
    ];

    protected $casts = [
        'checked' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
