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
        'from_items',
        'action',
        'user_id',
    ];

    protected $casts = [
        'from_items' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
