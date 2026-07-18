<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\PushesAmazonAdsUpdatesInChunks;
use App\Http\Controllers\Campaigns\Ebay2UtilizedAdsController;
use App\Models\Ebay2Metric;
use App\Models\Ebay2PriorityReport;
use App\Models\EbayTwoDataView;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\CronMonitor\CronExecutionContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Ebay2UtilizedBidsAutoUpdate extends Command
{
    use MonitorsCronExecution;
    use PushesAmazonAdsUpdatesInChunks;

    protected $signature = 'ebay2:auto-update-utilized-bids
        {--dry-run : Run without actually updating bids}
        {--chunk= : Override chunk size for API updates (default from cron-monitor config)}';

    protected $description = 'Automatically update eBay2 campaign keyword bids for over and under-utilized campaigns';

    /** Number of retry attempts for failed campaign updates (minimum 5 tries total for failures). */
    const MAX_RETRY_ATTEMPTS = 5;

    /** Seconds to wait between retry rounds for failed campaigns (rate-limit precaution). */
    const RETRY_DELAY_SECONDS = 5;

    protected string $monitorJobName = 'eBay2 Bid Sync (Utilized)';

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
            $this->info('🚀 Starting eBay2 Utilized Bids Auto-Update'.($isDryRun ? ' [DRY RUN - no API calls]' : ''));
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            $updateUtilizedBids = new Ebay2UtilizedAdsController;

            $campaigns = $this->getEbay2UtilizedCampaigns();

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
                'ebay2'
            );

            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info("FINAL: Total={$stats['total']} | Updated={$stats['updated']} | Skipped={$stats['skipped']} | Failed={$stats['failed']} | Chunks={$stats['chunks']}");
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            Log::info('ebay2:auto-update-utilized-bids completed', $stats);

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

    public function getEbay2UtilizedCampaigns()
    {
        try {
            // SKU normalization function to handle spaces and whitespace
            $normalizeSku = function ($sku) {
                if (empty($sku)) {
                    return '';
                }
                $sku = strtoupper(trim($sku));
                $sku = preg_replace('/\s+/u', ' ', $sku);
                $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
                return trim($sku);
            };

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

            // Fetch Shopify data with normalized SKU matching
            $shopifyDataRaw = ShopifySku::whereIn('sku', $skus)->get();
            $shopifyData = [];
            foreach ($shopifyDataRaw as $shopify) {
                $normalizedKey = $normalizeSku($shopify->sku);
                $shopifyData[$normalizedKey] = $shopify;
            }

            // Fetch eBay metric data with normalized SKU matching
            $ebayMetricDataRaw = Ebay2Metric::whereIn('sku', $skus)->get();
            $ebayMetricData = [];
            foreach ($ebayMetricDataRaw as $ebay) {
                $normalizedKey = $normalizeSku($ebay->sku);
                $ebayMetricData[$normalizedKey] = $ebay;
            }

            $nrValues = EbayTwoDataView::whereIn('sku', $skus)->pluck('value', 'sku');

            $reports = Ebay2PriorityReport::whereIn('report_range', ['L7', 'L1', 'L30'])
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->orderBy('report_range', 'asc')
                ->get();

            // Fetch last_sbid from day-before-yesterday's date records (same as controller)
            $dayBeforeYesterday = date('Y-m-d', strtotime('-2 days'));
            $lastSbidReports = Ebay2PriorityReport::where('report_range', $dayBeforeYesterday)
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->get();

            $lastSbidMap = [];
            foreach ($lastSbidReports as $report) {
                if (!empty($report->campaign_id) && !empty($report->last_sbid)) {
                    $lastSbidMap[$report->campaign_id] = $report->last_sbid;
                }
            }

            $result = [];
            $campaignMap = [];

            foreach ($productMasters as $pm) {
                // Skip PARENT SKUs
                if (stripos($pm->sku, 'PARENT') !== false) {
                    continue;
                }

                $normalizedSku = $normalizeSku($pm->sku);
                $shopify = $shopifyData[$normalizedSku] ?? ShopifySku::where('sku', $pm->sku)->first();
                $ebay = $ebayMetricData[$normalizedSku] ?? Ebay2Metric::where('sku', $pm->sku)->first();

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

                // Skip if NR is NRA
                if ($nrValue == 'NRA') {
                    continue;
                }

                $matchedReports = $reports->filter(function ($item) use ($normalizedSku, $normalizeSku) {
                    return $normalizeSku($item->campaign_name ?? '') === $normalizedSku;
                });

                if ($matchedReports->isEmpty()) {
                    continue;
                }

                // Group reports by campaign_id to combine L7, L1, L30 data
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
                $isPink = $this->getDilColor($row['L30'], $row['INV']) === 'pink';

                // Exclude INV=0 campaigns from both over and under-utilized
                if ($inv <= 0) {
                    continue;
                }

                // Over-utilized: both UB7 and UB1 > 99%
                $isOverUtilized = ($ub7 > 99 && $ub1 > 99);

                // Under-utilized: both UB7 and UB1 < 66% and not pink
                $isUnderUtilized = ! $isOverUtilized && $ub7 < 66 && $ub1 < 66;

                if (! $isOverUtilized && ! $isUnderUtilized) {
                    continue;
                }

                // PMT S BID rule based on SCVR (CVR color thresholds)
                $ebayL30Sold = floatval($row['ebay_l30'] ?? 0);
                $ebayViews   = floatval($row['views'] ?? 0);
                $scvr = $ebayViews > 0 ? ($ebayL30Sold / $ebayViews) * 100 : 0;

                if ($scvr <= 4) {
                    $sbid = 9.1;       // RED
                } elseif ($scvr <= 7) {
                    $sbid = 7.1;       // YELLOW
                } elseif ($scvr <= 13) {
                    $sbid = 4.1;       // GREEN
                } else {
                    $sbid = 2.1;       // PINK
                }

                // Only add if SBID > 0
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
            $this->error("Error in getEbay2UtilizedCampaigns: ".$e->getMessage());
            $this->error("Stack trace: ".$e->getTraceAsString());
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

        if ($percent < 16.66) {
            return 'red';
        }
        if ($percent >= 16.66 && $percent < 25) {
            return 'yellow';
        }
        if ($percent >= 25 && $percent < 50) {
            return 'green';
        }

        return 'pink';
    }
}
