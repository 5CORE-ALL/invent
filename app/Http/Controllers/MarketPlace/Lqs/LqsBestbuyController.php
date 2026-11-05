<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

use App\Http\Controllers\MarketPlace\Lqs\Concerns\HandlesBestBuyLqsApi;

class LqsBestbuyController extends LqsMarketplaceBaseController
{
    use HandlesBestBuyLqsApi;

    protected function slug(): string
    {
        return 'bestbuy';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.bestbuy';
    }
}
