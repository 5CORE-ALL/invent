<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Http\Controllers\Campaigns\GoogleAdsDateRangeTrait;
use App\Models\GoogleDataView;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\CronMonitor\CronExecutionContext;
use App\Support\GoogleShoppingCampaignsRawRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreGoogleShoppingUtilizationCounts extends Command
{
    use GoogleAdsDateRangeTrait;
    use MonitorsCronExecution;
    use ProcessesUpdatesInChunks;

    protected $signature = 'google:store-shopping-utilization-counts
        {--chunk= : Override chunk size (default from cron-monitor config)}';

    protected $description = 'Store daily counts of over/under utilized Google Shopping campaigns';

    protected string $monitorJobName = 'Google Shopping Store Utilization Counts';

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeStore($m),
            $this->monitorJobName
        );
    }

    protected function executeStore(CronExecutionContext $monitor): int
    {
        $this->info('Starting to store Google Shopping utilization counts...');
        $chunkSize = $this->monitoredChunkSize();

        try {
            $productMasters = collect();
            ProductMaster::query()
                ->orderBy('id')
                ->chunkById($chunkSize, function ($rows) use (&$productMasters) {
                    foreach ($rows as $row) {
                        $productMasters->push($row);
                    }
                });
            $productMasters = $productMasters->sortBy(function ($pm) {
                $parent = (string) ($pm->parent ?? '');
                $isParentSku = str_starts_with(strtoupper(trim($pm->sku ?? '')), 'PARENT ') ? '1' : '0';
                $sku = (string) ($pm->sku ?? '');

                return $parent."\0".$isParentSku."\0".$sku;
            })->values();

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
            $shopifyData = ShopifySku::mapByProductSkus($skus);

            $dateRanges = $this->calculateDateRanges();

            $rawRule = GoogleShoppingCampaignsRawRule::resolvedRule();
            $ubOver = (float) $rawRule['sbid']['util_high'];
            $ubUnder = (float) $rawRule['sbid']['util_low'];

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

            $monitor->setFetched($uniqueCampaignIds->count());
            $monitor->setExpected($uniqueCampaignIds->count());

            foreach ($uniqueCampaignIds->chunk($chunkSize) as $idChunk) {
                foreach ($idChunk as $campaignId) {
                    $campaign = $campaignMap[$campaignId];
                    $campaignName = $campaign->campaign_name;

                    $matchedSku = null;
                    $matchedPm = null;

                    foreach ($productMasters as $pm) {
                        $sku = strtoupper(trim($pm->sku));
                        $campaignUpper = strtoupper(trim($campaignName));
                        $campaignUpperCleaned = rtrim($campaignUpper, '.');

                        $parts = array_map(function ($part) {
                            return rtrim(trim($part), '.');
                        }, explode(',', $campaignUpperCleaned));
                        $skuTrimmed = strtoupper(trim($sku));
                        $exactMatch = in_array($skuTrimmed, $parts);

                        if (! $exactMatch) {
                            $exactMatch = $campaignUpperCleaned === $skuTrimmed;
                        }

                        if ($exactMatch) {
                            $matchedSku = $pm->sku;
                            $matchedPm = $pm;
                            break;
                        }
                    }

                    $inv = 0;
                    if ($matchedPm) {
                        $shopify = $shopifyData[$matchedPm->sku] ?? null;
                        $inv = $shopify->inv ?? 0;
                    }

                    if (floatval($inv) <= 0) {
                        continue;
                    }

                    $latestCampaign = $googleCampaigns->where('campaign_id', $campaignId)
                        ->sortByDesc('date')
                        ->first();
                    $budget = $latestCampaign && $latestCampaign->budget_amount_micros
                        ? $latestCampaign->budget_amount_micros / 1000000
                        : 0;

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

                    $ub7 = $budget > 0 ? ($spend_L7 / ($budget * 7)) * 100 : 0;
                    $ub1 = $budget > 0 ? ($spend_L1 / ($budget * 1)) * 100 : 0;

                    if (! isset($result[$campaignId])) {
                        $result[$campaignId] = [
                            'campaign_id' => $campaignId,
                            'ub7' => $ub7,
                            'ub1' => $ub1,
                        ];
                    }
                }
                $monitor->incrementProcessed($idChunk->count());
            }

            $overUtilizedCount7ub = 0;
            $underUtilizedCount7ub = 0;

            $overUtilizedCount7ub1ub = 0;
            $underUtilizedCount7ub1ub = 0;

            foreach ($result as $campaignData) {
                $ub7 = $campaignData['ub7'];
                $ub1 = $campaignData['ub1'];

                if ($ub7 > $ubOver) {
                    $overUtilizedCount7ub++;
                } elseif ($ub7 < $ubUnder) {
                    $underUtilizedCount7ub++;
                }

                if ($ub7 > $ubOver && $ub1 > $ubOver) {
                    $overUtilizedCount7ub1ub++;
                } elseif ($ub7 < $ubUnder && $ub1 < $ubUnder) {
                    $underUtilizedCount7ub1ub++;
                }
            }

            $today = now()->format('Y-m-d');
            $tomorrow = now()->copy()->addDay()->format('Y-m-d');

            $data = [
                'over_utilized_7ub' => $overUtilizedCount7ub,
                'under_utilized_7ub' => $underUtilizedCount7ub,
                'over_utilized_7ub_1ub' => $overUtilizedCount7ub1ub,
                'under_utilized_7ub_1ub' => $underUtilizedCount7ub1ub,
                'date' => $today,
            ];

            $blankData = [
                'over_utilized_7ub' => 0,
                'under_utilized_7ub' => 0,
                'over_utilized_7ub_1ub' => 0,
                'under_utilized_7ub_1ub' => 0,
                'date' => $tomorrow,
            ];

            $skuKeyToday = 'GOOGLE_SHOPPING_UTILIZATION_'.$today;
            $skuKeyTomorrow = 'GOOGLE_SHOPPING_UTILIZATION_'.$tomorrow;

            $existingToday = GoogleDataView::where('sku', $skuKeyToday)->first();

            if ($existingToday) {
                $existingToday->update(['value' => $data]);
                $this->info("Updated Google Shopping utilization counts for {$today}");
            } else {
                GoogleDataView::create([
                    'sku' => $skuKeyToday,
                    'value' => $data,
                ]);
                $this->info("Created Google Shopping utilization counts for {$today}");
            }
            $monitor->incrementUpdated(1);

            $existingTomorrow = GoogleDataView::where('sku', $skuKeyTomorrow)->first();

            if (! $existingTomorrow) {
                GoogleDataView::create([
                    'sku' => $skuKeyTomorrow,
                    'value' => $blankData,
                ]);
                $this->info("Created blank Google Shopping utilization counts for {$tomorrow}");
            } else {
                $this->info("Tomorrow's data already exists for {$tomorrow}, skipping blank data creation");
            }

            $this->info('Google Shopping Utilization Counts:');
            $this->info('7UB Condition:');
            $this->info("  Over-utilized (UB7 > 90%): {$overUtilizedCount7ub}");
            $this->info("  Under-utilized (UB7 < 70%): {$underUtilizedCount7ub}");
            $this->info('7UB + 1UB Condition:');
            $this->info("  Over-utilized (UB7 > 90% AND UB1 > 90%): {$overUtilizedCount7ub1ub}");
            $this->info("  Under-utilized (UB7 < 70% AND UB1 < 70%): {$underUtilizedCount7ub1ub}");

            Log::info('Google Shopping Utilization Counts Stored', [
                'date' => $today,
                'over_utilized_7ub' => $overUtilizedCount7ub,
                'under_utilized_7ub' => $underUtilizedCount7ub,
                'over_utilized_7ub_1ub' => $overUtilizedCount7ub1ub,
                'under_utilized_7ub_1ub' => $underUtilizedCount7ub1ub,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error storing Google Shopping utilization counts: '.$e->getMessage());
            Log::error('Error storing Google Shopping utilization counts', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $monitor->classifyAndRecord($e);

            return self::FAILURE;
        }
    }
}
