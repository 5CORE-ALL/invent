<?php

namespace App\Http\Controllers;

use App\Models\ShopifyMetaCampaign;
use App\Jobs\FetchShopifyMetaCampaignsJob;
use Illuminate\Http\Request;
use Illuminate\Console\Scheduling\Schedule;

class ShopifyMetaCampaignController extends Controller
{
    /**
     * Trigger data fetch
     */
    public function fetch(Request $request)
    {
        $dateRange = $request->get('date_range', 'all');
        $channel = $request->get('channel', 'both');
        
        FetchShopifyMetaCampaignsJob::dispatch($dateRange, $channel);

        return response()->json([
            'success' => true,
            'message' => "Job dispatched to fetch {$dateRange} campaign data for {$channel}"
        ]);
    }

    /**
     * Get summary statistics
     */
    public function summary(Request $request)
    {
        $channel = $request->get('channel'); // Optional filter by channel
        
        $summary = [];

        foreach (['7_days', '30_days', '60_days'] as $range) {
            $query = ShopifyMetaCampaign::where('date_range', $range);
            
            if ($channel) {
                $query->where('referring_channel', $channel);
            }
            
            $campaigns = $query->get();
            
            $summary[$range] = [
                'campaigns_count' => $campaigns->count(),
                'total_sales' => $campaigns->sum('sales'),
                'total_orders' => $campaigns->sum('orders'),
                'total_sessions' => $campaigns->sum('sessions'),
                'total_ad_spend' => $campaigns->sum('ad_spend'),
                'average_order_value' => $campaigns->sum('orders') > 0 
                    ? $campaigns->sum('sales') / $campaigns->sum('orders') 
                    : 0,
                'conversion_rate' => $campaigns->sum('sessions') > 0
                    ? ($campaigns->sum('orders') / $campaigns->sum('sessions')) * 100
                    : 0,
                'roas' => $campaigns->sum('ad_spend') > 0
                    ? $campaigns->sum('sales') / $campaigns->sum('ad_spend')
                    : 0,
            ];
        }

        return response()->json($summary);
    }

    /**
     * Compare campaigns across date ranges
     */
    public function compare($campaignId)
    {
        $campaigns = ShopifyMetaCampaign::where('campaign_id', $campaignId)
            ->orderBy('date_range')
            ->get();

        if ($campaigns->isEmpty()) {
            return response()->json(['error' => 'Campaign not found'], 404);
        }

        return response()->json([
            'campaign_id' => $campaignId,
            'campaign_name' => $campaigns->first()->campaign_name,
            'data' => $campaigns
        ]);
    }

    /**
     * Schedule commands
     */
    protected function schedule(Schedule $schedule)
    {
        // Fetch both Facebook and Instagram daily at midnight
        $schedule->command('shopify:fetch-meta-campaigns --channel=both')->daily();
    }
}

