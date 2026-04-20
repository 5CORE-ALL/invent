<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkuImage extends Model
{
    protected $fillable = [
        'product_id',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function imageMarketplaceMaps(): HasMany
    {
        return $this->hasMany(ImageMarketplaceMap::class, 'sku_image_id');
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/'.$this->file_path);
    }

    /**
     * @return array<int, array{label: string, class: string}>
     */
    public function pushStatusBadges(): array
    {
        if (! $this->relationLoaded('imageMarketplaceMaps')) {
            $this->load('imageMarketplaceMaps');
        }
        $rows = $this->imageMarketplaceMaps;
        if ($rows->isEmpty()) {
            return [['label' => 'Not pushed', 'class' => 'bg-secondary']];
        }
        $badges = [];
        foreach ($rows->pluck('status')->unique()->sort()->values() as $status) {
            $badges[] = [
                'label' => (string) $status,
                'class' => match ($status) {
                    'pending' => 'bg-warning text-dark',
                    'sent' => 'bg-success',
                    'failed' => 'bg-danger',
                    default => 'bg-secondary',
                },
            ];
        }

        return $badges;
    }
}
