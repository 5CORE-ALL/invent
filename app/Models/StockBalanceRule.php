<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalanceRule extends Model
{
    protected $table = 'stock_balance_rules';

    protected $fillable = [
        'to_sku',
        'from_sku',
        'ratio',
        'from_qty',
        'action',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
