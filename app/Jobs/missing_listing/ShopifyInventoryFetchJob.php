<?php

namespace App\Jobs\missing_listing;

use App\Services\ShopifyApiService;

class ShopifyInventoryFetchJob extends BaseInventoryFetchJob
{
    protected $platform = 'shopify';

    protected function fetchInventory()
    {
        return (new ShopifyApiService())->getinventory();
    }
}