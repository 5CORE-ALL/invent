<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ACOSControl\AmazonACOSController;
use App\Models\AmazonDatasheet;
use Illuminate\Console\Command;
use App\Models\AmazonSpCampaignReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\DB;

class AutoUpdateAmazonPinkDilKwAds extends Command
{
    protected $signature = 'amazon:auto-update-pink-dil-kw-ads {--dry-run : Run without making changes}';
    protected $description = 'Automatically update Amazon campaign pink dil bgt';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            $dryRun = $this->option('dry-run');
            if ($dryRun) {
                $this->warn("=== DRY RUN MODE - No changes will be made ===");
            }
            $this->info("Starting Amazon bgts auto-update...");

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

            $updatePinkDilKwAds = new AmazonACOSController;

            $campaigns = $this->getAmazonPinkDilKwAdsData();

            if (empty($campaigns)) {
                $this->warn("No campaigns matched filter conditions.");
                return 0;
            }

            $this->info("Found " . count($campaigns) . " campaigns to process");

            // Separate campaigns to pause vs update budget
            $campaignsToPause = [];
            $campaignsToUpdate = [];

            foreach ($campaigns as $campaign) {
                // Check pause conditions:
                // 1. Dil% > 100% AND Acos > 10%
                // 2. Dil% is 50-100% AND Acos > 20%
                // 3. Ratings < 3.5
                // 4. Price < 10 AND units ordered > 0
                $rating = isset($campaign->rating) && $campaign->rating !== null ? (float) $campaign->rating : null;
                $price = isset($campaign->price) ? (float) $campaign->price : null;
                $unitsL30 = $campaign->OV_L30 ?? 0; // Use OV L30 instead of A_L30
                $dilPercent = $campaign->dilPercent ?? 0;
                $acos = $campaign->acos_L30 ?? 0;
                
                // Check each condition separately for detailed logging
                $condition1 = ($dilPercent > 100 && $acos > 10); // Dil% > 100% AND ACOS > 10%
                $condition2 = ($dilPercent >= 50 && $dilPercent <= 100 && $acos > 20); // Dil% 50-100% AND ACOS > 20%
                $condition3 = ($rating !== null && $rating < 3.5); // Rating < 3.5
                $condition4 = ($price < 10 && $unitsL30 > 0); // Price < 10 AND units > 0
                
                $shouldPause = $condition1 || $condition2 || $condition3 || $condition4;
                
                if ($shouldPause) {
                    // Determine which condition(s) triggered the pause
                    $pauseReasons = [];
                    if ($condition1) {
                        $pauseReasons[] = "Dil% > 100% ($dilPercent% > 100%) AND ACOS > 10% ($acos% > 10%)";
                    }
                    if ($condition2) {
                        $pauseReasons[] = "Dil% 50-100% ($dilPercent% between 50-100%) AND ACOS > 20% ($acos% > 20%)";
                    }
                    if ($condition3) {
                        $pauseReasons[] = "Low Rating ($rating < 3.5)";
                    }
                    if ($condition4) {
                        $pauseReasons[] = "Low Price (\$$price < \$10) AND Units Ordered ($unitsL30 > 0)";
                    }
                    
                    $reason = implode(" OR ", $pauseReasons);
                    
                    // Log pause details with reason
                    $this->info(sprintf(
                        "PAUSE: Campaign: %s | Dil: %.2f%% | ACOS: %.2f%% | Rating: %s | Price: $%.2f | Units L30: %d | Reason: %s | Status: PAUSED",
                        $campaign->campaignName ?? 'N/A',
                        $dilPercent,
                        $acos,
                        $rating !== null ? number_format($rating, 2) : 'N/A',
                        $price ?? 0,
                        $unitsL30,
                        $reason
                    ));
                    $campaignsToPause[] = $campaign->campaign_id;
                } else {
                    $campaignsToUpdate[] = $campaign;
                }
            }

            // Pause campaigns that meet the criteria
            if (!empty($campaignsToPause)) {
                $pauseCount = count($campaignsToPause);
                if ($dryRun) {
                    $this->warn(sprintf("DRY RUN: Would pause %d campaigns", $pauseCount));
                    $this->info(sprintf("=== TOTAL PAUSED CAMPAIGNS: %d ===", $pauseCount));
                } else {
                    $pauseResult = $updatePinkDilKwAds->pauseCampaigns($campaignsToPause);
                    $this->info("Pause Result: " . json_encode($pauseResult));
                    $this->info(sprintf("=== TOTAL PAUSED CAMPAIGNS: %d ===", $pauseCount));
                }
            } else {
                $this->info("=== TOTAL PAUSED CAMPAIGNS: 0 ===");
            }

