<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Stored copy of the Listing Manager product-modal payload (Amazon + Main Store + drafts) per SKU.
 */
class ListingManagerProductSnapshot extends Model
{
    protected $table = 'listing_manager_product_snapshots';

    protected $fillable = [
        'sku',
        'payload',
        'build_ms',
        'built_at',
        'last_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'build_ms' => 'integer',
        'built_at' => 'datetime',
    ];

    public static function tableReady(): bool
    {
        static $ready = null;
        if ($ready === null) {
            try {
                $ready = Schema::hasTable('listing_manager_product_snapshots');
            } catch (\Throwable) {
                $ready = false;
            }
        }

        return $ready;
    }

    public static function forSku(string $sku): ?self
    {
        $sku = trim($sku);
        if ($sku === '' || ! self::tableReady()) {
            return null;
        }

        return self::query()->where('sku', $sku)->first();
    }

    public function hasPayload(): bool
    {
        return is_array($this->payload) && $this->payload !== [] && $this->built_at !== null;
    }

    public function isStale(int $hours): bool
    {
        return $this->built_at === null || $this->built_at->lt(now()->subHours(max(1, $hours)));
    }
}
