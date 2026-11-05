<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

use App\Http\Controllers\MarketPlace\Lqs\Concerns\HandlesMiraklLqsApi;

class LqsMacyController extends LqsMarketplaceBaseController
{
    use HandlesMiraklLqsApi;

    protected function slug(): string
    {
        return 'macy';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.macy';
    }
}
