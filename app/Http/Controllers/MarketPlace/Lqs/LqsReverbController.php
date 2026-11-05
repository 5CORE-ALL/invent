<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

use App\Http\Controllers\MarketPlace\Lqs\Concerns\HandlesReverbLqsApi;

class LqsReverbController extends LqsMarketplaceBaseController
{
    use HandlesReverbLqsApi;

    protected function slug(): string
    {
        return 'reverb';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.reverb';
    }
}
