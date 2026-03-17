<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalanceTransferPreference extends Model
{
    protected $table = 'stock_balance_transfer_preferences';

    protected $fillable = [
        'user_id',
        'to_sku',
        'from_sku',
        'ratio',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
