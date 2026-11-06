<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

use App\Http\Controllers\MarketPlace\Lqs\Concerns\HandlesTopdawgLqsApi;

class LqsTopdawgController extends LqsMarketplaceBaseController
{
    use HandlesTopdawgLqsApi;

    protected function slug(): string
    {
        return 'topdawg';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.topdawg';
    }
}
