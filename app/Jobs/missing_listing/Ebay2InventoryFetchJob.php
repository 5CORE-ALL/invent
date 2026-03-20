<?php

namespace App\Jobs\missing_listing;

use App\Services\Ebay2ApiService;

class  Ebay2InventoryFetchJob extends BaseInventoryFetchJob
{
    protected $platform = 'ebay2';

    protected function fetchInventory()
    {
        return (new Ebay2ApiService())->getinventory();
    }
}