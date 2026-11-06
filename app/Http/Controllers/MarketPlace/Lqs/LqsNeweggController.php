<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

use App\Http\Controllers\MarketPlace\Lqs\Concerns\HandlesNeweggLqsApi;

class LqsNeweggController extends LqsMarketplaceBaseController
{
    use HandlesNeweggLqsApi;

    protected function slug(): string
    {
        return 'newegg';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.newegg';
    }
}
