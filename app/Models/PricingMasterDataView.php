<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingMasterDataView extends Model
{
    protected $table = 'pricing_master_data_view';

    protected $fillable = [
        'sku',
        'marketplace',
        'sprice',
        'amazon_margin',
        'sgpft',
        'spft',
        'sroi',
        'avg_pft',
    ];

    protected $casts = [
        'sprice' => 'decimal:2',
        'amazon_margin' => 'decimal:4',
        'sgpft' => 'decimal:2',
        'spft' => 'decimal:2',
        'sroi' => 'decimal:2',
        'avg_pft' => 'decimal:2',
    ];
}
