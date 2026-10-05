<?php

namespace App\Http\Controllers;

use App\Services\ShopifyVariantPriceUpdater;
use Illuminate\Support\Facades\Log;

class UpdatePriceApiController extends Controller
{
    //update price in shopify by variant id
    public static function updateShopifyVariantPrice($variantId, $newPrice, $store = 'b2c')
    {
        try {
            Log::info('Shopify price update started', [
                'variant_id' => $variantId,
                'new_price' => $newPrice,
                'store' => $store,
            ]);

            return app(ShopifyVariantPriceUpdater::class)->update((string) $variantId, (float) $newPrice, (string) $store);
        } catch (\Exception $e) {
            $storeName = ($store === 'pls' || $store === 'prolightsounds') ? 'ProLightSounds' : 'Shopify B2C';
            Log::error($storeName.' price update exception', [
                'variant_id' => $variantId,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Exception: '.$e->getMessage(),
            ];
        }
    }
}
