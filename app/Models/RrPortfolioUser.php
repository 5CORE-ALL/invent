<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RrPortfolioUser extends Model
{
    protected $table = 'rr_portfolio_user';

    protected $fillable = [
        'rr_portfolio_id',
        'user_id',
        'fits',
    ];

    protected $casts = [
        'fits' => 'boolean',
    ];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(RrPortfolio::class, 'rr_portfolio_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
