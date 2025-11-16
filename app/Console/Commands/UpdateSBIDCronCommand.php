<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\GoogleAdsSbidService;
use App\Models\ProductMaster;


class UpdateSBIDCronCommand extends Command
{
    protected $signature = 'sbid:update';
    protected $description = 'Update SBID for AdGroups and Product Groups using L1 range only';

    protected $sbidService;

    public function __construct(GoogleAdsSbidService $sbidService)
    {
        parent::__construct();
        $this->sbidService = $sbidService;
    }

    // public function handle()
    // {
    //     $this->info('Starting SBID update cron for Google campaigns (L1)...');

    //     $customerId = env('GOOGLE_ADS_LOGIN_CUSTOMER_ID');

    //     // Fetch all L1 Google campaigns (you can filter by specific ID if needed)
    //     $googleCampaigns = DB::connection('apicentral')
    //         ->table('google_ads_campaigns')
    //         ->select('campaign_id', 'campaign_name', 'metrics_cost_micros', 'metrics_clicks', 'sbid_status')
    //         ->where('range_type', 'L1')
    //         ->where('id', 1) 
    //         ->get();

    //     $processedCount = 0;

    //     foreach ($googleCampaigns as $campaign) {
    //         if (!empty($campaign->sbid_status) && $campaign->sbid_status == 1) {
    //             continue;
    //         }

    //         $spend_L1 = ($campaign->metrics_cost_micros ?? 0) / 1_000_000;
    //         $clicks_L1 = $campaign->metrics_clicks ?? 0;
    //         $cpc_L1 = $clicks_L1 > 0 ? $spend_L1 / $clicks_L1 : 0;
    //         $sbid = round($cpc_L1 * 0.95, 2);

    //         // Update AdGroups and Product Groups SBIDs in Google Ads
    //         $this->sbidService->updateCampaignSbids($customerId, $campaign->campaign_id,$sbid);
    //         DB::connection('apicentral')
    //             ->table('google_ads_campaigns')
    //             ->where('campaign_id', $campaign->campaign_id)
    //             ->update([
    //                 'sbid_status' => 1
    //             ]);

    //         $processedCount++;
    //     }

    //     $this->info("SBID update completed. Processed {$processedCount} campaigns.");
    //     Log::info('SBID Cron L1 Run', ['processed_campaigns' => $processedCount]);

    //     return 0;
    // }
    public function handle()
    {
        $this->info('Starting SBID update cron for Google campaigns (L1/L7 with SKU matching)...');

        $customerId = env('GOOGLE_ADS_LOGIN_CUSTOMER_ID');

        // Same as controller: Fetch product masters and SKUs
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $googleCampaigns = DB::connection('apicentral')
            ->table('google_ads_campaigns')
            ->select(
                'campaign_id',
                'campaign_name',
                'campaign_status',
                'budget_amount_micros',
                'range_type',
                'metrics_cost_micros',
                'metrics_clicks',
                'sbid_status'
            )->where('campaign_status','ENABLED')
            ->where('sbid_status',0)
            ->get();

        $ranges = ['L1', 'L7']; 

        $campaignUpdates = []; 

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));
            $matchedCampaign = $googleCampaigns->first(function ($c) use ($sku) {
                $campaign = strtoupper(trim($c->campaign_name));
                $parts = array_map('trim', explode(',', $campaign));
                return in_array($sku, $parts);
            });

            if (!$matchedCampaign || !empty($matchedCampaign->sbid_status) && $matchedCampaign->sbid_status == 1) {
                continue;
            }

            $campaignId = $matchedCampaign->campaign_id;
            $row = [];
            $row['campaignBudgetAmount'] = $matchedCampaign->budget_amount_micros ? $matchedCampaign->budget_amount_micros / 1000000 : null;

            foreach ($ranges as $range) {
                $campaignRange = $googleCampaigns->first(function ($c) use ($sku, $range) {
                    $campaign = strtoupper(trim($c->campaign_name));
                    $parts = array_map('trim', explode(',', $campaign));
                    return in_array($sku, $parts) && $c->range_type === $range;
                });

                $row["spend_$range"] = isset($campaignRange->metrics_cost_micros)
                    ? $campaignRange->metrics_cost_micros / 1000000
                    : 0;
                    Log::info('Spend Calculation:', [
    'campaign_id' => $campaignId ?? null,
    'range' => $range,
    'metrics_cost_micros' => $campaignRange->metrics_cost_micros ?? null,
    'calculated_spend' => $row["spend_$range"],
]);

                $row["clicks_$range"] = $campaignRange->metrics_clicks ?? 0;
                $row["cpc_$range"] = $row["clicks_$range"] ? $row["spend_$range"] / $row["clicks_$range"] : 0;
            }
    
            $ub7 = $row['campaignBudgetAmount'] > 0 ? ($row["spend_L7"] / ($row['campaignBudgetAmount'] * 7)) * 100 : 0;
                    Log::info('UB7 Calculation:', [
    'campaign_id' => $row['campaign_id'] ?? null,
    'spend_L7' => $row['spend_L7'] ?? null,
    'campaignBudgetAmount' => $row['campaignBudgetAmount'] ?? null,
    'ub7' => $ub7,
]);
                

            $sbid = 0;
            $cpc_L1 = isset($row["cpc_L1"]) ? floatval($row["cpc_L1"]) : 0;
            $cpc_L7 = isset($row["cpc_L7"]) ? floatval($row["cpc_L7"]) : 0;

            if ($ub7 > 90) {
                // $sbid = round($row["cpc_L1"] * 0.95, 2);
                 $maxCpc = max($cpc_L1, $cpc_L7); 
                 $sbid = round($maxCpc * 0.95, 2);
                 dd($sbid);
            } elseif ($ub7 < 70) {
                $maxCpc = max($row["cpc_L1"], $row["cpc_L7"]);
                $sbid = round($maxCpc * 1.05, 2);
            } else {
                continue;
            }
            if ($sbid > 0 && !isset($campaignUpdates[$campaignId])) {
                $this->sbidService->updateCampaignSbids($customerId, $campaignId, $sbid);
                DB::connection('apicentral')
                    ->table('google_ads_campaigns')
                    ->where('campaign_id', $campaignId)
                    ->update(['sbid_status' => 1]);

                $campaignUpdates[$campaignId] = true;
                $this->info("Updated campaign {$campaignId} (SKU: {$pm->sku}): SBID={$sbid} (UB7: {$ub7}%)");
            }
        }

        $processedCount = count($campaignUpdates);
        $this->info("Done. Processed: {$processedCount} unique campaigns.");
        Log::info('SBID Cron Run', ['processed' => $processedCount]);

        return 0;
    }
}
