<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\MarketPlace\ACOSControl\AmazonACOSController;
use App\Models\AmazonSpCampaignReport;
use App\Models\FbaTable;
use App\Models\ShopifySku;

class UpdateAmazonFbaKwBudgetCronCommand extends Command
{
    protected $signature = 'budget:update-amazon-fba-kw';
    protected $description = 'Update budget for Amazon FBA keyword campaigns based on ACOS (L30 data)';

    protected $acosController;

    public function __construct(AmazonACOSController $acosController)
    {
        parent::__construct();
        $this->acosController = $acosController;
    }

    public function handle()
    {
        $this->info('Starting budget update cron for Amazon FBA keyword campaigns (ACOS-based)...');

        // Get all FBA records with inventory > 0
        $fbaData = FbaTable::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
            ->where('quantity_available', '>', 0)
            ->orderBy('seller_sku', 'asc')
            ->get();

        if ($fbaData->isEmpty()) {
            $this->warn("No FBA records found.");
            return 0;
        }

        $this->info("Found " . $fbaData->count() . " FBA records");

        // Extract seller SKUs for campaigns matching
        $sellerSkus = $fbaData->pluck('seller_sku')->unique()->toArray();

        // Get base SKUs (without FBA) for Shopify data
        $baseSkus = $fbaData->map(function ($item) {
            $sku = $item->seller_sku;
            $base = preg_replace('/\s*FBA\s*/i', '', $sku);
            return strtoupper(trim($base));
        })->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $baseSkus)
            ->get()
            ->keyBy(function ($item) {
                return trim(strtoupper($item->sku));
            });

        // Fetch L30 campaign reports for keywords (not ending with PT) - only ENABLED campaigns
        $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where('campaignStatus', 'ENABLED')
            ->where(function ($q) use ($sellerSkus) {
                foreach ($sellerSkus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% pt'")
            ->get();

        $this->info("Found " . $amazonSpCampaignReportsL30->count() . " keyword campaigns in L30 range");

        $campaignUpdates = [];
        $campaignIdsToUpdate = [];
        $budgetsToUpdate = [];
        $updateDetails = [];

        foreach ($fbaData as $fba) {
            $sellerSku = $fba->seller_sku;
            $sellerSkuUpper = strtoupper(trim($sellerSku));
            
            // Get base SKU (without FBA) for Shopify data
            $baseSku = preg_replace('/\s*FBA\s*/i', '', $sellerSku);
            $baseSkuUpper = strtoupper(trim($baseSku));

            $shopify = $shopifyData[$baseSkuUpper] ?? null;
            
            // Double check: Skip if zero inventory (use FBA table's quantity_available)
            $quantityAvailable = (int)($fba->quantity_available ?? 0);
            if ($quantityAvailable <= 0) {
                $this->warn("Skipping SKU {$sellerSku} - Zero inventory (quantity_available: {$quantityAvailable})");
                continue;
            }

            // Match campaigns for this FBA SKU (keywords only - not ending with PT) - only ENABLED
            // Use exact match or campaign name starts/ends with SKU to avoid partial matches
            $matchedCampaignsL30 = $amazonSpCampaignReportsL30->filter(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));

                // Exact match OR campaign name equals SKU OR campaign name starts with SKU followed by space/end
                $exactMatch = ($cleanName === $sellerSkuUpper);
                $startsWithMatch = (str_starts_with($cleanName, $sellerSkuUpper . ' ') || str_starts_with($cleanName, $sellerSkuUpper . '.'));
                $endsWithMatch = (str_ends_with($cleanName, ' ' . $sellerSkuUpper) || str_ends_with($cleanName, '.' . $sellerSkuUpper));
                
                // Also check if campaign name contains SKU as a whole word (not substring)
                $containsAsWord = preg_match('/\b' . preg_quote($sellerSkuUpper, '/') . '\b/', $cleanName);

                return (
                    ($exactMatch || $startsWithMatch || $endsWithMatch || $containsAsWord)
                    && !str_ends_with($cleanName, ' PT')
                    && !str_ends_with($cleanName, ' PT.')
                    && strtoupper($item->campaignStatus) === 'ENABLED'
                );
            });

            if ($matchedCampaignsL30->isEmpty()) {
                $this->warn("No ENABLED campaign found for SKU: {$sellerSku}");
                continue;
            }

            // Warn if multiple campaigns match
            if ($matchedCampaignsL30->count() > 1) {
                $this->warn("Multiple campaigns found for SKU {$sellerSku}:");
                foreach ($matchedCampaignsL30 as $camp) {
                    $this->warn("  - {$camp->campaignName} (ID: {$camp->campaign_id}, Budget: \${$camp->campaignBudgetAmount})");
                }
            }

            // Get the first matched ENABLED campaign for budget update
            $matchedCampaign = $matchedCampaignsL30->first();
            
            // Log which campaign is being matched for debugging
            $this->line("Matching campaign for SKU {$sellerSku}: {$matchedCampaign->campaignName} (ID: {$matchedCampaign->campaign_id}, Status: {$matchedCampaign->campaignStatus}, Current Budget: \${$matchedCampaign->campaignBudgetAmount})");

            $campaignId = $matchedCampaign->campaign_id ?? '';
            $currentBudget = floatval($matchedCampaign->campaignBudgetAmount ?? 0);

            if (empty($campaignId)) {
                $this->line("Skipping campaign (SKU: {$sellerSku}) - Invalid campaign ID");
                continue;
            }

            // Calculate ACOS from L30 data
            $sales = floatval($matchedCampaign->sales30d ?? 0);
            $spend = floatval($matchedCampaign->spend ?? 0);

            $acos = 0;
            if ($sales > 0) {
                $acos = round(($spend / $sales) * 100, 2);
            } elseif ($spend > 0) {
                $acos = 100;
            }

            // Determine budget value based on ACOS (new rule)
            // ACOS < 10% → budget = 3
            // ACOS 10%-20% → budget = 2
            // ACOS > 20% → budget = 1
            $newBudget = 1;
            if ($acos < 10) {
                $newBudget = 3;
            } elseif ($acos >= 10 && $acos < 20) {
                $newBudget = 2;
            } else {
                $newBudget = 1;
            }

            // CRITICAL: Validate budget is only 1, 2, or 3 - prevent any other values
            if (!in_array($newBudget, [1, 2, 3], true)) {
                $this->error("INVALID BUDGET VALUE for SKU {$sellerSku} Campaign {$campaignId}: {$newBudget}. Skipping update!");
                continue;
            }

            // Avoid duplicate updates for same campaign
            if (!isset($campaignUpdates[$campaignId])) {
                $campaignUpdates[$campaignId] = true;
                $campaignIdsToUpdate[] = $campaignId;
                $budgetsToUpdate[] = $newBudget;
                $updateDetails[] = [
                    'campaign_id' => $campaignId,
                    'campaign_name' => $matchedCampaign->campaignName ?? '',
                    'old_budget' => $currentBudget,
                    'new_budget' => $newBudget,
                    'acos' => $acos,
                    'sales' => $sales,
                    'spend' => $spend
                ];
                
                // Log the budget calculation details
                $this->line("SKU: {$sellerSku} | Inventory: {$quantityAvailable} | Campaign: {$matchedCampaign->campaignName} | ACOS: {$acos}% | Current Budget: \${$currentBudget} | New Budget: \${$newBudget}");
            } else {
                $this->warn("Skipping duplicate campaign ID: {$campaignId} for SKU: {$sellerSku}");
            }
        }

