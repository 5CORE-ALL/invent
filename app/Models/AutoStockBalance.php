<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoStockBalance extends Model
{
    use HasFactory;

    protected $table = 'auto_stock_balance';

    // Fillable fields for mass assignment
    protected $fillable = [
        'from_sku',
        'from_parent_name',
        'from_available_qty',
        'from_dil_percent',
        'from_adjust_qty',
        'from_adj_dil',
        'to_sku',
        'to_parent_name',
        'to_available_qty',
        'to_dil_percent',
        'to_adjust_qty',
        'to_adj_dil',
        'added_qty',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
