<?php

namespace App\Jobs\missing_listing;

use App\Services\TiendamiaApiService;

class TiendamiaInventoryFetchJob extends BaseInventoryFetchJob
{
    protected $platform = 'tiendamia';

    protected function fetchInventory()
    {
        return (new TiendamiaApiService())->getinventory();
    }
}