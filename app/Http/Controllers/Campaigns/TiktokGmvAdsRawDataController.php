<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\TiktokGmvAd;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * GMV TikTok Ads Raw Data — every row in tiktok_gmv_ads. No filters.
 */
class TiktokGmvAdsRawDataController extends Controller
{
    public function index()
    {
        return view('campaign.tiktok.tiktok_gmv_ads_raw_data');
    }

    public function getData()
    {
        try {
            if (! Schema::hasTable('tiktok_gmv_ads')) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'count' => 0,
                ]);
            }

            $data = TiktokGmvAd::query()
                ->orderByDesc('id')
                ->get()
                ->map(function (TiktokGmvAd $row) {
                    $arr = $row->toArray();
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
            Log::error('GMV TikTok Ads Raw Data failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
                'count' => 0,
            ], 500);
        }
    }
}
