<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\PushesAmazonAdsUpdatesInChunks;
use App\Http\Controllers\Campaigns\Ebay3UtilizedAdsController;
use App\Models\Ebay3Metric;
use App\Models\Ebay3PriorityReport;
use App\Models\EbayThreeDataView;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\CronMonitor\CronExecutionContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Ebay3UtilizedBidsAutoUpdate extends Command
{
    use MonitorsCronExecution;
    use PushesAmazonAdsUpdatesInChunks;

    protected $signature = 'ebay3:auto-update-utilized-bids
        {--dry-run : Run without actually updating bids}
        {--chunk= : Override chunk size for API updates (default from cron-monitor config)}';

    protected $description = 'Automatically update eBay3 campaign keyword bids for over and under-utilized campaigns';

    /** Number of retry attempts for failed campaign updates (minimum 5 tries total for failures). */
    const MAX_RETRY_ATTEMPTS = 5;

    /** Seconds to wait between retry rounds for failed campaigns (rate-limit precaution). */
    const RETRY_DELAY_SECONDS = 5;

    protected string $monitorJobName = 'eBay3 Bid Sync (Utilized)';

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
            set_time_limit(0);
            ini_set('max_execution_time', 0);
            ini_set('memory_limit', '1024M');

            try {
                DB::connection()->getPdo();
                $this->info('✓ Database connection OK');
                DB::connection()->disconnect();
            } catch (\Exception $e) {
                $this->error('✗ Database connection failed: '.$e->getMessage());
                $monitor->classifyAndRecord($e);

                return self::FAILURE;
            }

            $isDryRun = $this->option('dry-run');

            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('🚀 Starting eBay3 Utilized Bids Auto-Update'.($isDryRun ? ' [DRY RUN - no API calls]' : ''));
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            $updateUtilizedBids = new Ebay3UtilizedAdsController;

            $campaigns = $this->getEbay3UtilizedCampaigns();

            if (empty($campaigns)) {
                $this->warn('⚠️  No campaigns matched filter conditions.');
                $monitor->markApiConnected();
                $monitor->setExpected(0);

                return self::SUCCESS;
            }

            $validCampaigns = array_filter($campaigns, fn ($campaign) => ! empty($campaign->campaign_id) && ! empty($campaign->sbid) && floatval($campaign->sbid) > 0);

            if (empty($validCampaigns)) {
                $this->warn('⚠️  No valid campaigns found (all have empty campaign_id or zero/blank sbid).');
                $monitor->markApiConnected();
                $monitor->setExpected(0);

                return self::SUCCESS;
            }

            $overCount = count(array_filter($validCampaigns, fn ($c) => $c->isOverUtilized ?? false));
            $underCount = count($validCampaigns) - $overCount;

            $this->info('📊 Found '.count($validCampaigns)." campaigns to update (Over: {$overCount}, Under: {$underCount})");
            $this->info('');

            $this->info('📋 Campaigns to be updated:');
            foreach ($validCampaigns as $index => $campaign) {
                $campaignName = $campaign->campaignName ?? 'Unknown';
                $campaignId = $campaign->campaign_id ?? 'N/A';
                $newBid = $campaign->sbid ?? 0;
                $lastSbid = ! empty($campaign->last_sbid) && $campaign->last_sbid !== '0' ? (float) $campaign->last_sbid : 0;
                $type = ($campaign->isOverUtilized ?? false) ? 'Over' : 'Under';

                $this->line('   '.($index + 1).". [{$type}] Campaign: {$campaignName} | ID: {$campaignId} | Last SBID: \${$lastSbid} | New SBID: \${$newBid}");
            }

            $this->info('');

            $campaignBidMap = collect($validCampaigns)->pluck('sbid', 'campaign_id')->toArray();

            if ($isDryRun) {
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
                    $updateUtilizedBids->updateAutoKeywordsBidDynamic($ids, $bids),
                    $ids
                ),
                'ebay3'
            );

            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info("FINAL: Total={$stats['total']} | Updated={$stats['updated']} | Skipped={$stats['skipped']} | Failed={$stats['failed']} | Chunks={$stats['chunks']}");
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            Log::info('ebay3:auto-update-utilized-bids completed', $stats);

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

    public function getEbay3UtilizedCampaigns()
    {
        try {
            $normalizeSku = fn ($sku) => trim(preg_replace('/[^\S\r\n]+/u', ' ', strtoupper(trim($sku))));

            $productMasters = ProductMaster::whereNull('deleted_at')
                ->orderBy('parent', 'asc')
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

            $shopifyByPm = ShopifySku::mapByProductSkus($skus);
            $shopifyData = [];
            foreach ($productMasters as $pm) {
                $nk = $normalizeSku($pm->sku);
                if ($nk === '') {
                    continue;
                }
                $row = $shopifyByPm->get($pm->sku);
                if ($row !== null) {
                    $shopifyData[$nk] = $row;
                }
            }
            $ebayMetricData = Ebay3Metric::whereIn('sku', $skus)->get()->keyBy(fn ($item) => $normalizeSku($item->sku));
            $nrValues = EbayThreeDataView::whereIn('sku', $skus)->pluck('value', 'sku');

            $reports = Ebay3PriorityReport::whereIn('report_range', ['L7', 'L1', 'L30'])
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->orderBy('report_range', 'asc')
                ->get();

            $dayBeforeYesterday = date('Y-m-d', strtotime('-2 days'));
            $lastSbidMap = Ebay3PriorityReport::where('report_range', $dayBeforeYesterday)
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->pluck('last_sbid', 'campaign_id')
                ->filter();

            $result = [];
            $campaignMap = [];

            foreach ($productMasters as $pm) {
                // Only process PARENT SKUs for Ebay3
                if (stripos($pm->sku, 'PARENT') === false) {
                    continue;
                }

                $normalizedSku = $normalizeSku($pm->sku);
                $sku = $normalizedSku;
                $shopify = $shopifyData[$normalizedSku] ?? ShopifySku::firstForProductSku($pm->sku);
                $ebay = $ebayMetricData[$normalizedSku] ?? Ebay3Metric::where('sku', $pm->sku)->first();

                $nrValue = '';
                if (isset($nrValues[$pm->sku])) {
                    $raw = $nrValues[$pm->sku];
                    if (!is_array($raw)) {
                        $raw = json_decode($raw, true);
                    }
                    if (is_array($raw)) {
                        $nrValue = $raw['NR'] ?? null;
                    }
                }

                if ($nrValue == 'NRA') {
                    continue;
                }

                $matchedReports = $reports->filter(function ($item) use ($sku, $normalizeSku) {
                    $campaignName = $item->campaign_name ?? '';
                    $normalizedCampaignName = $normalizeSku($campaignName);
                    return $normalizedCampaignName === $sku;
                });

                if ($matchedReports->isEmpty()) {
                    continue;
                }

                foreach ($matchedReports as $campaign) {
                    $campaignId = $campaign->campaign_id ?? '';

                    if (empty($campaignId)) {
                        continue;
                    }

                    if (!isset($campaignMap[$campaignId])) {
                        $campaignMap[$campaignId] = [
                            'sku' => $pm->sku,
                            'campaign_id' => $campaignId,
                            'campaignName' => $campaign->campaign_name ?? '',
                            'campaignBudgetAmount' => $campaign->campaignBudgetAmount ?? 0,
                            'campaignStatus' => $campaign->campaignStatus ?? '',
                            'INV' => (int)($shopify->inv ?? 0),
                            'L30' => (int)($shopify->quantity ?? 0),
                            'l7_spend' => 0,
                            'l7_cpc' => 0,
                            'l1_spend' => 0,
                            'l1_cpc' => 0,
                            'acos' => 0,
                            'NR' => $nrValue,
                            'price' => (float)($ebay->ebay_price ?? 0),
                            'ebay_l30' => (float)($ebay->ebay_l30 ?? 0),
                            'views' => (float)($ebay->views ?? 0),
                            'last_sbid' => $lastSbidMap[$campaignId] ?? '',
                        ];
                    }

                    $reportRange = $campaign->report_range ?? '';
                    $adFees = (float) str_replace(['USD ', ','], '', $campaign->cpc_ad_fees_payout_currency ?? '0');
                    $sales = (float) str_replace(['USD ', ','], '', $campaign->cpc_sale_amount_payout_currency ?? '0');
                    $cpc = (float) str_replace(['USD ', ','], '', $campaign->cost_per_click ?? '0');

                    if ($reportRange == 'L7') {
                        $campaignMap[$campaignId]['l7_spend'] = $adFees;
                        $campaignMap[$campaignId]['l7_cpc'] = $cpc;
                    } elseif ($reportRange == 'L1') {
                        $campaignMap[$campaignId]['l1_spend'] = $adFees;
                        $campaignMap[$campaignId]['l1_cpc'] = $cpc;
                    } elseif ($reportRange == 'L30') {
                        $campaignMap[$campaignId]['acos'] = $sales > 0 ? round(($adFees / $sales) * 100, 2) : ($adFees > 0 ? 100 : 0);
                    }
                }
            }

            foreach ($campaignMap as $campaignId => $row) {
                if ($row['campaignStatus'] !== 'RUNNING') {
                    continue;
                }

                $l7_spend = (float)$row['l7_spend'];
                $l1_cpc = (float)$row['l1_cpc'];
                $l7_cpc = (float)$row['l7_cpc'];
                $l1_spend = (float)$row['l1_spend'];
                $budget = (float)$row['campaignBudgetAmount'];
                $price = (float)$row['price'];
                $inv = (float)$row['INV'];

                $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
                $ub1 = $budget > 0 ? ($l1_spend / $budget) * 100 : 0;

                // Over-utilized: both UB7 and UB1 > 99%
                $isOverUtilized = ($ub7 > 99 && $ub1 > 99);

                // Under-utilized: both UB7 and UB1 < 66%
                $isUnderUtilized = ! $isOverUtilized && $ub7 < 66 && $ub1 < 66;

                if (! $isOverUtilized && ! $isUnderUtilized) {
                    continue;
                }

                $sbid = 0;

                // PMT S BID rule based on SCVR (CVR color thresholds)
                $ebayL30Sold = floatval($row['ebay_l30'] ?? 0);
                $ebayViews   = floatval($row['views'] ?? 0);

                if ($ebayL30Sold == 0) {
                    $sbid = 0; // 0 sold → no bid
                } elseif ($ebayViews <= 0) {
                    $sbid = 0;
                } else {
                    $scvr = ($ebayL30Sold / $ebayViews) * 100;
                    if ($scvr <= 4) {
                        $sbid = 9.1;
                    } elseif ($scvr <= 7) {
                        $sbid = 7.1;
                    } elseif ($scvr <= 13) {
                        $sbid = 4.1;
                    } else {
                        $sbid = 2.1;
                    }
                }

                if ($sbid > 0) {
                    $row['sbid'] = $sbid;
                    $row['isOverUtilized'] = $isOverUtilized;
                    $row['isUnderUtilized'] = $isUnderUtilized;
                    $result[] = (object) $row;
                }
            }

            DB::connection()->disconnect();
            return $result;

        } catch (\Exception $e) {
            $this->error("Error in getEbay3UtilizedCampaigns: ".$e->getMessage());
            $this->error("Stack trace: ".$e->getTraceAsString());
            return [];
        } finally {
            DB::connection()->disconnect();
        }
    }
}
