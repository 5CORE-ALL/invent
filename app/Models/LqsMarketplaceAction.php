<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LqsMarketplaceAction extends Model
{
    protected $table = 'lqs_marketplace_actions';

    protected $fillable = [
        'marketplace',
        'sku',
        'action',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
