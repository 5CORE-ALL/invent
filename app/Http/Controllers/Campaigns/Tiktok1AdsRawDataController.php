<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\TiktokCampaignReport;
use App\Support\TikTokAdsSkuResolver;
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
                    'sums' => [
                        'count' => 0,
                        'cost_l30' => 0,
                        'cost_l1' => 0,
                        'orders_l30' => 0,
                        'orders_l1' => 0,
                        'revenue_l30' => 0,
                        'revenue_l1' => 0,
                    ],
                ]);
            }

            $rows = TiktokCampaignReport::query()->orderByDesc('id')->get();
            $sums = [
                'count' => $rows->count(),
                'cost_l30' => 0.0,
                'cost_l1' => 0.0,
                'orders_l30' => 0,
                'orders_l1' => 0,
                'revenue_l30' => 0.0,
                'revenue_l1' => 0.0,
            ];
            foreach ($rows as $row) {
                $range = strtoupper(trim((string) ($row->report_range ?? '')));
                $cost = (float) ($row->cost ?? 0);
                $orders = (int) ($row->sku_orders ?? 0);
                $revenue = (float) ($row->gross_revenue ?? 0);
                if ($range === 'L1') {
                    $sums['cost_l1'] += $cost;
                    $sums['orders_l1'] += $orders;
                    $sums['revenue_l1'] += $revenue;
                } else {
                    $sums['cost_l30'] += $cost;
                    $sums['orders_l30'] += $orders;
                    $sums['revenue_l30'] += $revenue;
                }
            }
            $sums['cost_l30'] = round($sums['cost_l30'], 2);
            $sums['cost_l1'] = round($sums['cost_l1'], 2);
            $sums['revenue_l30'] = round($sums['revenue_l30'], 2);
            $sums['revenue_l1'] = round($sums['revenue_l1'], 2);

            $data = $rows->map(function (TiktokCampaignReport $row) {
                $arr = $row->toArray();
                $arr['sku'] = TikTokAdsSkuResolver::skuFor($row->product_id, $row->campaign_name);
                if (($arr['roi'] === null || $arr['roi'] === '') && (float) ($row->cost ?? 0) > 0) {
                    $arr['roi'] = round((float) ($row->gross_revenue ?? 0) / (float) $row->cost, 2);
                }
                $arr['time_posted'] = optional($row->time_posted)->format('Y-m-d H:i:s');
                $arr['created_at'] = optional($row->created_at)->format('Y-m-d H:i:s');
                $arr['updated_at'] = optional($row->updated_at)->format('Y-m-d H:i:s');

                return $arr;
            })->values();

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => $data->count(),
                'sums' => $sums,
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
