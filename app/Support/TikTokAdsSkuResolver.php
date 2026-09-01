<?php

namespace App\Support;

use App\Models\TikTokProduct;
use Illuminate\Support\Facades\Schema;

/**
 * TikTok ads exports use campaign names like "TRIPOD MIC STAND".
 * Shop analytics keys off seller SKU — resolve via product_id.
 */
class TikTokAdsSkuResolver
{
    /**
     * @return array<string, string> product_id/sku_id => sku
     */
    public static function productIdToSkuMap(): array
    {
        static $map = null;
        if (is_array($map)) {
            return $map;
        }

        $map = [];
        if (! Schema::hasTable('tiktok_products')) {
            return $map;
        }

        TikTokProduct::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['product_id', 'sku_id', 'sku'])
            ->each(function (TikTokProduct $product) use (&$map) {
                $sku = trim((string) $product->sku);
                if ($sku === '') {
                    return;
                }
                foreach ([$product->product_id, $product->sku_id] as $id) {
                    $id = trim((string) $id);
                    if ($id !== '') {
                        $map[$id] = $sku;
                    }
                }
            });

        return $map;
    }

    public static function skuFor(mixed $productId, mixed $campaignName = ''): string
    {
        $pid = trim((string) $productId);
        $map = self::productIdToSkuMap();
        if ($pid !== '' && isset($map[$pid])) {
            return strtoupper(trim($map[$pid]));
        }

        return strtoupper(trim((string) $campaignName));
    }
}
