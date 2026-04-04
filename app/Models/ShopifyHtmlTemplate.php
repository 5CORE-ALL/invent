<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopifyHtmlTemplate extends Model
{
    protected $table = 'shopify_html_templates';

    protected $fillable = [
        'user_id',
        'sku',
        'marketplace',
        'template_name',
        'html_content',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
