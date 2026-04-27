<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;

class VideosForAdsController extends Controller
{
    public function index()
    {
        return view('videos-for-ads');
    }
}
