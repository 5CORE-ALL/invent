<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

class LqsWalmartController extends LqsMarketplaceBaseController
{
    protected function slug(): string
    {
        return 'walmart';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.walmart';
    }
}
