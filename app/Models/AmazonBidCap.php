<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmazonBidCap extends Model
{
    use HasFactory;

    protected $table = 'amazon_bid_caps';

    protected $fillable = [
        'sku',
        'bid_cap',
        'user_id',
        'last_updated_at'
    ];

    protected $casts = [
        'bid_cap' => 'decimal:2',
        'last_updated_at' => 'datetime'
    ];

    /**
     * Get the user who last updated this bid cap
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
