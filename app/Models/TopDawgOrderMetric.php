<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopDawgOrderMetric extends Model
{
    use HasFactory;

    protected $table = 'topdawg_order_metrics';

    protected $fillable = [
        'order_id',
        'order_number',
        'order_date',
        'order_paid_at',
        'status',
        'amount',
        'display_sku',
        'sku',
        'product_id',
        'display_title',
        'quantity',
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

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if (empty($model->order_id) && ! empty($model->order_number)) {
                $model->order_id = (string) $model->order_number;
            }
            if (empty($model->order_number) && ! empty($model->order_id)) {
                $model->order_number = (string) $model->order_id;
            }
        });
    }
}