            // Update budget for campaigns that don't meet pause criteria
            if (!empty($campaignsToUpdate)) {
                // Log budget update details
                foreach ($campaignsToUpdate as $campaign) {
                    $this->info(sprintf(
                        "BGT UPDATE: Campaign: %s | Old BGT: $%.2f | New BGT: $%.2f",
                        $campaign->campaignName ?? 'N/A',
                        $campaign->campaignBudgetAmount ?? 0,
                        $campaign->sbgt ?? 0
                    ));
                }
                
                if ($dryRun) {
                    $this->warn(sprintf("DRY RUN: Would update budget for %d campaigns", count($campaignsToUpdate)));
                } else {
                    $campaignIds = collect($campaignsToUpdate)->pluck('campaign_id')->toArray();
                    $newBgts = collect($campaignsToUpdate)->pluck('sbgt')->toArray();

                    $updateResult = $updatePinkDilKwAds->updateAutoAmazonCampaignBgt($campaignIds, $newBgts);
                    $this->info("Update Result: " . json_encode($updateResult));
                }
            }
            
        } catch (\Exception $e) {
            $this->error("✗ Error occurred: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        } finally {
            DB::connection()->disconnect();
        }
    }

    public function getAmazonPinkDilKwAdsData()
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

            // Build SKU list, excluding PARENT SKUs
            $skus = $productMasters->pluck('sku')
                ->filter()
                ->reject(function ($sku) {
                    return stripos($sku, 'PARENT ') === 0;
                })
                ->unique()
                ->values()
                ->all();

            if (empty($skus)) {
                $this->warn("No valid non-parent SKUs found!");
                return [];
            }

            $this->info(sprintf("KW Pink DIL: loaded %d product masters, %d unique child SKUs", $productMasters->count(), count($skus)));

            $amazonDatasheetsBySku = [];
            $shopifyData = [];

            if (!empty($skus)) {
                $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
                    return strtoupper($item->sku);
                });
                $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
            }

            // Fetch latest ratings from junglescout_product_data (same logic as getAmazonUtilizedAdsData)
            $junglescoutData = collect();
            
            // Get latest by SKU
            $skuRatings = DB::table('junglescout_product_data as j1')
                ->select('j1.sku', 'j1.data')
                ->whereNotNull('j1.sku')
                ->whereIn('j1.sku', $skus)
                ->whereRaw('j1.updated_at = (SELECT MAX(j2.updated_at) FROM junglescout_product_data j2 WHERE j2.sku = j1.sku)')
                ->get();
            
            foreach ($skuRatings as $item) {
                $data = json_decode($item->data, true);
                $rating = $data['rating'] ?? null;
                if ($item->sku && !$junglescoutData->has($item->sku)) {
                    $junglescoutData->put($item->sku, $rating);
                }
            }
            
            // Get latest by parent as fallback
            $parentRatings = DB::table('junglescout_product_data as j1')
                ->select('j1.parent', 'j1.data')
                ->whereNotNull('j1.parent')
                ->whereIn('j1.parent', $skus)
                ->whereRaw('j1.updated_at = (SELECT MAX(j2.updated_at) FROM junglescout_product_data j2 WHERE j2.parent = j1.parent)')
                ->get();
            
            foreach ($parentRatings as $item) {
                $data = json_decode($item->data, true);
                $rating = $data['rating'] ?? null;
                if ($item->parent && !$junglescoutData->has($item->parent)) {
                    $junglescoutData->put($item->parent, $rating);
                }
            }

            // Get ALL KW (non-PT, non-FBA) SP campaigns for L30/L7/L1, then match in PHP
            $allCampaigns = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->whereIn('report_date_range', ['L30', 'L7', 'L1'])
                ->whereNotNull('campaignName')
                ->where(function ($q) {
                    // Exclude FBA campaigns
                    $q->where('campaignName', 'NOT LIKE', '%FBA%');
                    // Exclude PT campaigns (ending with PT or PT.)
                    $q->whereRaw("UPPER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% PT'");
                    $q->whereRaw("UPPER(TRIM(campaignName)) NOT LIKE '%PT.%'");
                })
                ->get();

            $campaignsByRange = [
                'L30' => $allCampaigns->where('report_date_range', 'L30')->values(),
                'L7'  => $allCampaigns->where('report_date_range', 'L7')->values(),
                'L1'  => $allCampaigns->where('report_date_range', 'L1')->values(),
            ];

            $this->info(sprintf(
                "KW Pink DIL: loaded %d SP campaigns (L30: %d, L7: %d, L1: %d)",
                $allCampaigns->count(),
                $campaignsByRange['L30']->count(),
                $campaignsByRange['L7']->count(),
                $campaignsByRange['L1']->count()
            ));

            $result = [];
            $totalSkusProcessed = 0;
            $matchedSkus = 0;

            foreach ($productMasters as $pm) {
                $rawSku = $pm->sku;
                if (!$rawSku || stripos($rawSku, 'PARENT ') === 0) {
                    // Skip parent SKUs for matching; they don't have their own KW campaigns
                    continue;
                }

                $totalSkusProcessed++;
                $sku = strtoupper($rawSku);

                $amazonSheet = $amazonDatasheetsBySku[strtoupper($pm->sku)] ?? null;
                $shopify = $shopifyData[$pm->sku] ?? null;

                // Use flexible matching per date range
                $matchedCampaignL7  = $this->findMatchingCampaign($sku, $campaignsByRange['L7']);
                $matchedCampaignL1  = $this->findMatchingCampaign($sku, $campaignsByRange['L1']);
                $matchedCampaignL30 = $this->findMatchingCampaign($sku, $campaignsByRange['L30']);

                // Skip if no campaign found in any range
                if (!$matchedCampaignL30 && !$matchedCampaignL7 && !$matchedCampaignL1) {
                    // For debugging, see if there are loose substring matches
                    $possible = $campaignsByRange['L30']->filter(function ($item) use ($sku) {
                        return stripos($this->normalizeText($item->campaignName), $this->normalizeText($sku)) !== false;
                    });
                    if ($possible->isNotEmpty()) {
                        $this->warn("KW Pink DIL: SKU '{$sku}' has loose matches but no strong match: " . $possible->pluck('campaignName')->implode(', '));
                    }
                    continue;
                }

                $matchedSkus++;

                // Get campaign_id and name from any available range (prioritize L7, then L1, then L30)
                $campaignId = ($matchedCampaignL7 ? $matchedCampaignL7->campaign_id : null)
                    ?? ($matchedCampaignL1 ? $matchedCampaignL1->campaign_id : null)
                    ?? ($matchedCampaignL30 ? $matchedCampaignL30->campaign_id : '');
                $campaignName = $matchedCampaignL7->campaignName
                    ?? ($matchedCampaignL1->campaignName ?? ($matchedCampaignL30->campaignName ?? ''));

                // Use L30 data if we found it, otherwise try to find it by campaign_id inside L30 range
                if (!$matchedCampaignL30 && !empty($campaignId)) {
                    $matchedCampaignL30 = $campaignsByRange['L30']->firstWhere('campaign_id', $campaignId);
                }

                $row = [];
                $row['INV']    = $shopify->inv ?? 0;
                $row['A_L30']  = $amazonSheet->units_ordered_l30 ?? 0;
                $row['OV_L30'] = $shopify->quantity ?? 0; // OV L30 (overall L30 from Shopify)
                $row['price']  = $amazonSheet->price ?? null;
                $row['campaign_id'] = $campaignId;
                $row['campaignName'] = $campaignName;
                $row['campaignBudgetAmount'] = $matchedCampaignL7->campaignBudgetAmount
                    ?? ($matchedCampaignL1->campaignBudgetAmount ?? ($matchedCampaignL30->campaignBudgetAmount ?? 0));
                $row['rating'] = $junglescoutData[$pm->sku] ?? null;
                $row['sbgt'] = 1;

                // Process ALL campaigns, not just pink DIL ones
                // We need to check for pause conditions:
                // 1. Dil% > 100% AND Acos > 10%
                // 2. Dil% is 50-100% AND Acos > 20%
                // 3. Ratings < 3.5
                // 4. Price < 10 AND units ordered > 0
                if (!empty($row['campaignName'])) {
                    // Use OV L30 (overall L30) instead of A_L30 for dilPercent calculation
                    $dilPercent = $row['INV'] > 0 ? (($row['OV_L30'] / $row['INV']) * 100) : 0;
                    
                    // Calculate ACOS from L30 data
                    $sales = $matchedCampaignL30 ? ($matchedCampaignL30->sales30d ?? 0) : 0;
                    $spend = $matchedCampaignL30 ? ($matchedCampaignL30->spend ?? 0) : 0;
                    
                    if ($sales > 0) {
                        $row['acos_L30'] = round(($spend / $sales) * 100, 2);
                    } elseif ($spend > 0) {
                        $row['acos_L30'] = 100;
                    } else {
                        $row['acos_L30'] = 0;
                    }
                    
                    $row['dilPercent'] = round($dilPercent, 2);
                    
                    $result[] = (object) $row;
                }
            }

            $this->info(sprintf(
                "KW Pink DIL: processed %d child SKUs, matched %d campaigns",
                $totalSkusProcessed,
                $matchedSkus
            ));

            DB::connection()->disconnect();
            return $result;
        
        } catch (\Exception $e) {
            $this->error("Error in getAmazonPinkDilKwAdsData: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return [];
        } finally {
            DB::connection()->disconnect();
        }
    }

    /**
     * Normalize text by uppercasing, trimming, collapsing spaces, and removing trailing dots.
     */
    protected function normalizeText(?string $text): string
    {
        if ($text === null) {
            return '';
        }
        // Replace non-breaking and odd spaces, then collapse
        $text = str_replace(
            ["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"],
            ' ',
            $text
        );
        $text = preg_replace('/\s+/', ' ', $text);
        return strtoupper(trim(rtrim($text, '.')));
    }

    /**
     * Flexible campaign matching for a given SKU against a collection of campaigns.
     * Strategies (in order):
     *  - Exact match on normalized name
     *  - SKU with ' KW' suffix
     *  - Compact match ignoring spaces (with and without 'KW')
     *  - Whole-word match: single-word SKU as token, or all words of SKU present
     */
    protected function findMatchingCampaign(string $sku, $campaigns)
    {
        if (!$campaigns || $campaigns->isEmpty()) {
            return null;
        }

        $skuNorm = $this->normalizeText($sku);
        if ($skuNorm === '') {
            return null;
        }

        $skuWithKw = $skuNorm . ' KW';
        $skuCompact = str_replace(' ', '', $skuNorm);
        $skuCompactKw = $skuCompact . 'KW';
        $skuTokens = array_values(array_filter(explode(' ', $skuNorm)));
        $isMultiWordSku = count($skuTokens) > 1;

        // 1) Exact normalized match (with and without ' KW')
        foreach ($campaigns as $item) {
            $nameNorm = $this->normalizeText($item->campaignName);
            if ($nameNorm === $skuNorm || $nameNorm === $skuWithKw) {
                return $item;
            }
        }

        // 2) Compact match ignoring spaces (e.g., 'MS DBL G YLW 2PCS' vs 'MSDBLGYLW2PCS KW')
        foreach ($campaigns as $item) {
            $nameNorm = $this->normalizeText($item->campaignName);
            $nameCompact = str_replace(' ', '', $nameNorm);
            if ($nameCompact === $skuCompact || $nameCompact === $skuCompactKw) {
                return $item;
            }
        }

        // 3) Whole-word / all-words-present matching
        foreach ($campaigns as $item) {
            $nameNorm = $this->normalizeText($item->campaignName);
            $nameTokens = array_values(array_filter(explode(' ', $nameNorm)));

            if (empty($nameTokens)) {
                continue;
            }

            if ($isMultiWordSku) {
                // Multi-word SKU: require ALL SKU tokens to appear somewhere in campaign name
                $allTokensPresent = true;
                foreach ($skuTokens as $token) {
                    if (!in_array($token, $nameTokens, true)) {
                        $allTokensPresent = false;
                        break;
                    }
                }
                if ($allTokensPresent) {
                    return $item;
                }
            } else {
                // Single-word SKU: match as a whole word in campaign name
                if (in_array($skuNorm, $nameTokens, true)) {
                    return $item;
                }
            }
        }

        return null;
    }

}