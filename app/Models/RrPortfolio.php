<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RrPortfolio extends Model
{
    protected $table = 'rr_portfolios';

    protected $fillable = [
        'html_content',
        'original_filename',
        'source_format',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(RrPortfolioUser::class, 'rr_portfolio_id');
    }
}
