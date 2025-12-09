<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
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

        // Get all FBA records
        $fbaData = FbaTable::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
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

        // Fetch L30 campaign reports for keywords (not ending with PT)
        $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($sellerSkus) {
                foreach ($sellerSkus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% pt'")
            ->get();

        $this->info("Found " . $amazonSpCampaignReportsL30->count() . " keyword campaigns in L30 range");

        $campaignUpdates = [];
        $processedCount = 0;

        foreach ($fbaData as $fba) {
            $sellerSku = $fba->seller_sku;
            $sellerSkuUpper = strtoupper(trim($sellerSku));
            
            // Get base SKU (without FBA) for Shopify data
            $baseSku = preg_replace('/\s*FBA\s*/i', '', $sellerSku);
            $baseSkuUpper = strtoupper(trim($baseSku));

            $shopify = $shopifyData[$baseSkuUpper] ?? null;
            
            // Skip if zero inventory
            if ($shopify && ($shopify->inv ?? 0) <= 0) {
                continue;
            }

            // Match campaigns for this FBA SKU (keywords only - not ending with PT)
            $matchedCampaignsL30 = $amazonSpCampaignReportsL30->filter(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));

                return (
                    str_contains($cleanName, $sellerSkuUpper)
                    && !str_ends_with($cleanName, ' PT')
                    && !str_ends_with($cleanName, ' PT.')
                );
            });

            if ($matchedCampaignsL30->isEmpty()) {
                continue;
            }

            // Get the first matched campaign for budget update
            $matchedCampaign = $matchedCampaignsL30->first();

            $campaignId = $matchedCampaign->campaign_id ?? '';
            $currentBudget = floatval($matchedCampaign->campaignBudgetAmount ?? 0);

            if (empty($campaignId) || $currentBudget <= 0) {
                $this->line("Skipping campaign (SKU: {$sellerSku}) - Invalid campaign ID or budget");
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

            // Determine budget multiplier based on ACOS
            // ACOS < 10% → multiplier 5
            // ACOS 10-20% → multiplier 4
            // ACOS 20-30% → multiplier 3
            // ACOS 30-40% → multiplier 2
            // ACOS > 40% → multiplier 1
            $multiplier = 1;
            if ($acos < 10) {
                $multiplier = 5;
            } elseif ($acos >= 10 && $acos < 20) {
                $multiplier = 4;
            } elseif ($acos >= 20 && $acos < 30) {
                $multiplier = 3;
            } elseif ($acos >= 30 && $acos < 40) {
                $multiplier = 2;
            } else {
                $multiplier = 1;
            }

            $newBudget = $currentBudget * $multiplier;

            // Only update if budget changed significantly
            if (abs($newBudget - $currentBudget) < 0.01) {
                continue; // Budget unchanged
            }

            // Avoid duplicate updates for same campaign
            if (!isset($campaignUpdates[$campaignId])) {
                try {
                    $result = $this->acosController->updateAutoAmazonCampaignBgt([$campaignId], [$newBudget]);
                    
                    if (isset($result['status']) && $result['status'] == 200) {
                        $campaignUpdates[$campaignId] = true;
                        $processedCount++;
                        $this->info("Updated FBA KW campaign {$campaignId} (SKU: {$sellerSku}): Budget=\${$currentBudget} → \${$newBudget} (ACOS={$acos}%, Multiplier={$multiplier})");
                    } else {
                        $this->error("Failed to update FBA KW campaign budget {$campaignId}: " . ($result['error'] ?? 'Unknown error'));
                        Log::error("FBA KW Budget Update Failed", [
                            'campaign_id' => $campaignId,
                            'sku' => $sellerSku,
                            'current_budget' => $currentBudget,
                            'new_budget' => $newBudget,
                            'acos' => $acos,
                            'multiplier' => $multiplier,
                            'error' => $result['error'] ?? 'Unknown error'
                        ]);
                    }
                } catch (\Exception $e) {
                    $this->error("Failed to update FBA KW campaign budget {$campaignId}: " . $e->getMessage());
                    Log::error("FBA KW Budget Update Exception", [
                        'campaign_id' => $campaignId,
                        'sku' => $sellerSku,
                        'current_budget' => $currentBudget,
                        'new_budget' => $newBudget,
                        'acos' => $acos,
                        'multiplier' => $multiplier,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        $this->info("Done. Processed: {$processedCount} unique FBA keyword campaign budgets.");
        Log::info('FBA KW Budget Cron Run', ['processed' => $processedCount]);

        return 0;
    }
}
