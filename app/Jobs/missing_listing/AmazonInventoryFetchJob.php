<?php

namespace App\Jobs\missing_listing;

use App\Services\AmazonSpApiService;

class AmazonInventoryFetchJob extends BaseInventoryFetchJob
{
    protected $platform = 'amazon';

    protected function fetchInventory()
    {
        return (new AmazonSpApiService())->getinventory();
    }
}