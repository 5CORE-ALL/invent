<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplacePushLog extends Model
{
    use HasFactory;

    protected $table = 'marketplace_push_logs';

    protected $fillable = [
        'sku',
        'marketplace',
        'status',
        'error_message',
        'response_data',
        'user_id',
    ];

    protected $casts = [
        'response_data' => 'array',
    ];

    public const MARKETPLACES = [
        'amazon', 'temu', 'temu2', 'reverb', 'wayfair', 'walmart',
        'shopify_main', 'shopify_pls', 'shopify_b5c', 'doba',
        'ebay1', 'ebay2', 'ebay3', 'macy', 'faire',
        'bestbuy', 'newegg', 'shein', 'aliexpress', 'alibaba',
        'purchasing_power', 'topdawg', 'tiktok', 'tiktok2',
    ];
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PENDING = 'pending';
}
