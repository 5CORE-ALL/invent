<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceReview extends Model
{
    public const SHOPIFY_PENDING = 'pending';

    public const SHOPIFY_PUSHED = 'pushed';

    public const SHOPIFY_FAILED = 'failed';

    public const SHOPIFY_SKIPPED = 'skipped';

    protected $table = 'marketplace_reviews';

    protected $fillable = [
        'sku', 'marketplace', 'external_product_id', 'external_review_id', 'rating', 'title', 'body',
        'reviewer_name', 'verified', 'reviewed_at', 'review_url', 'images', 'source', 'fingerprint',
        'shopify_product_id', 'shopify_status', 'shopify_pushed_at', 'shopify_error', 'shopify_attempts',
    ];

    protected $casts = [
        'rating' => 'integer',
        'verified' => 'boolean',
        'reviewed_at' => 'datetime',
        'images' => 'array',
        'shopify_pushed_at' => 'datetime',
        'shopify_attempts' => 'integer',
    ];

    public static function fingerprintFor(string $marketplace, string $sku, ?string $externalId, ?string $reviewer, ?string $body, mixed $date): string
    {
        $externalId = trim((string) $externalId);
        if ($externalId !== '') {
            return sha1(strtolower($marketplace).'|'.strtoupper(trim($sku)).'|id|'.$externalId);
        }

        return sha1(strtolower($marketplace).'|'.strtoupper(trim($sku)).'|'
            .mb_strtolower(trim((string) $reviewer)).'|'
            .mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $body)) ?? '').'|'
            .($date ? substr((string) $date, 0, 10) : ''));
    }
}
