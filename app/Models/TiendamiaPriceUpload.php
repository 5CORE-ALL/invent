<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TiendamiaPriceUpload extends Model
{
    use HasFactory;

    protected $table = 'tiendamia_price_uploads';

    protected $fillable = [
        'upload_batch_id',
        'source_filename',
        'row_index',
        'offer_sku',
        'product_sku',
        'category_code',
        'category_label',
        'brand',
        'product',
        'offer_state',
        'price',
        'original_price',
        'quantity',
        'alert_threshold',
        'logistic_class',
        'activated',
        'available_start_date',
        'available_end_date',
        'discount_price',
        'discount_start_date',
        'discount_end_date',
        'ean',
        'inactivity_reason',
        'fulfillment_center_code',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'quantity' => 'integer',
        'row_index' => 'integer',
    ];
}
