<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

class LqsMacyController extends LqsMarketplaceBaseController
{
    protected function slug(): string
    {
        return 'macy';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.macy';
    }
}
