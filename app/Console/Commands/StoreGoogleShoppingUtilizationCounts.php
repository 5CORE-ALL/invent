<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\GoogleDataView;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Campaigns\GoogleAdsDateRangeTrait;

class StoreGoogleShoppingUtilizationCounts extends Command
{
    use GoogleAdsDateRangeTrait;

    protected $signature = 'google:store-shopping-utilization-counts';
    protected $description = 'Store daily counts of over/under utilized Google Shopping campaigns';

    public function handle()
    {
        $this->info('Starting to store Google Shopping utilization counts...');

        try {
            // Get data using the same method as the controller
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

            // Calculate date ranges (same as controller)
            $dateRanges = $this->calculateDateRanges();
            
            // Fetch SHOPPING campaigns data within L30 range
            $googleCampaigns = DB::table('google_ads_campaigns')
                ->select(
                    'campaign_id',
                    'campaign_name',
                    'campaign_status',
                    'budget_amount_micros',
                    'date',
                    'metrics_cost_micros',
                    'metrics_clicks'
                )
                ->where('advertising_channel_type', 'SHOPPING')
                ->whereBetween('date', [$dateRanges['L30']['start'], $dateRanges['L30']['end']])
                ->get();

            $result = [];
            $uniqueCampaignIds = $googleCampaigns->pluck('campaign_id')->unique();
            $campaignMap = $googleCampaigns->groupBy('campaign_id')->map(function ($campaigns) {
                return $campaigns->first();
            });

            foreach ($uniqueCampaignIds as $campaignId) {
                $campaign = $campaignMap[$campaignId];
                $campaignName = $campaign->campaign_name;

                // Try to find matching SKU from ProductMaster
                $matchedSku = null;
                $matchedPm = null;

                foreach ($productMasters as $pm) {
                    $sku = strtoupper(trim($pm->sku));
                    $campaignUpper = strtoupper(trim($campaignName));
                    // Remove trailing period from campaign name for matching
                    $campaignUpperCleaned = rtrim($campaignUpper, '.');

                    $parts = array_map(function($part) { return rtrim(trim($part), '.'); }, explode(',', $campaignUpperCleaned));
                    $skuTrimmed = strtoupper(trim($sku));
                    $exactMatch = in_array($skuTrimmed, $parts);

                    if (!$exactMatch) {
                        $exactMatch = $campaignUpperCleaned === $skuTrimmed;
                    }

                    if ($exactMatch) {
                        $matchedSku = $pm->sku;
                        $matchedPm = $pm;
                        break;
                    }
                }

                // Get INV for matched SKU
                $inv = 0;
                if ($matchedPm) {
                    $shopify = $shopifyData[$matchedPm->sku] ?? null;
                    $inv = $shopify->inv ?? 0;
                }

                // Skip campaigns with INV = 0 (same as controller default filter)
                if (floatval($inv) <= 0) {
                    continue;
                }

                // Get latest campaign budget
                $latestCampaign = $googleCampaigns->where('campaign_id', $campaignId)
                    ->sortByDesc('date')
                    ->first();
                $budget = $latestCampaign && $latestCampaign->budget_amount_micros
                    ? $latestCampaign->budget_amount_micros / 1000000
                    : 0;

                // Aggregate L7 and L1 spend
                $spend_L7 = $googleCampaigns
                    ->where('campaign_id', $campaignId)
                    ->whereBetween('date', [$dateRanges['L7']['start'], $dateRanges['L7']['end']])
                    ->where('campaign_status', 'ENABLED')
                    ->sum('metrics_cost_micros') / 1000000;

                $spend_L1 = $googleCampaigns
                    ->where('campaign_id', $campaignId)
                    ->whereBetween('date', [$dateRanges['L1']['start'], $dateRanges['L1']['end']])
                    ->where('campaign_status', 'ENABLED')
                    ->sum('metrics_cost_micros') / 1000000;

                // Calculate UB7 and UB1
                $ub7 = $budget > 0 ? ($spend_L7 / ($budget * 7)) * 100 : 0;
                $ub1 = $budget > 0 ? ($spend_L1 / ($budget * 1)) * 100 : 0;

                // Store campaign data (only once per campaign_id)
                if (!isset($result[$campaignId])) {
                    $result[$campaignId] = [
                        'campaign_id' => $campaignId,
                        'ub7' => $ub7,
                        'ub1' => $ub1,
                    ];
                }
            }

            // Count campaigns by utilization type
            // 7UB only condition
            $overUtilizedCount7ub = 0;
            $underUtilizedCount7ub = 0;
            
            // 7UB + 1UB condition
            $overUtilizedCount7ub1ub = 0;
            $underUtilizedCount7ub1ub = 0;

            foreach ($result as $campaignData) {
                $ub7 = $campaignData['ub7'];
                $ub1 = $campaignData['ub1'];
                
                // Categorize based on 7UB only condition
                if ($ub7 > 99) {
                    $overUtilizedCount7ub++;
                } elseif ($ub7 < 66) {
                    $underUtilizedCount7ub++;
                }
                
                // Categorize based on 7UB + 1UB condition
                if ($ub7 > 99 && $ub1 > 99) {
                    $overUtilizedCount7ub1ub++;
                } elseif ($ub7 < 66 && $ub1 < 66) {
                    $underUtilizedCount7ub1ub++;
                }
            }

            // Store in google_data_view table with date as SKU
            $today = now()->format('Y-m-d');
            $tomorrow = now()->copy()->addDay()->format('Y-m-d');
            
            // Data for today (with actual counts)
            $data = [
                // 7UB only condition
                'over_utilized_7ub' => $overUtilizedCount7ub,
                'under_utilized_7ub' => $underUtilizedCount7ub,
                // 7UB + 1UB condition
                'over_utilized_7ub_1ub' => $overUtilizedCount7ub1ub,
                'under_utilized_7ub_1ub' => $underUtilizedCount7ub1ub,
                'date' => $today
            ];

            // Blank data for tomorrow (all counts as 0)
            $blankData = [
                // 7UB only condition
                'over_utilized_7ub' => 0,
                'under_utilized_7ub' => 0,
                // 7UB + 1UB condition
                'over_utilized_7ub_1ub' => 0,
                'under_utilized_7ub_1ub' => 0,
                'date' => $tomorrow
            ];

            // Use date as SKU identifier for this data
            $skuKeyToday = 'GOOGLE_SHOPPING_UTILIZATION_' . $today;
            $skuKeyTomorrow = 'GOOGLE_SHOPPING_UTILIZATION_' . $tomorrow;

            // Insert/Update today's data
            $existingToday = GoogleDataView::where('sku', $skuKeyToday)->first();

            if ($existingToday) {
                $existingToday->update(['value' => $data]);
                $this->info("Updated Google Shopping utilization counts for {$today}");
            } else {
                GoogleDataView::create([
                    'sku' => $skuKeyToday,
                    'value' => $data
                ]);
                $this->info("Created Google Shopping utilization counts for {$today}");
            }

            // Insert/Update tomorrow's blank data (only if it doesn't exist)
            $existingTomorrow = GoogleDataView::where('sku', $skuKeyTomorrow)->first();

            if (!$existingTomorrow) {
                GoogleDataView::create([
                    'sku' => $skuKeyTomorrow,
                    'value' => $blankData
                ]);
                $this->info("Created blank Google Shopping utilization counts for {$tomorrow}");
            } else {
                $this->info("Tomorrow's data already exists for {$tomorrow}, skipping blank data creation");
            }

            $this->info("Google Shopping Utilization Counts:");
            $this->info("7UB Condition:");
            $this->info("  Over-utilized (UB7 > 90%): {$overUtilizedCount7ub}");
            $this->info("  Under-utilized (UB7 < 70%): {$underUtilizedCount7ub}");
            $this->info("7UB + 1UB Condition:");
            $this->info("  Over-utilized (UB7 > 90% AND UB1 > 90%): {$overUtilizedCount7ub1ub}");
            $this->info("  Under-utilized (UB7 < 70% AND UB1 < 70%): {$underUtilizedCount7ub1ub}");

            Log::info("Google Shopping Utilization Counts Stored", [
                'date' => $today,
                'over_utilized_7ub' => $overUtilizedCount7ub,
                'under_utilized_7ub' => $underUtilizedCount7ub,
                'over_utilized_7ub_1ub' => $overUtilizedCount7ub1ub,
                'under_utilized_7ub_1ub' => $underUtilizedCount7ub1ub
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error storing Google Shopping utilization counts: " . $e->getMessage());
            Log::error("Error storing Google Shopping utilization counts", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}

