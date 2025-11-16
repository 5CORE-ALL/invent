<?php

namespace App\Jobs\missing_listing;

use App\Services\SheinApiService;

class  SheinInventoryFetchJob extends BaseInventoryFetchJob
{
    protected $platform = 'shopify';

    protected function fetchInventory()
    {
        return (new SheinApiService())->listAllProducts();
    }
}