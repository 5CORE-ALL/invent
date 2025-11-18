<?php

namespace App\Services;

class CustomLpMappingService
{
    /**
     * Get custom LP mapping for specific SKUs
     * These SKUs use custom LP values instead of ProductMaster LP
     */
    public static function getCustomLpMapping(): array
    {
        return [
            'CAPO BLUE' => 0.44,
            'ET 6FT BLU' => 0.43,
            'GS EL' => 0.65,
            'MX 4CH 2MIC SLV' => 36.04,
            'SW L RED 5PCS' => 1.04,
        ];
    }

    /**
     * Get LP value for a SKU, checking custom mapping first, then ProductMaster
     */
    public static function getLpValue(string $sku, $product = null): float
    {
        $customLpMapping = self::getCustomLpMapping();

        // Check if SKU has custom LP, otherwise use ProductMaster LP
        return isset($customLpMapping[$sku]) ? $customLpMapping[$sku] : ($product ? floatval($product->Values['lp'] ?? 0) : 0);
    }
}