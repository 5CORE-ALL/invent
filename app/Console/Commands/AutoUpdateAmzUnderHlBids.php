<?php

namespace App\Console\Commands;

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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoUpdateAmzUnderHlBids extends Command
{
    protected $signature = 'amazon:auto-update-under-hl-bids {--dry-run : Show what would be updated without calling API}';
    protected $description = 'Automatically update Amazon campaign hl bids';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            $dryRun = $this->option('dry-run');
            $this->info("Starting Amazon Under-Utilized HL bids auto-update..." . ($dryRun ? " [DRY RUN - no API calls]" : ""));

            // Check database connection (without creating persistent connection)
            try {
                DB::connection()->getPdo();
                $this->info("✓ Database connection OK");
                // Immediately disconnect after check to prevent connection buildup
                DB::connection()->disconnect();
            } catch (\Exception $e) {
                $this->error("✗ Database connection failed: " . $e->getMessage());
                return 1;
            }

            $updateKwBids = new AmazonSbBudgetController;

            $campaigns = $this->getAutomateAmzUtilizedBgtHl();

            if (empty($campaigns)) {
                $this->warn("No campaigns matched filter conditions.");
                return 0;
            }

            // Require campaign id + numeric proposed bid (same shape as over HL job; do not require sbid > 0 beyond being a valid number)
            $validCampaigns = collect($campaigns)->filter(function ($campaign) {
                return ! empty($campaign->campaign_id)
                    && isset($campaign->sbid)
                    && is_numeric($campaign->sbid)
                    && floatval($campaign->sbid) > 0;
            })->values();

            if ($validCampaigns->isEmpty()) {
                $this->warn("No valid campaigns found (missing campaign_id or invalid bid).");
                return 0;
            }

            $apiCampaigns = $validCampaigns->filter(function ($campaign) {
                return (int) ($campaign->INV ?? 0) > 0;
            })->values();

            $this->info("Found " . $validCampaigns->count() . " under-utilized HL campaign(s) (" . $apiCampaigns->count() . " eligible for Amazon API; " . ($validCampaigns->count() - $apiCampaigns->count()) . " INV=0 — persist sbid_m only).");
            $this->line("");

            // Log campaigns before update (same format as Under KW/PT)
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
                return 0;
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
                return 0;
            }

            $campaignIds = $apiCampaigns->pluck('campaign_id')->toArray();
            $newBids = $apiCampaigns->pluck('sbid')->toArray();

            // Validate arrays are aligned
            if (count($campaignIds) !== count($newBids)) {
                $this->error("✗ Array mismatch: campaign IDs and bids count don't match!");
                return 1;
            }

            try {
                $result = $updateKwBids->updateAutoCampaignSbKeywordsBid($campaignIds, $newBids);

                // Handle Response object (when no keywords found)
                if (is_object($result) && method_exists($result, 'getData')) {
                    $result = $result->getData(true);
                }

                $isSuccess = false;
                $successCount = 0;

                if (is_array($result)) {
                    $status = $result['status'] ?? null;
                    $message = $result['message'] ?? '';
                    $successCount = $result['success_count'] ?? 0;

                    // Format 1: Explicit status 200
                    if ($status == 200) {
                        $isSuccess = true;
                    }
                    // Format 2: Partial success (207) — still consider job completed
                    elseif ($status == 207) {
                        $isSuccess = true;
                        if (!empty($result['failed_batches'])) {
                            $this->warn("Some chunks failed after retries; successful updates: " . ($successCount ?: 'see logs'));
                        }
                    }
                    // Format 3: No status key but data has code SUCCESS (chunked API response)
                    elseif (!isset($result['status']) && isset($result['data'])) {
                        $successCount = \App\Http\Controllers\Campaigns\AmazonSbBudgetController::countSuccessfulKeywords($result['data']);
                        if ($successCount > 0 || !empty($result['data'])) {
                            $isSuccess = true;
                        }
                    }
                    // Format 4: Raw chunked array (array of arrays with code:SUCCESS)
                    elseif (!isset($result['status']) && isset($result[0]) && is_array($result[0])) {
                        $successCount = \App\Http\Controllers\Campaigns\AmazonSbBudgetController::countSuccessfulKeywords($result);
                        if ($successCount > 0) {
                            $isSuccess = true;
                        }
                    }
                    // Format 5: Object with success key
                    elseif (isset($result['success']) && $result['success']) {
                        $isSuccess = true;
                    }
                }

                if ($isSuccess) {
                    $this->info("✓ HL bids updated successfully!");
                    $this->line("");
                    $this->info("Updated campaigns (persist sbid_m for all under-utilized rows, including INV=0):");
                    $persistedRows = 0;
                    foreach ($validCampaigns as $campaign) {
                        $campaignName = $campaign->campaignName ?? 'N/A';
                        $newBid = $campaign->sbid ?? 0;
                        $this->line("  Campaign: {$campaignName} | New Bid: {$newBid}");
                        $persistedRows += AmazonBidUtilizationService::persistSbSbidM((string) ($campaign->campaign_id ?? ''), (float) $newBid);
                    }
                    Log::info('amazon:auto-update-under-hl-bids persisted sbid_m to L30', [
                        'campaigns' => $validCampaigns->count(),
                        'api_campaigns' => $apiCampaigns->count(),
                        'l30_rows_updated' => $persistedRows,
                    ]);
                    if ($successCount > 0) {
                        $this->info("Keywords updated: {$successCount}");
                    }
                } else {
                    $this->error("✗ Bid update failed!");
                    if (is_array($result)) {
                        if (isset($result['status'])) {
                            $this->error("Status: " . $result['status']);
                        }
                        if (isset($result['message'])) {
                            $this->error("Message: " . $result['message']);
                        }
                        if (isset($result['error'])) {
                            $this->error("Error: " . $result['error']);
                        }
                    }
                    if (is_array($result) || is_object($result)) {
                        \Illuminate\Support\Facades\Log::debug('HL under-utilized bid update response', ['result' => $result]);
                    }
                    return 1;
                }

            } catch (\Exception $e) {
                $this->error("✗ Exception occurred during bid update:");
                $this->error($e->getMessage());
                $this->error("Stack trace: " . $e->getTraceAsString());
                return 1;
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("✗ Error in handle: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        } finally {
            DB::connection()->disconnect();
        }
    }

    public function getAutomateAmzUtilizedBgtHl()
    {
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
            // Align HL budget source with frontend preference (L30 first, then L7/L1 fallback).
            $budgetCandidates = [
                floatval(($matchedCampaignL30 ? $matchedCampaignL30->campaignBudgetAmount : null) ?? 0),
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

            $baseBid = ($matchedCampaignL30 ? floatval($matchedCampaignL30->last_sbid ?? 0) : 0);
            if ($baseBid <= 0) {
                $baseBid = $l1_cpc > 0 ? $l1_cpc : ($l7_cpc > 0 ? $l7_cpc : 0);
            }
            if ($baseBid <= 0) {
                $baseBid = 0.60;
            }

            // Under-utilized HL: increase bid when 1-day utilization < 50% (align with over HL job: do not require INV > 0)
            $row['INV'] = (int) ($row['INV'] ?? 0);
            if ($row['campaignName'] !== '' && $ub1 < 50) {
                $row['sbid'] = round($baseBid * 1.10, 2);
                AmazonBidUtilizationService::logBidDecision(
                    (string) $row['campaign_id'],
                    'hl_under',
                    $ub1,
                    $baseBid,
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