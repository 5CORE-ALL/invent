<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

class LqsEbay2Controller extends LqsMarketplaceBaseController
{
    protected function slug(): string
    {
        return 'ebay2';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.ebay2';
    }
}
