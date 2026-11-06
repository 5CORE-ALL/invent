<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

use App\Http\Controllers\MarketPlace\Lqs\Concerns\HandlesSheinLqsApi;

class LqsSheinController extends LqsMarketplaceBaseController
{
    use HandlesSheinLqsApi;

    protected function slug(): string
    {
        return 'shein';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.shein';
    }
}
