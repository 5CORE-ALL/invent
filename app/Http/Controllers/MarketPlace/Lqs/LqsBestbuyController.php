<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

use App\Http\Controllers\MarketPlace\Lqs\Concerns\HandlesMiraklLqsApi;

class LqsBestbuyController extends LqsMarketplaceBaseController
{
    use HandlesMiraklLqsApi;

    protected function slug(): string
    {
        return 'bestbuy';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.bestbuy';
    }
}
