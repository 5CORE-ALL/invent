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
        return view('campaign.tiktok.tiktok_gmv_ads_raw_data', [
            'sums' => $this->currentGmvSums(),
        ]);
    }

    public function getData()
    {
        try {
            if (! Schema::hasTable('tiktok_gmv_ads')) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'count' => 0,
                    'sums' => $this->emptyGmvSums(),
                ]);
            }

            $data = TiktokGmvAd::query()
                ->orderByDesc('ad_sold')
                ->orderByDesc('ad_sales')
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
                'sums' => $this->sumGmvRows($data),
            ]);
        } catch (\Throwable $e) {
            Log::error('GMV TikTok Ads Raw Data failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
                'count' => 0,
                'sums' => $this->emptyGmvSums(),
            ], 500);
        }
    }

    /**
     * @return array{count: int, sold_l30: int, sold_l1: int, sales_l30: float, sales_l1: float, spend_l30: float, spend_l1: float, budget: float}
     */
    private function currentGmvSums(): array
    {
        if (! Schema::hasTable('tiktok_gmv_ads')) {
            return $this->emptyGmvSums();
        }

        try {
            $rows = TiktokGmvAd::query()
                ->get(['report_range', 'ad_sold', 'ad_sales', 'spend', 'budget'])
                ->map(fn (TiktokGmvAd $row) => $row->toArray())
                ->values();

            return $this->sumGmvRows($rows);
        } catch (\Throwable $e) {
            Log::warning('GMV TikTok Ads badge sums failed: '.$e->getMessage());

            return $this->emptyGmvSums();
        }
    }

    /**
     * @return array{count: int, sold_l30: int, sold_l1: int, sales_l30: float, sales_l1: float, spend_l30: float, spend_l1: float, budget: float}
     */
    private function emptyGmvSums(): array
    {
        return [
            'count' => 0,
            'sold_l30' => 0,
            'sold_l1' => 0,
            'sales_l30' => 0.0,
            'sales_l1' => 0.0,
            'spend_l30' => 0.0,
            'spend_l1' => 0.0,
            'budget' => 0.0,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @return array{count: int, sold_l30: int, sold_l1: int, sales_l30: float, sales_l1: float, spend_l30: float, spend_l1: float, budget: float}
     */
    private function sumGmvRows($rows): array
    {
        $sums = $this->emptyGmvSums();
        $budgetL30 = 0.0;
        $budgetAll = 0.0;
        $hasL30 = false;

        foreach ($rows as $row) {
            $range = strtoupper(trim((string) ($row['report_range'] ?? '')));
            $sold = (int) ($row['ad_sold'] ?? 0);
            $sales = (float) ($row['ad_sales'] ?? 0);
            $spend = (float) ($row['spend'] ?? 0);
            $budget = (float) ($row['budget'] ?? 0);
            $budgetAll += $budget;
            $sums['count']++;

            if ($range === 'L30') {
                $hasL30 = true;
                $sums['sold_l30'] += $sold;
                $sums['sales_l30'] += $sales;
                $sums['spend_l30'] += $spend;
                $budgetL30 += $budget;
            } elseif ($range === 'L1') {
                $sums['sold_l1'] += $sold;
                $sums['sales_l1'] += $sales;
                $sums['spend_l1'] += $spend;
            } else {
                $sums['sold_l30'] += $sold;
                $sums['sales_l30'] += $sales;
                $sums['spend_l30'] += $spend;
            }
        }

        $sums['sales_l30'] = round($sums['sales_l30'], 2);
        $sums['sales_l1'] = round($sums['sales_l1'], 2);
        $sums['spend_l30'] = round($sums['spend_l30'], 2);
        $sums['spend_l1'] = round($sums['spend_l1'], 2);
        $sums['budget'] = round($hasL30 ? $budgetL30 : $budgetAll, 2);

        return $sums;
    }
}
