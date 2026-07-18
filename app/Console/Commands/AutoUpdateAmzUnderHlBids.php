<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\PushesAmazonAdsUpdatesInChunks;
use App\Http\Controllers\Campaigns\AmazonSbBudgetController;
use App\Http\Controllers\Campaigns\AmazonSpBudgetController;
use App\Models\AmazonDataView;
use App\Models\AmazonSbCampaignReport;
use Illuminate\Console\Command;
use App\Models\AmazonSpCampaignReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use GuzzleHttp\Client;
use App\Services\Amazon\AmazonBidUtilizationService;
use App\Services\CronMonitor\CronExecutionContext;
use App\Support\AmazonAdsSbidRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoUpdateAmzUnderHlBids extends Command
{
    use MonitorsCronExecution;
    use PushesAmazonAdsUpdatesInChunks;

    protected $signature = 'amazon:auto-update-under-hl-bids
        {--dry-run : Show what would be updated without calling API}
        {--chunk= : Override chunk size for API updates (default from cron-monitor config)}';
    protected $description = 'Automatically update Amazon campaign hl bids';

    const MAX_RETRY_ATTEMPTS = 5;
    const RETRY_DELAY_SECONDS = 5;

    protected string $monitorJobName = 'Amazon Bid Sync (HL Under)';

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
            $this->info("Starting Amazon Under-Utilized HL bids auto-update..." . ($dryRun ? " [DRY RUN - no API calls]" : ""));

            try {
                DB::connection()->getPdo();
                $this->info("✓ Database connection OK");
                DB::connection()->disconnect();
            } catch (\Exception $e) {
                $this->error("✗ Database connection failed: " . $e->getMessage());
                $monitor->classifyAndRecord($e);

                return self::FAILURE;
            }

            $updateKwBids = new AmazonSbBudgetController;
            $campaigns = $this->getAutomateAmzUtilizedBgtHl();

            if (empty($campaigns)) {
                $this->warn("No campaigns matched filter conditions.");
                $monitor->markApiConnected();
                $monitor->setExpected(0);

                return self::SUCCESS;
            }

            // Require campaign id + numeric proposed bid (same shape as over HL job)
            $validCampaigns = collect($campaigns)->filter(function ($campaign) {
                return ! empty($campaign->campaign_id)
                    && isset($campaign->sbid)
                    && is_numeric($campaign->sbid)
                    && floatval($campaign->sbid) > 0;
            })->values();

            if ($validCampaigns->isEmpty()) {
                $this->warn("No valid campaigns found (missing campaign_id or invalid bid).");
                $monitor->setExpected(0);

                return self::SUCCESS;
            }

            $apiCampaigns = $validCampaigns->filter(function ($campaign) {
                return (int) ($campaign->INV ?? 0) > 0;
            })->values();

            $this->info("Found " . $validCampaigns->count() . " under-utilized HL campaign(s) (" . $apiCampaigns->count() . " eligible for Amazon API; " . ($validCampaigns->count() - $apiCampaigns->count()) . " INV=0 — persist sbid_m only).");
            $this->line("");

            $this->info("========================================");
            $this->info("CAMPAIGNS TO UPDATE (UNDER-UTILIZED HL):");
            $this->info("========================================");
            foreach ($validCampaigns as $campaign) {
                $campaignName = $campaign->campaignName ?? 'N/A';
                $newBid = $campaign->sbid ?? 0;
                $campaignId = $campaign->campaign_id ?? '';
                $budget = floatval($campaign->campaignBudgetAmount ?? 0);
                $l7_spend = floatval($campaign->l7_spend ?? 0);
                $l1_spend = floatval($campaign->l1_spend ?? 0);
                $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
                $ub1 = $budget > 0 ? ($l1_spend / $budget) * 100 : 0;
                $inv = (int)($campaign->INV ?? 0);
                $this->info("Campaign Name: {$campaignName}");
                $this->info("  - Campaign ID: {$campaignId}");
                $this->info("  - Bid: {$newBid}");
                $this->info("  - 7UB: " . round($ub7, 2) . "% | 1UB: " . round($ub1, 2) . "%");
                $this->info("  - INV: {$inv}" . ($inv <= 0 ? ' (API update skipped — persist sbid_m only)' : ''));
                $this->info("---");
            }
            $this->info("========================================");
            $this->line("");

            if ($dryRun) {
                $this->newLine();
                $this->warn("DRY RUN: No API call made. Remove --dry-run to apply updates.");
                $this->info("✓ Dry run completed. Total campaigns that would be updated: " . $validCampaigns->count());
                $monitor->mergeMeta(['dry_run' => true]);
                $monitor->markApiConnected();
                $monitor->setExpected($validCampaigns->count());
                $monitor->setFetched($validCampaigns->count());
                $monitor->setSkipped($validCampaigns->count());

                return self::SUCCESS;
            }

            // INV=0 rows: persist sbid_m only (no Amazon API)
            $invZeroMap = [];
            foreach ($validCampaigns as $campaign) {
                if ((int) ($campaign->INV ?? 0) <= 0) {
                    $cid = (string) ($campaign->campaign_id ?? '');
                    if ($cid !== '' && ! isset($invZeroMap[$cid])) {
                        $invZeroMap[$cid] = (float) ($campaign->sbid ?? 0);
                    }
                }
            }

            if ($apiCampaigns->isEmpty()) {
                $this->warn("No campaigns with INV > 0 — skipping Amazon API; persisting sbid_m to L30 for all " . $validCampaigns->count() . " row(s).");
                $persistedRows = 0;
                foreach ($validCampaigns as $campaign) {
                    $persistedRows += AmazonBidUtilizationService::persistSbSbidM((string) ($campaign->campaign_id ?? ''), (float) ($campaign->sbid ?? 0));
                }
                Log::info('amazon:auto-update-under-hl-bids persisted sbid_m only (INV=0 for all)', [
                    'campaigns' => $validCampaigns->count(),
                    'l30_rows_updated' => $persistedRows,
                ]);
                $this->info("✓ sbid_m persisted ({$persistedRows} L30 row updates).");
                $monitor->markApiConnected();
                $monitor->setExpected($validCampaigns->count());
                $monitor->setFetched($validCampaigns->count());
                $monitor->setSkipped($validCampaigns->count());

                return self::SUCCESS;
            }

            $campaignBudgetMap = [];
            foreach ($apiCampaigns as $campaign) {
                $cid = $campaign->campaign_id ?? '';
                $sbid = $campaign->sbid ?? 0;
                if (! empty($cid) && is_numeric($sbid) && $sbid > 0 && ! isset($campaignBudgetMap[$cid])) {
                    $campaignBudgetMap[$cid] = $sbid;
                }
            }

            if ($campaignBudgetMap === []) {
                $this->warn("No valid campaigns found for Amazon API.");
                $monitor->setExpected(0);

                return self::SUCCESS;
            }

            $monitor->markApiConnected();
            $stats = $this->pushAmazonAdsIdMapInChunks(
                $monitor,
                $campaignBudgetMap,
                fn (array $ids, array $bids) => $updateKwBids->updateAutoCampaignSbKeywordsBid($ids, $bids)
            );

            $persistedRows = 0;
            foreach ($stats['updated_map'] as $cid => $bid) {
                if ($bid !== null) {
                    $persistedRows += AmazonBidUtilizationService::persistSbSbidM((string) $cid, (float) $bid);
                }
            }
            // Preserve INV=0 persist-only path for rows not sent to the API
            foreach ($invZeroMap as $cid => $bid) {
                $persistedRows += AmazonBidUtilizationService::persistSbSbidM((string) $cid, (float) $bid);
            }

            Log::info('amazon:auto-update-under-hl-bids persisted sbid_m to L30', [
                'api_success' => count($stats['updated_map']),
                'inv_zero' => count($invZeroMap),
                'l30_rows_updated' => $persistedRows,
            ]);

            $this->info("FINAL: Total={$stats['total']} | Updated={$stats['updated']} | Skipped={$stats['skipped']} | Failed={$stats['failed']} | Chunks={$stats['chunks']}");

            return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("✗ Error in handle: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            $monitor->classifyAndRecord($e);

            return self::FAILURE;
        } finally {
            DB::connection()->disconnect();
        }
    }

    public function getAutomateAmzUtilizedBgtHl()
    {
        try {
            $sbidRule = AmazonAdsSbidRule::resolvedRule();
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            if ($productMasters->isEmpty()) {
                $this->warn("No product masters found in database!");
                return [];
            }

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

            if (empty($skus)) {
                $this->warn("No valid SKUs found!");
                return [];
            }

            $shopifyData = [];

            $normalizeSku = function ($s) {
                if ($s === null || $s === '') return '';
                $s = preg_replace('/\s+/', ' ', trim((string) $s));
                $s = preg_replace('/\s+2\s+PCS\b/i', ' 2PCS', $s);
                return $s;
            };

            if (!empty($skus)) {
                $shopifyData = ShopifySku::mapByProductSkus($skus);
            }

        $amazonSpCampaignReportsL7 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->get();

        $amazonSpCampaignReportsL1 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L1')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->get();

        $latestDateSb = DB::table('amazon_sb_campaign_reports')
            ->where('ad_type', 'SPONSORED_BRANDS')
            ->whereRaw("report_date_range REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'")
            ->max('report_date_range');
        $l2DateSb = $latestDateSb
            ? date('Y-m-d', strtotime($latestDateSb.' -1 day'))
            : date('Y-m-d', strtotime('-2 days'));
        $amazonSpCampaignReportsL2 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', $l2DateSb)
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->get();

        $amazonSpCampaignReportsL30 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->get();

        $result = [];
        $processedCampaignIds = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);

            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                $cleanName = preg_replace('/\s+/', ' ', strtoupper(trim($item->campaignName)));
                $cleanSku = preg_replace('/\s+/', ' ', $sku);
                $expected1 = $cleanSku;
                $expected2 = $cleanSku . ' HEAD';

                return ($cleanName === $expected1 || $cleanName === $expected2);
            });

            $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
                $cleanName = preg_replace('/\s+/', ' ', strtoupper(trim($item->campaignName)));
                $cleanSku = preg_replace('/\s+/', ' ', $sku);
                $expected1 = $cleanSku;
                $expected2 = $cleanSku . ' HEAD';

                return ($cleanName === $expected1 || $cleanName === $expected2);
            });

            $matchedCampaignL2 = $amazonSpCampaignReportsL2->first(function ($item) use ($sku) {
                $cleanName = preg_replace('/\s+/', ' ', strtoupper(trim($item->campaignName)));
                $cleanSku = preg_replace('/\s+/', ' ', $sku);
                $expected1 = $cleanSku;
                $expected2 = $cleanSku . ' HEAD';

                return ($cleanName === $expected1 || $cleanName === $expected2);
            });

            $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                $cleanName = preg_replace('/\s+/', ' ', strtoupper(trim($item->campaignName)));
                $cleanSku = preg_replace('/\s+/', ' ', $sku);
                $expected1 = $cleanSku;
                $expected2 = $cleanSku . ' HEAD';

                return ($cleanName === $expected1 || $cleanName === $expected2);
            });

            if (!$matchedCampaignL7 && !$matchedCampaignL1) {
                continue;
            }

            $campaignId = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
            if (! empty($campaignId) && isset($processedCampaignIds[$campaignId])) {
                continue;
            }
            if (! empty($campaignId)) {
                $processedCampaignIds[$campaignId] = true;
            }

            $row = [];
            $row['INV'] = (int) (($shopify?->inv) ?? 0);
            $row['campaign_id'] = $campaignId;
            $row['campaignName'] = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');
            $row['campaignStatus'] = strtoupper(trim($matchedCampaignL7->campaignStatus ?? ($matchedCampaignL1->campaignStatus ?? 'PAUSED')));
            // Align HL budget source with frontend preference (L30 first, then L7/L1 fallback).
            $budgetCandidates = [
                floatval(($matchedCampaignL30 ? $matchedCampaignL30->campaignBudgetAmount : null) ?? 0),
                floatval($matchedCampaignL2 ? ($matchedCampaignL2->campaignBudgetAmount ?? 0) : 0),
                floatval($matchedCampaignL7->campaignBudgetAmount ?? 0),
                floatval($matchedCampaignL1->campaignBudgetAmount ?? 0),
            ];
            $budgetCandidates = array_values(array_filter($budgetCandidates, function ($v) {
                return $v > 0;
            }));
            $utilizationBudget = !empty($budgetCandidates) ? $budgetCandidates[0] : 0;
            $row['campaignBudgetAmount'] = $utilizationBudget;
            $row['utilization_budget'] = $utilizationBudget;
            $row['l7_spend'] = $matchedCampaignL7->cost ?? 0;

            $costPerClick7 = ($matchedCampaignL7 && $matchedCampaignL7->clicks > 0)
                ? ($matchedCampaignL7->cost / $matchedCampaignL7->clicks)
                : 0;

            $costPerClick1 = ($matchedCampaignL1 && $matchedCampaignL1->clicks > 0)
                ? ($matchedCampaignL1->cost / $matchedCampaignL1->clicks)
                : 0;

            $row['l7_cpc']   = $costPerClick7;
            $row['l1_spend'] = $matchedCampaignL1->cost ?? 0;
            $row['l1_cpc']   = $costPerClick1;
            $row['l2_spend'] = $matchedCampaignL2 ? (float) ($matchedCampaignL2->cost ?? 0) : 0;
            $row['l2_cpc'] = ($matchedCampaignL2 && ($matchedCampaignL2->clicks ?? 0) > 0)
                ? ((float) ($matchedCampaignL2->cost ?? 0) / (float) $matchedCampaignL2->clicks)
                : 0.0;

            // Calculate avg_cpc (lifetime average from daily records)
            $campaignId = $row['campaign_id'];
            $avgCpc = 0;
            try {
                $avgCpcRecord = DB::table('amazon_sb_campaign_reports')
                    ->select(DB::raw('AVG(CASE WHEN clicks > 0 THEN cost / clicks ELSE 0 END) as avg_cpc'))
                    ->where('campaign_id', $campaignId)
                    ->where('ad_type', 'SPONSORED_BRANDS')
                    ->where(function ($q) {
                        $q->whereNull('campaignStatus')->orWhere('campaignStatus', '!=', 'ARCHIVED');
                    })
                    ->where('report_date_range', 'REGEXP', '^[0-9]{4}-[0-9]{2}-[0-9]{2}$')
                    ->whereNotNull('campaign_id')
                    ->first();
                
                if ($avgCpcRecord && $avgCpcRecord->avg_cpc > 0) {
                    $avgCpc = floatval($avgCpcRecord->avg_cpc);
                }
            } catch (\Exception $e) {
                // Continue without avg_cpc if there's an error
            }

            $l1_cpc = floatval($row['l1_cpc']);
            $l2_cpc = floatval($row['l2_cpc'] ?? 0);
            $l7_cpc = floatval($row['l7_cpc']);
            $budget = floatval($row['utilization_budget'] ?? $row['campaignBudgetAmount'] ?? 0);
            $l7_spend = floatval($row['l7_spend']);
            $l1_spend = floatval($row['l1_spend']);
            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            $ub1 = $budget > 0 ? ($l1_spend / $budget) * 100 : 0;

            $resolved = AmazonBidUtilizationService::resolveUb(
                (string) $row['campaign_id'],
                'hl',
                ['ub7' => $ub7, 'ub1' => $ub1]
            );
            $ub7 = $resolved['ub7'];
            $ub1 = $resolved['ub1'];
            $ubSource = $resolved['source'];

            $l2_spend = floatval($row['l2_spend'] ?? 0);
            $ub2 = AmazonBidUtilizationService::ub2PercentFromL2Spend($budget, $l2_spend);

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

            $row['INV'] = (int) ($row['INV'] ?? 0);
            $row['sbid'] = $bidOut['sbid'];
            $bothLowHl = AmazonAdsSbidRule::isBothBelowUtilLow($ub7, $ub1, $sbidRule);
            $bothHighHl = AmazonAdsSbidRule::isBothAboveUtilHigh($ub7, $ub1, $sbidRule);
            if ($row['campaignName'] !== '' && ($row['campaignStatus'] ?? '') === 'ENABLED'
                && $row['sbid'] !== null && $row['sbid'] > 0
                && (($bothLowHl && $bidOut['band'] === 'under') || ($bothHighHl && $bidOut['band'] === 'over'))) {
                AmazonBidUtilizationService::logBidDecision(
                    (string) $row['campaign_id'],
                    $bidOut['band'] === 'over' ? 'hl_over' : 'hl_under',
                    $ub1,
                    $l1_cpc > 0 ? $l1_cpc : ($l2_cpc > 0 ? $l2_cpc : ($l7_cpc > 0 ? $l7_cpc : 0.0)),
                    (float) $row['sbid'],
                    $ubSource
                );
                $result[] = (object) $row;
            }
            }

            DB::connection()->disconnect();
            return $result;
        
        } catch (\Exception $e) {
            $this->error("Error in getAutomateAmzUtilizedBgtHl: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return [];
        } finally {
            DB::connection()->disconnect();
        }
    }

}