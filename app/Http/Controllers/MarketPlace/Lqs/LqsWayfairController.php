<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

class LqsWayfairController extends LqsMarketplaceBaseController
{
    protected function slug(): string
    {
        return 'wayfair';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.wayfair';
    }
}
