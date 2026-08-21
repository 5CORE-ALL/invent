<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\TiktokCampaignReport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * TikTok 1 Ads Raw Data — every row saved by /tiktok/utilized/upload
 * into tiktok_campaign_reports. No L30/L7, Product card, or SKU filters.
 */
class Tiktok1AdsRawDataController extends Controller
{
    public function index()
    {
        return view('campaign.tiktok.tiktok_1_ads_raw_data');
    }

    public function getData()
    {
        try {
            if (! Schema::hasTable('tiktok_campaign_reports')) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'count' => 0,
                ]);
            }

            $data = TiktokCampaignReport::query()
                ->orderByDesc('id')
                ->get()
                ->map(function (TiktokCampaignReport $row) {
                    $arr = $row->toArray();
                    $arr['time_posted'] = optional($row->time_posted)->format('Y-m-d H:i:s');
                    $arr['created_at'] = optional($row->created_at)->format('Y-m-d H:i:s');
                    $arr['updated_at'] = optional($row->updated_at)->format('Y-m-d H:i:s');

                    return $arr;
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => $data->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('TikTok 1 Ads Raw Data failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
                'count' => 0,
            ], 500);
        }
    }
}
