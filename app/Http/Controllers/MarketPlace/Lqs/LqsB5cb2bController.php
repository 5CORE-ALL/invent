<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

use App\Http\Controllers\MarketPlace\Lqs\Concerns\HandlesB5cb2bLqsApi;

class LqsB5cb2bController extends LqsMarketplaceBaseController
{
    use HandlesB5cb2bLqsApi;

    protected function slug(): string
    {
        return 'b5cb2b';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.b5cb2b';
    }
}
