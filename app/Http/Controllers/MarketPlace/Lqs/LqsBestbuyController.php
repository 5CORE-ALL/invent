<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

class LqsBestbuyController extends LqsMarketplaceBaseController
{
    protected function slug(): string
    {
        return 'bestbuy';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.bestbuy';
    }
}
