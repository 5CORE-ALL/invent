<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

use App\Http\Controllers\MarketPlace\Lqs\Concerns\HandlesTemuLqsApi;

class LqsTemu2Controller extends LqsMarketplaceBaseController
{
    use HandlesTemuLqsApi;

    protected function slug(): string
    {
        return 'temu2';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.temu2';
    }
}
