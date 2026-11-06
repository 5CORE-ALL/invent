<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

use App\Http\Controllers\MarketPlace\Lqs\Concerns\HandlesTemuLqsApi;

class LqsTemu3Controller extends LqsMarketplaceBaseController
{
    use HandlesTemuLqsApi;

    protected function slug(): string
    {
        return 'temu3';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.temu3';
    }
}
