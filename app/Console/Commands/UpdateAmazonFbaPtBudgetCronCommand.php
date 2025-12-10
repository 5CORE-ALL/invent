<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\MarketPlace\ACOSControl\AmazonACOSController;
use App\Models\AmazonSpCampaignReport;
use App\Models\FbaTable;
use App\Models\ShopifySku;

class UpdateAmazonFbaPtBudgetCronCommand extends Command
{
    protected $signature = 'budget:update-amazon-fba-pt';
    protected $description = 'Update budget for Amazon FBA product target campaigns based on ACOS (L30 data)';

    protected $acosController;

    public function __construct(AmazonACOSController $acosController)
    {
        parent::__construct();
        $this->acosController = $acosController;
    }

    public function handle()
    {
        $this->info('Starting budget update cron for Amazon FBA product target campaigns (ACOS-based)...');

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

        // Fetch L30 campaign reports for product targets (ending with PT)
        $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($sellerSkus) {
                foreach ($sellerSkus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->where(function ($q) {
                $q->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) LIKE '% pt'")
                  ->orWhereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) LIKE '% pt.'");
            })
            ->get();

        $this->info("Found " . $amazonSpCampaignReportsL30->count() . " product target campaigns in L30 range");

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

            // Skip if zero inventory (use FBA table's quantity_available)
            if (($fba->quantity_available ?? 0) <= 0) {
                continue;
            }

            // Match campaigns for this FBA SKU (product targets only - ending with PT)
            $matchedCampaignsL30 = $amazonSpCampaignReportsL30->filter(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));

                return (
                    str_contains($cleanName, $sellerSkuUpper)
                    && (str_ends_with($cleanName, ' PT') || str_ends_with($cleanName, ' PT.'))
                );
            });

            if ($matchedCampaignsL30->isEmpty()) {
                continue;
            }

            // Get the first matched campaign for budget update
            $matchedCampaign = $matchedCampaignsL30->first();

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

            // Determine budget value based on ACOS
            // ACOS < 10% → budget = 5
            // ACOS 10%-20% → budget = 4
            // ACOS 20%-30% → budget = 3
            // ACOS 30%-40% → budget = 2
            // ACOS > 40% → budget = 1
            $newBudget = 1;
            if ($acos < 10) {
                $newBudget = 5;
            } elseif ($acos >= 10 && $acos < 20) {
                $newBudget = 4;
            } elseif ($acos >= 20 && $acos < 30) {
                $newBudget = 3;
            } elseif ($acos >= 30 && $acos < 40) {
                $newBudget = 2;
            } else {
                $newBudget = 1;
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
                    'new_budget' => $newBudget
                ];
            }
        }

        // Batch update all campaigns in one call
        if (!empty($campaignIdsToUpdate)) {
            try {
                $result = $this->acosController->updateAutoAmazonCampaignBgt($campaignIdsToUpdate, $budgetsToUpdate);
                
                if (isset($result['status']) && $result['status'] == 200) {
                    $processedCount = count($campaignIdsToUpdate);
                    foreach ($updateDetails as $detail) {
                        $this->info("Updated FBA PT campaign {$detail['campaign_id']}: Budget=\${$detail['old_budget']} → \${$detail['new_budget']}");
                    }
                    $this->info("Done. Processed: {$processedCount} unique FBA product target campaign budgets in batch.");
                } else {
                    $this->error("Failed to update FBA PT campaign budgets: " . ($result['error'] ?? 'Unknown error'));
                }
            } catch (\Exception $e) {
                $this->error("Failed to update FBA PT campaign budgets: " . $e->getMessage());
            }
        } else {
            $this->info("No campaigns to update.");
        }

        return 0;
    }
}
