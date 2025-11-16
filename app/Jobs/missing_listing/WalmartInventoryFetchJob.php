<?php

namespace App\Jobs\missing_listing;

use App\Services\WalmartApiService;

class WalmartInventoryFetchJob extends BaseInventoryFetchJob
{
    protected $platform = 'walmart';

    protected function fetchInventory()
    {
        return (new WalmartApiService())->getinventory();
    }
}