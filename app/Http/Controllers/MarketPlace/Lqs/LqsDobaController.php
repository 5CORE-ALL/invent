<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

use App\Http\Controllers\MarketPlace\Lqs\Concerns\HandlesDobaLqsApi;

class LqsDobaController extends LqsMarketplaceBaseController
{
    use HandlesDobaLqsApi;

    protected function slug(): string
    {
        return 'doba';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.doba';
    }
}
