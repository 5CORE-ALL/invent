<?php

namespace App\Services\MarketplaceManager;

use App\Models\Temu2Metric;
use Illuminate\Support\Facades\Schema;

/**
 * Resolve the Shopify SKU for a Temu2 order line.
 *
 * Listing identity is sku_id → temu2_metrics.sku. Order ext_code is used only
 * when that listing is missing or maps to more than one SKU (2PAIR / 4PCS aliases).
 */
class Temu2OrderLineSkuResolver
{
    /**
     * @var array<string, string>
     */
    private array $listingSkuBySkuId = [];

    public function resolve(?string $skuId, ?string $extCode, ?string $displaySku = null): string
    {
        $fallback = trim((string) ($extCode ?: $displaySku ?: ''));
        $fromListing = $this->listingSkuForSkuId((string) $skuId);
        if ($fromListing !== '') {
            return $fromListing;
        }

        return $fallback;
    }

    public function listingSkuForSkuId(string $skuId): string
    {
        $skuId = trim($skuId);
        if ($skuId === '') {
            return '';
        }
        if (array_key_exists($skuId, $this->listingSkuBySkuId)) {
            return $this->listingSkuBySkuId[$skuId];
        }

        $resolved = $this->lookupListingSku($skuId);
        $this->listingSkuBySkuId[$skuId] = $resolved;

        return $resolved;
    }

    protected function lookupListingSku(string $skuId): string
    {
        try {
            if (! Schema::hasTable('temu2_metrics') || ! Schema::hasColumn('temu2_metrics', 'sku_id')) {
                return '';
            }

            $skus = Temu2Metric::query()
                ->where('sku_id', $skuId)
                ->pluck('sku')
                ->map(static fn ($sku) => trim((string) $sku))
                ->filter(static fn (string $sku) => self::isUsableSku($sku))
                ->unique(static fn (string $sku) => mb_strtolower($sku))
                ->values();

            if ($skus->count() === 1) {
                return (string) $skus->first();
            }
        } catch (\Throwable $e) {
            // keep order ext_code
        }

        return '';
    }

    public static function isUsableSku(string $sku): bool
    {
        $sku = trim($sku);
        if ($sku === '' || in_array($sku, ['__order__', '__unknown__'], true)) {
            return false;
        }

        return ! (bool) preg_match('/^[a-f0-9]{32}$/i', $sku);
    }
}
