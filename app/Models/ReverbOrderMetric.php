<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReverbOrderMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_date',
        'order_paid_at',
        'status',
        'amount',
        'display_sku',
        'display_title',
        'sku',
        'product_id',
        'quantity',
        'order_number',
        'shopify_order_id',
        'pushed_to_shopify_at',
        'import_status',
        'raw_payload',
    ];

    protected $casts = [
        'order_paid_at' => 'datetime',
        'pushed_to_shopify_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    /**
     * Reverb uses order_number as the API / UI order id (they are the same).
     * Legacy rows may only have order_number filled; MM rows set both.
     */
    public function orderRef(): string
    {
        $id = trim((string) ($this->order_id ?? ''));
        if ($id !== '') {
            return $id;
        }

        return trim((string) ($this->order_number ?? ''));
    }
}
