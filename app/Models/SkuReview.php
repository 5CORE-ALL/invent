<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class SkuReview extends Model
{
    use HasFactory;

    protected $table = 'sku_reviews';

    protected $fillable = [
        'sku', 'product_id', 'marketplace', 'review_id', 'rating',
        'review_title', 'review_text', 'reviewer_name', 'review_date',
        'sentiment', 'issue_category', 'ai_summary', 'ai_reply',
        'supplier_id', 'department', 'source_type', 'is_flagged',
    ];

    protected $casts = [
        'review_date' => 'date',
        'is_flagged'  => 'boolean',
        'rating'      => 'integer',
    ];

    // Department mapping by issue category
    const DEPARTMENT_MAP = [
        'quality'       => 'Supplier Team',
        'packaging'     => 'Packaging Team',
        'shipping'      => 'Logistics',
        'service'       => 'Customer Support',
        'wrong_item'    => 'Logistics',
        'missing_parts' => 'Supplier Team',
        'other'         => 'Customer Support',
    ];

    const SENTIMENT_COLORS = [
        'positive' => 'success',
        'neutral'  => 'warning',
        'negative' => 'danger',
    ];

    const ISSUE_COLORS = [
        'quality'       => 'danger',
        'packaging'     => 'warning',
        'shipping'      => 'info',
        'service'       => 'secondary',
        'wrong_item'    => 'dark',
        'missing_parts' => 'primary',
        'other'         => 'light',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product()
    {
        return $this->belongsTo(ProductMaster::class, 'product_id');
    }

    public function scopeUnanalyzed(Builder $query): Builder
    {
        return $query->whereNull('sentiment');
    }

    public function scopeNegative(Builder $query): Builder
    {
        return $query->where('sentiment', 'negative');
    }

    public function scopeFlagged(Builder $query): Builder
    {
        return $query->where('is_flagged', true);
    }

    public function getDepartmentAttribute(): ?string
    {
        if ($this->attributes['department'] ?? null) {
            return $this->attributes['department'];
        }
        return self::DEPARTMENT_MAP[$this->issue_category] ?? null;
    }

    public static function mapDepartment(string $issueCategory): string
    {
        return self::DEPARTMENT_MAP[$issueCategory] ?? 'Customer Support';
    }
}
