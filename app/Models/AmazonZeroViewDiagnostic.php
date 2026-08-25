<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonZeroViewDiagnostic extends Model
{
    protected $table = 'amazon_zero_view_diagnostics';

    protected $fillable = [
        'sku',
        'asin',
        'marketplace',
        'account',
        'product_name',
        'inventory',
        'listing_status',
        'suppression_status',
        'buyable_status',
        'price',
        'featured_offer_status',
        'l7_views',
        'l30_views',
        'l7_sessions',
        'l30_sessions',
        'search_index_status',
        'category_status',
        'browse_node_status',
        'main_image_status',
        'title_status',
        'diagnostic_status',
        'problem',
        'recommended_action',
        'diagnostic_data',
        'run_status',
        'api_errors',
        'started_at',
        'completed_at',
        'duration_ms',
        'last_checked_at',
    ];

    protected $casts = [
        'inventory' => 'decimal:2',
        'price' => 'decimal:2',
        'l7_views' => 'integer',
        'l30_views' => 'integer',
        'l7_sessions' => 'integer',
        'l30_sessions' => 'integer',
        'diagnostic_data' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];
}