        // Batch update all campaigns in one call
        if (!empty($campaignIdsToUpdate)) {
            // CRITICAL: Validate arrays are aligned and all budgets are valid (1, 2, or 3)
            if (count($campaignIdsToUpdate) !== count($budgetsToUpdate)) {
                $this->error("ARRAY MISALIGNMENT: campaignIds count (" . count($campaignIdsToUpdate) . ") != budgets count (" . count($budgetsToUpdate) . "). Aborting update!");
                return 1;
            }

            // Validate all budget values are 1, 2, or 3
            $invalidBudgets = array_filter($budgetsToUpdate, function($budget) {
                return !in_array($budget, [1, 2, 3], true);
            });

            if (!empty($invalidBudgets)) {
                $this->error("INVALID BUDGET VALUES FOUND: " . implode(', ', $invalidBudgets) . ". Only 1, 2, or 3 allowed. Aborting update!");
                $this->error("Campaign IDs: " . implode(', ', $campaignIdsToUpdate));
                $this->error("Budgets: " . implode(', ', $budgetsToUpdate));
                return 1;
            }

            // Log what will be sent
            $this->info("About to update " . count($campaignIdsToUpdate) . " campaigns with budgets: " . implode(', ', $budgetsToUpdate));

            try {
                $result = $this->acosController->updateAutoAmazonCampaignBgt($campaignIdsToUpdate, $budgetsToUpdate);
                
                if (isset($result['status']) && $result['status'] == 200) {
                    $processedCount = count($campaignIdsToUpdate);
                    foreach ($updateDetails as $detail) {
                        $this->info("Updated FBA KW campaign {$detail['campaign_id']}: Budget=\${$detail['old_budget']} → \${$detail['new_budget']}");
                    }
                    $this->info("Done. Processed: {$processedCount} unique FBA keyword campaign budgets in batch.");
                } else {
                    $this->error("Failed to update FBA KW campaign budgets: " . ($result['error'] ?? 'Unknown error'));
                }
            } catch (\Exception $e) {
                $this->error("Failed to update FBA KW campaign budgets: " . $e->getMessage());
            }
        } else {
            $this->info("No campaigns to update.");
        }

        return 0;
    }
}
