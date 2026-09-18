<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

class LqsEbay3Controller extends LqsMarketplaceBaseController
{
    protected function slug(): string
    {
        return 'ebay3';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.ebay3';
    }
}
