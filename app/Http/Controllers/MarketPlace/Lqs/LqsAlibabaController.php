<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

class LqsAlibabaController extends LqsMarketplaceBaseController
{
    protected function slug(): string
    {
        return 'alibaba';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.alibaba';
    }
}
