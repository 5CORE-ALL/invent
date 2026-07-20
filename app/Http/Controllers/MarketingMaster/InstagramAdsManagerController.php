<?php

namespace App\Http\Controllers\MarketingMaster;

use App\Http\Controllers\Controller;

class InstagramAdsManagerController extends Controller
{
    public function index()
    {
        return view('marketing-masters.instagram_ads_manager.index');
    }

    public function getInstagramAdsData()
    {
        $data = [
            ['id' => 1, 'campaign_name' => 'Campaign 1', 'status' => 'Active', 'budget' => 100],
            ['id' => 2, 'campaign_name' => 'Campaign 2', 'status' => 'Paused', 'budget' => 200],
        ];

        return response()->json($data);
    }
}
