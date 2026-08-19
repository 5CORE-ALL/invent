<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;

class InactiveListingController extends Controller
{
    public function index()
    {
        return view('market-places.Inactive_listings');
    }
}
