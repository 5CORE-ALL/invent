<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\PushesAmazonAdsUpdatesInChunks;
use App\Http\Controllers\Campaigns\AmazonSpBudgetController;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use Illuminate\Console\Command;
use App\Models\AmazonSpCampaignReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\Amazon\AmazonBidUtilizationService;
use App\Services\CronMonitor\CronExecutionContext;
use App\Support\AmazonAdsSbidRule;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;

class AutoUpdateAmazonPtBids extends Command
{
    use MonitorsCronExecution;
    use PushesAmazonAdsUpdatesInChunks;

    protected $signature = 'amazon:auto-update-over-pt-bids
        {--dry-run : Show what would be updated without calling API}
        {--chunk= : Override chunk size for API updates (default from cron-monitor config)}';
    protected $description = 'Automatically update Amazon campaign product-target bids';

    const MAX_RETRY_ATTEMPTS = 5;
    const RETRY_DELAY_SECONDS = 5;

    protected string $monitorJobName = 'Amazon Bid Sync (PT Over)';

    protected $profileId;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeBidUpdate($m),
            $this->monitorJobName
        );
    }

    protected function executeBidUpdate(CronExecutionContext $monitor): int
    {
        try {
            $dryRun = $this->option('dry-run');
            $this->info("Starting Amazon PT bids auto-update..." . ($dryRun ? " [DRY RUN - no API calls]" : ""));

            try {
                DB::connection()->getPdo();
                $this->info("✓ Database connection OK");
                DB::connection()->disconnect();
            } catch (\Exception $e) {
                $this->error("✗ Database connection failed: " . $e->getMessage());
                $monitor->classifyAndRecord($e);
                return self::FAILURE;
            }

            $controller = new AmazonSpBudgetController;
            $campaigns = $this->getAutomateAmzUtilizedBgtPt();

            if (empty($campaigns)) {
                $this->warn("No campaigns matched filter conditions.");
                $monitor->markApiConnected();
                $monitor->setExpected(0);
                return self::SUCCESS;
            }

            $campaignBudgetMap = [];
            $skippedCampaigns = [];

            foreach ($campaigns as $index => $campaign) {
                $campaignId = $campaign->campaign_id ?? '';
                $sbid = $campaign->sbid ?? 0;
                $campaignName = $campaign->campaignName ?? '';

                if (empty($campaignId) || $sbid <= 0) {
                    $skippedCampaigns[] = [
                        'campaign_id' => $campaignId,
                        'campaign_name' => $campaignName,
                        'reason' => empty($campaignId) ? 'Missing campaign_id' : 'Invalid bid',
                    ];
                    continue;
                }

                if (!isset($campaignBudgetMap[$campaignId])) {
                    $campaignBudgetMap[$campaignId] = $sbid;
                } else {
                    $skippedCampaigns[] = [
                        'campaign_id' => $campaignId,
                        'campaign_name' => $campaignName,
                        'reason' => 'Duplicate campaign_id',
                    ];
                }
            }

            if ($campaignBudgetMap === []) {
                $this->warn("No valid campaign IDs found to update.");
                $monitor->setExpected(0);
                return self::SUCCESS;
            }

            $this->info("Found " . count($campaignBudgetMap) . " unique campaigns to update.");
            foreach ($campaignBudgetMap as $cid => $bid) {
                $this->line("  {$cid} => {$bid}");
            }

            if ($dryRun) {
                $this->warn("DRY RUN: No API call made. Remove --dry-run to apply updates.");
                $monitor->mergeMeta(['dry_run' => true]);
                $monitor->markApiConnected();
                $n = count($campaignBudgetMap);
                $monitor->setExpected($n);
                $monitor->setFetched($n);
                $monitor->setSkipped($n);
                return self::SUCCESS;
            }

            $monitor->markApiConnected();
            $stats = $this->pushAmazonAdsIdMapInChunks(
                $monitor,
                $campaignBudgetMap,
                fn (array $ids, array $bids) => $controller->updateAutoCampaignTargetsBid($ids, $bids)
            );

            foreach ($stats['updated_map'] as $cid => $bid) {
                if ($bid !== null) {
                    AmazonBidUtilizationService::persistSpSbidM((string) $cid, (float) $bid);
                }
            }

            if (!empty($skippedCampaigns)) {
                $this->warn("Pre-filter skipped: " . count($skippedCampaigns));
            }

            $this->info("FINAL PT: Total={$stats['total']} | Updated={$stats['updated']} | Skipped={$stats['skipped']} | Failed={$stats['failed']} | Chunks={$stats['chunks']}");

            return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("✗ Error occurred: " . $e->getMessage());
            $monitor->classifyAndRecord($e);
            return self::FAILURE;
        } finally {
            DB::connection()->disconnect();
        }
    }

    public function getAutomateAmzUtilizedBgtPt()
    {
        try {
            $sbidRule = AmazonAdsSbidRule::resolvedRule();
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            if ($productMasters->isEmpty()) {
                $this->warn("No product masters found in database!");
                $this->warn("No ProductMaster records found");
                return [];
            }

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
            
            if (empty($skus)) {
                $this->warn("No valid SKUs found!");
                return [];
            }

            $shopifyData = [];
            $amazonDatasheets = [];
            
            if (!empty($skus)) {
                $shopifyData = ShopifySku::mapByProductSkus($skus);
                $amazonDatasheets = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
                    return strtoupper($item->sku);
                });
            }

            // For PARENT rows: INV = sum of child SKUs' INV (same as BgtKw/BgtPt)
            $childInvSumByParent = [];
            foreach ($productMasters as $pm) {
                $norm = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $pm->sku ?? '');
                $norm = preg_replace('/\s+/', ' ', $norm);
                $skuUpper = strtoupper(trim($norm));
                if (stripos($skuUpper, 'PARENT') !== false) {
                    continue;
                }
                $p = $pm->parent ?? '';
                if ($p === '') {
                    continue;
                }
                $shopifyChild = $shopifyData[$pm->sku] ?? null;
                $inv = ($shopifyChild && isset($shopifyChild->inv)) ? (int) $shopifyChild->inv : 0;
                if (!isset($childInvSumByParent[$p])) {
                    $childInvSumByParent[$p] = 0;
                }
                $childInvSumByParent[$p] += $inv;
            }

            // For PARENT rows: avg price = average of child SKUs' prices (from amazon datashheet, or ProductMaster Values as fallback)
            $childPricesByParent = [];
            foreach ($productMasters as $pmChild) {
                $norm = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $pmChild->sku ?? '');
                $norm = preg_replace('/\s+/', ' ', $norm);
                $skuUpper = strtoupper(trim($norm));
                if (stripos($skuUpper, 'PARENT') !== false) {
                    continue;
                }
                $p = $pmChild->parent ?? '';
                if ($p === '') {
                    continue;
                }
                $amazonSheetChild = $amazonDatasheets[$skuUpper] ?? null;
                $childPrice = ($amazonSheetChild && isset($amazonSheetChild->price) && (float)$amazonSheetChild->price > 0)
                    ? (float)$amazonSheetChild->price
                    : null;
                if ($childPrice === null) {
                    $values = $pmChild->Values;
                    if (is_string($values)) {
                        $values = json_decode($values, true) ?: [];
                    } elseif (is_object($values)) {
                        $values = (array) $values;
                    } else {
                        $values = is_array($values) ? $values : [];
                    }
                    $childPrice = isset($values['msrp']) && (float)$values['msrp'] > 0
                        ? (float)$values['msrp']
                        : (isset($values['map']) && (float)$values['map'] > 0 ? (float)$values['map'] : null);
                }
                if ($childPrice !== null && $childPrice > 0) {
                    $normParent = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $p ?? ''))));
                    $normParent = rtrim($normParent, '.');
                    if (!isset($childPricesByParent[$normParent])) {
                        $childPricesByParent[$normParent] = [];
                    }
                    $childPricesByParent[$normParent][] = $childPrice;
                }
            }
            $avgPriceByParent = [];
            $avgPriceByParentCanonical = [];
            foreach ($childPricesByParent as $pk => $prices) {
                $avg = count($prices) > 0 ? round(array_sum($prices) / count($prices), 2) : 0;
                $avgPriceByParent[$pk] = $avg;
                $canonical = preg_replace('/\s+/', '', $pk);
                if ($canonical !== '' && $avg > 0) {
                    $avgPriceByParentCanonical[$canonical] = $avg;
                }
            }

        $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->get();

        $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L1')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->get();

        // L2 = 2nd last date from table (table's latest date - 1 day), not server today
        $latestDateInTable = DB::table('amazon_sp_campaign_reports')
            ->where('ad_type', 'SPONSORED_PRODUCTS')
            ->whereRaw("report_date_range REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'")
            ->max('report_date_range');
        $dayBeforeYesterdayForL2 = $latestDateInTable
            ? date('Y-m-d', strtotime($latestDateInTable . ' -1 day'))
            : date('Y-m-d', strtotime('-2 days'));
        $amazonSpCampaignReportsL2 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', $dayBeforeYesterdayForL2)
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->get();

        $result = [];
        $processedCampaignIds = []; // Track to avoid processing same campaign multiple times

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);

            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                // Normalize spaces: replace multiple spaces with single space
                $cleanName = preg_replace('/\s+/', ' ', strtoupper(trim($item->campaignName)));
                $cleanSku = preg_replace('/\s+/', ' ', $sku);
                
                return (
                    (str_ends_with($cleanName, $cleanSku . ' PT') || str_ends_with($cleanName, $cleanSku . ' PT.'))
                    && strtoupper($item->campaignStatus) === 'ENABLED'
                );
            });

            $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
                $cleanName = preg_replace('/\s+/', ' ', strtoupper(trim($item->campaignName)));
                $cleanSku = preg_replace('/\s+/', ' ', $sku);
                return (
                    (str_ends_with($cleanName, $cleanSku . ' PT') || str_ends_with($cleanName, $cleanSku . ' PT.'))
                    && strtoupper($item->campaignStatus) === 'ENABLED'
                );
            });

            $matchedCampaignL2 = $amazonSpCampaignReportsL2->first(function ($item) use ($sku) {
                $cleanName = preg_replace('/\s+/', ' ', strtoupper(trim($item->campaignName)));
                $cleanSku = preg_replace('/\s+/', ' ', $sku);
                return (
                    (str_ends_with($cleanName, $cleanSku . ' PT') || str_ends_with($cleanName, $cleanSku . ' PT.'))
                    && strtoupper($item->campaignStatus) === 'ENABLED'
                );
            });

            if (!$matchedCampaignL7 && !$matchedCampaignL1) {
                continue;
            }

            // Skip if we've already processed this campaign ID (avoid duplicates)
            $campaignId = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
            if (!empty($campaignId) && isset($processedCampaignIds[$campaignId])) {
                continue;
            }
            $processedCampaignIds[$campaignId] = true;

            $row = [];
            // INV: for PARENT SKU use sum of children's INV; for child use shopify inv
            $row['INV'] = (stripos($pm->sku ?? '', 'PARENT') !== false)
                ? (int) ($childInvSumByParent[$pm->parent ?? $pm->sku ?? ''] ?? 0)
                : (int) ($shopify->inv ?? 0);
            $row['campaign_id'] = $campaignId;
            $row['campaignName'] = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');
            $row['campaignStatus'] = strtoupper(trim($matchedCampaignL7->campaignStatus ?? ($matchedCampaignL1->campaignStatus ?? 'PAUSED')));
            // Keep utilization budget stable across windows so command-side UB matches frontend behavior.
            // Prefer the higher non-zero budget from recent windows.
            $budgetCandidates = [
                floatval($matchedCampaignL2->campaignBudgetAmount ?? 0),
                floatval($matchedCampaignL1->campaignBudgetAmount ?? 0),
                floatval($matchedCampaignL7->campaignBudgetAmount ?? 0),
            ];
            $budgetCandidates = array_values(array_filter($budgetCandidates, function ($v) {
                return $v > 0;
            }));
            $utilizationBudget = !empty($budgetCandidates) ? max($budgetCandidates) : 0;
            $row['campaignBudgetAmount'] = $utilizationBudget;
            $row['utilization_budget'] = $utilizationBudget;
            $row['l7_spend'] = $matchedCampaignL7->spend ?? 0;
            $row['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
            $row['l1_spend'] = $matchedCampaignL1->spend ?? 0;
            $row['l1_cpc'] = $matchedCampaignL1->costPerClick ?? 0;
            $row['l2_spend'] = $matchedCampaignL2 ? ($matchedCampaignL2->spend ?? 0) : 0;
            $row['l2_cpc'] = $matchedCampaignL2 ? ($matchedCampaignL2->costPerClick ?? 0) : 0;

            // Get price from AmazonDatasheet
            $amazonSheet = $amazonDatasheets[strtoupper($pm->sku)] ?? null;
            $price = ($amazonSheet && isset($amazonSheet->price)) ? floatval($amazonSheet->price) : 0;
            // For parent SKU rows: use average of child SKUs' prices when direct price is 0
            if (($price === 0 || $price === null) && stripos($pm->sku ?? '', 'PARENT') !== false && !empty($avgPriceByParent)) {
                $normSku = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $sku))));
                $normSku = rtrim($normSku, '.');
                $normParentKey = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $pm->parent ?? ''))));
                $normParentKey = rtrim($normParentKey, '.');
                $price = $avgPriceByParent[$normSku] ?? $avgPriceByParent[$normParentKey] ?? $avgPriceByParent[$pm->parent ?? ''] ?? $avgPriceByParent[rtrim($pm->parent ?? '', '.')] ?? 0;
                if ($price === 0 && !empty($avgPriceByParentCanonical)) {
                    $canonicalSku = preg_replace('/\s+/', '', $normSku);
                    $canonicalParentKey = preg_replace('/\s+/', '', $normParentKey);
                    $price = $avgPriceByParentCanonical[$canonicalSku] ?? $avgPriceByParentCanonical[$canonicalParentKey] ?? 0;
                }
                if ($price === 0) {
                    $parentValues = $pm->Values;
                    if (is_string($parentValues)) {
                        $parentValues = json_decode($parentValues, true) ?: [];
                    } elseif (is_object($parentValues)) {
                        $parentValues = (array) $parentValues;
                    } else {
                        $parentValues = is_array($parentValues) ? $parentValues : [];
                    }
                    $price = (isset($parentValues['msrp']) && (float)$parentValues['msrp'] > 0)
                        ? (float)$parentValues['msrp']
                        : (isset($parentValues['map']) && (float)$parentValues['map'] > 0 ? (float)$parentValues['map'] : 0);
                }
            }
            $price = (float) ($price ?? 0);

            // Calculate avg_cpc (lifetime average from daily records)
            $avgCpc = 0;
            try {
                $avgCpcRecord = DB::table('amazon_sp_campaign_reports')
                    ->select(DB::raw('AVG(costPerClick) as avg_cpc'))
                    ->where('campaign_id', $campaignId)
                    ->where('ad_type', 'SPONSORED_PRODUCTS')
                    ->where('campaignStatus', '!=', 'ARCHIVED')
                    ->where('report_date_range', 'REGEXP', '^[0-9]{4}-[0-9]{2}-[0-9]{2}$')
                    ->where('costPerClick', '>', 0)
                    ->whereNotNull('campaign_id')
                    ->first();
                
                if ($avgCpcRecord && $avgCpcRecord->avg_cpc > 0) {
                    $avgCpc = floatval($avgCpcRecord->avg_cpc);
                }
            } catch (\Exception $e) {
                // Continue without avg_cpc if there's an error
            }

            $budget = floatval($row['utilization_budget'] ?? $row['campaignBudgetAmount'] ?? 0);
            $l7_spend = floatval($row['l7_spend']);
            $l1_spend = floatval($row['l1_spend']);

            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            $ub1 = $budget > 0 ? ($l1_spend / $budget) * 100 : 0;
            $l2_spend = floatval($row['l2_spend'] ?? 0);
            $ub2 = AmazonBidUtilizationService::ub2PercentFromL2Spend($budget, $l2_spend);

            $l1_cpc = floatval($row['l1_cpc']);
            $l2_cpc = floatval($row['l2_cpc'] ?? 0);
            $l7_cpc = floatval($row['l7_cpc']);
            $fbL1 = $matchedCampaignL1 ? (float) ($matchedCampaignL1->costPerClick ?? 0) : 0.0;
            $fbL7 = $matchedCampaignL7 ? (float) ($matchedCampaignL7->costPerClick ?? 0) : 0.0;
            $cpcFallback = ($l1_cpc <= 0 && $l2_cpc <= 0 && $l7_cpc <= 0) ? max($fbL1, $fbL7) : null;
            if ($cpcFallback === null || $cpcFallback <= 0) {
                $cpcFallback = null;
            }

            $bidOut = AmazonBidUtilizationService::sbidFromUb2Ub1Cpc(
                $ub7,
                $ub1,
                $l1_cpc,
                $l2_cpc,
                $l7_cpc,
                $cpcFallback
            );
            $row['sbid'] = $bidOut['sbid'];
            $row['ub2'] = $ub2;

            if (empty($row['campaign_id'])) {
                continue;
            }
            if ($row['sbid'] === null || ! is_numeric($row['sbid']) || $row['sbid'] <= 0) {
                continue;
            }

            $bothRed = AmazonAdsSbidRule::isBothBelowUtilLow($ub7, $ub1, $sbidRule);
            $bothPink = AmazonAdsSbidRule::isBothAboveUtilHigh($ub7, $ub1, $sbidRule);

            // Include when U7/U1 both red or both pink and band matches (same gating as over-KW / HL)
            if ($row['INV'] > 0 && ($row['campaignStatus'] ?? '') === 'ENABLED'
                && (($bothRed && $bidOut['band'] === 'under') || ($bothPink && $bidOut['band'] === 'over'))) {
                $result[] = (object) $row;
            }
        }

            DB::connection()->disconnect();
            return $result;
        
        } catch (\Exception $e) {
            $this->error("Error in getAutomateAmzUtilizedBgtPt: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return [];
        } finally {
            DB::connection()->disconnect();
        }
    }

}