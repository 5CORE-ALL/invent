<?php

namespace App\Jobs\missing_listing;

use App\Services\Ebay3ApiService;

class  Ebay3InventoryFetchJob extends BaseInventoryFetchJob
{
    protected $platform = 'ebay3';

    protected function fetchInventory()
    {
        return (new Ebay3ApiService())->getinventory();
    }
}