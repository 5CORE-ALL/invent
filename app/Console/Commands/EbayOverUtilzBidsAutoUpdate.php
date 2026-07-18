<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\PushesAmazonAdsUpdatesInChunks;
use App\Http\Controllers\Campaigns\EbayOverUtilizedBgtController;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use App\Models\EbayPriorityReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\CronMonitor\CronExecutionContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EbayOverUtilzBidsAutoUpdate extends Command
{
    use MonitorsCronExecution;
    use PushesAmazonAdsUpdatesInChunks;

    protected $signature = 'ebay:auto-update-over-bids
        {--dry-run : Show what would be updated without calling API}
        {--chunk= : Override chunk size for API updates (default from cron-monitor config)}';

    protected $description = 'Automatically update Ebay campaign keyword bids';

    /** Number of retry attempts for failed campaign updates (minimum 5 tries total for failures). */
    const MAX_RETRY_ATTEMPTS = 5;

    /** Seconds to wait between retry rounds for failed campaigns (rate-limit precaution). */
    const RETRY_DELAY_SECONDS = 5;

    protected string $monitorJobName = 'eBay Bid Sync (Over)';

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
            @ini_set('max_execution_time', 900);

            $dryRun = $this->option('dry-run');

            try {
                DB::connection()->getPdo();
                $this->info('✓ Database connection OK');
                DB::connection()->disconnect();
            } catch (\Exception $e) {
                $this->error('✗ Database connection failed: '.$e->getMessage());
                $monitor->classifyAndRecord($e);

                return self::FAILURE;
            }

            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('🚀 Starting eBay Over-Utilized Bids Auto-Update'.($dryRun ? ' [DRY RUN - no API calls]' : ''));
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            $updateOverUtilizedBids = new EbayOverUtilizedBgtController;

            $campaigns = $this->getEbayOverUtilizCampaign();

            if (empty($campaigns)) {
                $this->warn('⚠️  No campaigns matched filter conditions.');
                $monitor->markApiConnected();
                $monitor->setExpected(0);

                return self::SUCCESS;
            }

            $validCampaigns = array_filter($campaigns, function ($campaign) {
                return ! empty($campaign->campaign_id) && ! empty($campaign->sbid) && floatval($campaign->sbid) > 0;
            });

            if (empty($validCampaigns)) {
                $this->warn('⚠️  No valid campaigns found (all have empty campaign_id or zero/blank sbid).');
                $monitor->markApiConnected();
                $monitor->setExpected(0);

                return self::SUCCESS;
            }

            $this->info('📊 Found '.count($validCampaigns).' campaigns to update');
            $this->info('');

            $this->info('📋 Campaigns to be updated:');
            foreach ($validCampaigns as $index => $campaign) {
                $campaignName = $campaign->campaign_name ?? 'Unknown';
                $campaignId = $campaign->campaign_id ?? 'N/A';
                $newBid = $campaign->sbid ?? 0;
                $oldCpc = $campaign->l7_cpc ?? 0;

                $this->line('   '.($index + 1).". Campaign: {$campaignName} | ID: {$campaignId} | Old CPC: \${$oldCpc} | New Bid: \${$newBid}");
            }

            $this->info('');

            $campaignBidMap = [];
            foreach ($validCampaigns as $campaign) {
                $campaignBidMap[$campaign->campaign_id] = $campaign->sbid ?? 0;
            }

            if ($dryRun) {
                $this->newLine();
                $this->warn('DRY RUN: No API call made. Remove --dry-run to apply updates.');
                $this->info('✓ Dry run completed. Total campaigns that would be updated: '.count($campaignBidMap));
                $monitor->mergeMeta(['dry_run' => true]);
                $monitor->markApiConnected();
                $monitor->setExpected(count($campaignBidMap));
                $monitor->setFetched(count($campaignBidMap));
                $monitor->setSkipped(count($campaignBidMap));

                return self::SUCCESS;
            }

            $monitor->markApiConnected();
            $stats = $this->pushAmazonAdsIdMapInChunks(
                $monitor,
                $campaignBidMap,
                fn (array $ids, array $bids) => $this->normalizeEbayAdsPushResult(
                    $updateOverUtilizedBids->updateAutoKeywordsBidDynamic($ids, $bids),
                    $ids
                ),
                'ebay'
            );

            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info("FINAL: Total={$stats['total']} | Updated={$stats['updated']} | Skipped={$stats['skipped']} | Failed={$stats['failed']} | Chunks={$stats['chunks']}");
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            Log::info('ebay:auto-update-over-bids completed', $stats);

            return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('✗ Error occurred: '.$e->getMessage());
            $this->error('Stack trace: '.$e->getTraceAsString());
            $monitor->classifyAndRecord($e);

            return self::FAILURE;
        } finally {
            DB::connection()->disconnect();
        }
    }

    /**
     * Normalize eBay JsonResponse into Amazon-style chunk result for retries.
     *
     * @param  list<string>  $ids
     * @return array{status: int, failed: list<array>, skipped: list<array>}
     */
    private function normalizeEbayAdsPushResult($result, array $ids): array
    {
        if (is_object($result) && method_exists($result, 'getData')) {
            $result = $result->getData(true);
        }

        if (! is_array($result)) {
            return [
                'status' => 500,
                'failed' => array_map(
                    fn ($id) => ['campaign_id' => (string) $id, 'error' => 'Unexpected API result', 'status' => 500],
                    $ids
                ),
                'skipped' => [],
            ];
        }

        $httpStatus = (int) ($result['status'] ?? 500);
        $data = $result['data'] ?? [];
        $failedById = [];
        $seenIds = [];

        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }
            $campId = $item['campaign_id'] ?? null;
            if ($campId !== null) {
                $seenIds[(string) $campId] = true;
            }
            if (($item['status'] ?? '') === 'error') {
                $key = (string) ($campId ?? 'unknown');
                $failedById[$key] = [
                    'campaign_id' => $key,
                    'error' => $item['message'] ?? 'Unknown error',
                    'status' => $httpStatus > 0 ? $httpStatus : 500,
                ];
            }
        }

        $skipped = [];
        foreach ($ids as $id) {
            if (! isset($seenIds[(string) $id])) {
                $skipped[] = ['campaign_id' => (string) $id, 'reason' => 'No response row'];
            }
        }

        $failed = array_values($failedById);

        return [
            'status' => empty($failed) ? 200 : 207,
            'failed' => $failed,
            'skipped' => $skipped,
        ];
    }

    public function getEbayOverUtilizCampaign(){
        try {
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

            // SKU normalization function
            $normalizeSku = function ($sku) {
                $sku = trim($sku);
                $sku = preg_replace('/\s+/u', ' ', $sku);
                $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
                return strtoupper($sku);
            };

            $shopifyData = [];
            $nrValues = [];
            $ebayMetricData = [];

            if (!empty($skus)) {
                // Normalize ShopifySku data keys
                $shopifyRaw = ShopifySku::whereIn('sku', $skus)->get();
                $shopifyData = collect();
                foreach ($shopifyRaw as $item) {
                    $normalizedKey = $normalizeSku($item->sku);
                    $shopifyData[$normalizedKey] = $item;
                }
                
                $nrValues = EbayDataView::whereIn('sku', $skus)->pluck('value', 'sku');
                
                // Normalize EbayMetric data keys
                $ebayMetricRaw = EbayMetric::whereIn('sku', $skus)->get();
                $ebayMetricData = collect();
                foreach ($ebayMetricRaw as $item) {
                    $normalizedKey = $normalizeSku($item->sku);
                    $ebayMetricData[$normalizedKey] = $item;
                }
            }

        $ebayCampaignReportsL7 = EbayPriorityReport::where('report_range', 'L7')
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        $ebayCampaignReportsL1 = EbayPriorityReport::where('report_range', 'L1')
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        $ebayCampaignReportsL30 = EbayPriorityReport::where('report_range', 'L30')
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        // Calculate total ACOS from ALL RUNNING campaigns (L30 data)
        $allL30Campaigns = EbayPriorityReport::where('report_range', 'L30')
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get();

        $totalSpendAll = 0;
        $totalSalesAll = 0;

        foreach ($allL30Campaigns as $campaign) {
            $adFees = (float) str_replace(['USD ', ','], '', $campaign->cpc_ad_fees_payout_currency ?? '0');
            $sales = (float) str_replace(['USD ', ','], '', $campaign->cpc_sale_amount_payout_currency ?? '0');
            $totalSpendAll += $adFees;
            $totalSalesAll += $sales;
        }

        $totalACOSAll = $totalSalesAll > 0 ? ($totalSpendAll / $totalSalesAll) * 100 : 0;

        $result = [];

        foreach ($productMasters as $pm) {
            $normalizedSku = $normalizeSku($pm->sku);

            $shopify = $shopifyData[$normalizedSku] ?? null;

            $ebay = $ebayMetricData[$normalizedSku] ?? null;

            $matchedCampaignL7 = $ebayCampaignReportsL7->first(function ($item) use ($normalizedSku, $normalizeSku) {
                $campaignName = $normalizeSku(rtrim($item->campaign_name, '.'));
                return $campaignName === $normalizedSku;
            });

            $matchedCampaignL1 = $ebayCampaignReportsL1->first(function ($item) use ($normalizedSku, $normalizeSku) {
                $campaignName = $normalizeSku(rtrim($item->campaign_name, '.'));
                return $campaignName === $normalizedSku;
            });

            $matchedCampaignL30 = $ebayCampaignReportsL30->first(function ($item) use ($normalizedSku, $normalizeSku) {
                $campaignName = $normalizeSku(rtrim($item->campaign_name, '.'));
                return $campaignName === $normalizedSku;
            });

            if (!$matchedCampaignL7 && !$matchedCampaignL1 && !$matchedCampaignL30) {
                continue;
            }

            // Use L7 if available, otherwise fall back to L30
            $campaignForDisplay = $matchedCampaignL7 ?? $matchedCampaignL30;
            
            // Only process RUNNING campaigns
            if (!$campaignForDisplay || $campaignForDisplay->campaignStatus !== 'RUNNING') {
                continue;
            }

            $row = [];
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['price']  = $ebay->ebay_price ?? 0;
            $row['campaign_id'] = $campaignForDisplay->campaign_id ?? '';
            $row['campaign_name'] = $campaignForDisplay->campaign_name ?? '';
            $row['campaignBudgetAmount'] = $campaignForDisplay->campaignBudgetAmount ?? '';
            $row['sku'] = $pm->sku;

            $row['l7_spend'] = (float) str_replace(['USD ', ','], '', $matchedCampaignL7->cpc_ad_fees_payout_currency ?? '0');
            $row['l7_cpc'] = (float) str_replace(['USD ', ','], '', $matchedCampaignL7->cost_per_click ?? '0');
            $row['l1_spend'] = (float) str_replace(['USD ', ','], '', $matchedCampaignL1->cpc_ad_fees_payout_currency ?? '0');
            $row['l1_cpc'] = (float) str_replace(['USD ', ','], '', $matchedCampaignL1->cost_per_click ?? '0');

            // Calculate ACOS from L30 data (use L30 if available, otherwise use L7)
            $matchedCampaignL30 = $matchedCampaignL30 ?? $matchedCampaignL7;
            $adFeesL30 = (float) str_replace(['USD ', ','], '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? '0');
            $salesL30 = (float) str_replace(['USD ', ','], '', $matchedCampaignL30->cpc_sale_amount_payout_currency ?? '0');
            $acos = $salesL30 > 0 ? ($adFeesL30 / $salesL30) * 100 : 0;
            
            // If acos is 0 (no sales or no ad fees), set it to 100 for comparison
            if ($acos === 0) {
                $rowAcos = 100;
            } else {
                $rowAcos = $acos;
            }

            $l1_cpc = floatval($row['l1_cpc']);
            $l7_cpc = floatval($row['l7_cpc']);
            
            // Calculate SBID - handle cases where l1_cpc is 0 or missing
            if($l1_cpc > 1.25){
                $row['sbid'] = floor($l1_cpc * 0.80 * 100) / 100;
            }elseif($l1_cpc > 0){
                $row['sbid'] = floor($l1_cpc * 0.90 * 100) / 100;
            }

            $budget = floatval($row['campaignBudgetAmount']);
            $l7_spend = floatval($row['l7_spend']);
            $l1_spend = floatval($row['l1_spend']);

            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            $ub1 = $budget > 0 ? ($l1_spend / $budget) * 100 : 0;

            $row['NR'] = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NR'] = $raw['NR'] ?? null;
                }
            }

            // Apply filter conditions: ub7 > 99 AND ub1 > 99
            // Other filters: NR !== 'NRA', INV > 0
            if ($ub7 > 99 && $ub1 > 99 && $row['NR'] !== 'NRA' && $row['INV'] > 0) {
                $result[] = (object) $row;
            }

            }

            DB::connection()->disconnect();
            return $result;
        
        } catch (\Exception $e) {
            $this->error("Error in getEbayOverUtilizCampaign: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return [];
        } finally {
            DB::connection()->disconnect();
        }
    }

    private function getDilColor($l30, $inv)
    {
        if ($inv == 0) {
            return 'red';
        }

        $percent = ($l30 / $inv) * 100;

        if ($percent < 16.66) return 'red';
        if ($percent >= 16.66 && $percent < 25) return 'yellow';
        if ($percent >= 25 && $percent < 50) return 'green';
        return 'pink';
    }


}
