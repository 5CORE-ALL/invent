<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class OfferUpAnalyticsController extends Controller
{
    public function index(): View
    {
        return view('market-places.offerup_analytics');
    }
}
