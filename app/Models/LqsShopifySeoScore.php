<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LqsShopifySeoScore extends Model
{
    protected $table = 'lqs_shopify_seo_scores';

    protected $fillable = [
        'shopify_product_id',
        'handle',
        'title',
        'seo_title',
        'seo_description',
        'focus_keyphrase',
        'skus',
        'seo_score',
        'seo_rating',
        'readability_score',
        'readability_rating',
        'findings',
        'yoast_payload',
        'source',
        'synced_at',
    ];

    protected $casts = [
        'skus' => 'array',
        'findings' => 'array',
        'yoast_payload' => 'array',
        'seo_score' => 'integer',
        'readability_score' => 'integer',
        'synced_at' => 'datetime',
    ];
};
