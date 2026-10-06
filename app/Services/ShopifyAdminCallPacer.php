<?php

namespace App\Services;

use Illuminate\Http\Client\Response;

/**
 * Back-compat wrapper. All Shopify Admin REST traffic shares ShopifyAdminCallGate
 * so verification Accept and catalog/price sync cannot exceed 2 calls/sec together.
 */
class ShopifyAdminCallPacer
{
    public static function wait(): void
    {
        ShopifyAdminCallGate::acquire();
    }

    public static function record(?Response $response): void
    {
        ShopifyAdminCallGate::record($response);
    }
}
