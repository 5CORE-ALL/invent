<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

class LqsAliexpressController extends LqsMarketplaceBaseController
{
    protected function slug(): string
    {
        return 'aliexpress';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.aliexpress';
    }
}
