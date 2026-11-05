<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

use App\Http\Controllers\MarketPlace\Lqs\Concerns\HandlesEbayLqsApi;

class LqsEbay3Controller extends LqsMarketplaceBaseController
{
    use HandlesEbayLqsApi;

    protected function slug(): string
    {
        return 'ebay3';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.ebay3';
    }
}
