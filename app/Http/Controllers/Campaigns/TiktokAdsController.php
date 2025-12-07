<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use Illuminate\Http\Request;

class TiktokAdsController extends Controller
{
    public function index()
    {
        $marketplaceData = MarketplacePercentage::where("marketplace", "TiktokShop")->first();
        $tiktokPercentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $tiktokAdPercentage = $marketplaceData ? $marketplaceData->ad_updates : 100;

        // Get chart data for last 30 days
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30);

        // Create array for all 30 days with data or zeros
        $dates = [];
        $clicks = [];
        $spend = [];
        $adSales = [];
        $adSold = [];
        $acos = [];
        $cvr = [];

        for ($i = 30; $i >= 0; $i--) {
            $date = \Carbon\Carbon::now()->subDays($i)->format('Y-m-d');
            $dates[] = $date;
            
            // Placeholder values - can be populated with actual Temu metrics
            $clicks[] = 0;
            $spend[] = 0;
            $adSales[] = 0;
            $adSold[] = 0;
            $acos[] = 0;
            $cvr[] = 0;
        }

        return view('campaign.tiktok.tiktok-ads', compact('tiktokPercentage', 'tiktokAdPercentage', 'dates', 'clicks', 'spend', 'adSales', 'adSold', 'acos', 'cvr'));
    }
}
