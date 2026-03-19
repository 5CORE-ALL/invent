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

    public const MARKETPLACES = ['amazon', 'temu', 'reverb', 'wayfair', 'shopify_pls', 'doba', 'ebay1', 'ebay2', 'ebay3'];
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PENDING = 'pending';
}
