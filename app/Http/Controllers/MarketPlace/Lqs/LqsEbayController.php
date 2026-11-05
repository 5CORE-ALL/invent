<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

use App\Http\Controllers\MarketPlace\Lqs\Concerns\HandlesEbayLqsApi;

class LqsEbayController extends LqsMarketplaceBaseController
{
    use HandlesEbayLqsApi;

    protected function slug(): string
    {
        return 'ebay';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.ebay';
    }
}
