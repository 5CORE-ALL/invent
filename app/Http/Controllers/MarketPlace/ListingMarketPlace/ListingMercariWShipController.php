<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;



use App\Http\Controllers\Controller;
use App\Support\Marketplace\AutomatedListingPage;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\MercariWShipListingStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;


class ListingMercariWShipController extends Controller
{
    use HandlesListingPublishActions;

    public function getNrReqCount()
    {
        return ChannelListingRegistry::nrReqCountArray('mercariwship');
    }

}