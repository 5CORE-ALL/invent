<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AmazonDataView;
use App\Models\AmazonSpCampaignReport;
use App\Models\AmazonSbCampaignReport;
use App\Models\AmazonUtilizationCount;
use Illuminate\Support\Facades\Log;

class StoreAmazonUtilizationCounts extends Command
{
    protected $signature = 'amazon:store-utilization-counts';
    protected $description = 'Store daily counts of over/under/correctly utilized Amazon KW, PT, and HL campaigns';

    public function handle()
    {
        $this->info('Starting to store Amazon utilization counts...');

        // Process KW, PT, and HL campaigns
        $this->processCampaignType('KW');
        $this->processCampaignType('PT');
        $this->processCampaignType('HL');

        Log::info('amazon:store-utilization-counts finished');

        return 0;
    }

    private function processCampaignType($campaignType)
    {
        $this->info("Processing {$campaignType} campaigns...");
        $campaignMap = [];
        $campaignTypeUpper = strtoupper((string) $campaignType);

        if ($campaignTypeUpper === 'HL') {
            $baseCampaigns = AmazonSbCampaignReport::query()
                ->where('ad_type', 'SPONSORED_BRANDS')
                ->where('report_date_range', 'L30')
                ->whereNotNull('campaign_id')
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->orderBy('campaignName')
                ->get()
                ->unique('campaign_id');

            foreach ($baseCampaigns as $baseCampaign) {
                $campaignId = trim((string) ($baseCampaign->campaign_id ?? ''));
                $campaignName = trim((string) ($baseCampaign->campaignName ?? ''));
                if ($campaignId === '' || $campaignName === '') {
                    continue;
                }

                $detectedType = $this->detectCampaignType($campaignName, 'hl');
                if ($detectedType !== 'hl') {
                    continue;
                }

                $matchedCampaignL7 = AmazonSbCampaignReport::query()
                    ->where('campaign_id', $campaignId)
                    ->where('report_date_range', 'L7')
                    ->latest('id')
                    ->first();
                $matchedCampaignL1 = AmazonSbCampaignReport::query()
                    ->where('campaign_id', $campaignId)
                    ->where('report_date_range', 'L1')
                    ->latest('id')
                    ->first();

                $this->info("Processing campaign: {$campaignName} (Type: HL)");

                $campaignMap[$campaignId] = [
                    'campaign_id' => $campaignId,
                    'campaignName' => $campaignName,
                    'budget' => $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? ($baseCampaign->campaignBudgetAmount ?? 0)),
                    'l7_spend' => $matchedCampaignL7->cost ?? 0,
                    'l1_spend' => $matchedCampaignL1->cost ?? 0,
                    'inv' => 1, // Keep campaign eligible even when SKU mapping is unavailable.
                ];
            }
        } else {
            $baseCampaigns = AmazonSpCampaignReport::query()
                ->where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', 'L30')
                ->whereNotNull('campaign_id')
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->orderBy('campaignName')
                ->get()
                ->unique('campaign_id');

            foreach ($baseCampaigns as $baseCampaign) {
                $campaignId = trim((string) ($baseCampaign->campaign_id ?? ''));
                $campaignName = trim((string) ($baseCampaign->campaignName ?? ''));
                if ($campaignId === '' || $campaignName === '') {
                    continue;
                }

                $detectedType = $this->detectCampaignType($campaignName, 'kw');
                if (($campaignTypeUpper === 'PT' && $detectedType !== 'pt')
                    || ($campaignTypeUpper === 'KW' && $detectedType !== 'kw')) {
                    continue;
                }

                $matchedCampaignL7 = AmazonSpCampaignReport::query()
                    ->where('campaign_id', $campaignId)
                    ->where('report_date_range', 'L7')
                    ->latest('id')
                    ->first();
                $matchedCampaignL1 = AmazonSpCampaignReport::query()
                    ->where('campaign_id', $campaignId)
                    ->where('report_date_range', 'L1')
                    ->latest('id')
                    ->first();

                $this->info("Processing campaign: {$campaignName} (Type: ".strtoupper($detectedType).')');

                $campaignMap[$campaignId] = [
                    'campaign_id' => $campaignId,
                    'campaignName' => $campaignName,
                    'budget' => $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? ($baseCampaign->campaignBudgetAmount ?? 0)),
                    'l7_spend' => $matchedCampaignL7->spend ?? 0,
                    'l1_spend' => $matchedCampaignL1->spend ?? 0,
                    'inv' => 1, // Keep campaign eligible even when SKU mapping is unavailable.
                ];
            }
        }

        // Now count unique campaigns from campaignMap
        $overUtilizedCount7ub = 0;
        $underUtilizedCount7ub = 0;
        $correctlyUtilizedCount7ub = 0;
        
        $overUtilizedCount7ub1ub = 0;
        $underUtilizedCount7ub1ub = 0;
        $correctlyUtilizedCount7ub1ub = 0;
        
        $typeKey = match ($campaignType) {
            'KW' => 'kw',
            'PT' => 'pt',
            'HL' => 'hl',
            default => strtolower($campaignType),
        };

        foreach ($campaignMap as $campaignId => $campaignData) {
            $budget = $campaignData['budget'] ?? 0;
            $l7_spend = $campaignData['l7_spend'] ?? 0;
            $l1_spend = $campaignData['l1_spend'] ?? 0;
            
            // Calculate UB7 and UB1
            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            $ub1 = $budget > 0 ? ($l1_spend / ($budget * 1)) * 100 : 0;

            try {
                AmazonUtilizationCount::updateOrCreate(
                    [
                        'campaign_id' => $campaignId,
                        'campaign_type' => $typeKey,
                    ],
                    [
                        'campaign_name' => (string) ($campaignData['campaignName'] ?? ''),
                        'ub7' => round($ub7, 2),
                        'ub1' => round($ub1, 2),
                        'inventory' => (int) ($campaignData['inv'] ?? 0),
                    ]
                );
                if ($this->output->isVerbose()) {
                    $this->line("  utilization upsert: {$campaignId} ({$typeKey}) ub7=".round($ub7, 2).'% ub1='.round($ub1, 2).'%');
                }
            } catch (\Throwable $e) {
                Log::warning('StoreAmazonUtilizationCounts: failed to upsert utilization row', [
                    'campaign_id' => $campaignId,
                    'campaign_type' => $typeKey,
                    'error' => $e->getMessage(),
                ]);
            }
            
            // Categorize based on 7UB only condition
            if ($ub7 > 90) {
                $overUtilizedCount7ub++;
            } elseif ($ub7 < 70) {
                $underUtilizedCount7ub++;
            } elseif ($ub7 >= 70 && $ub7 <= 90) {
                $correctlyUtilizedCount7ub++;
            }
            
            // Categorize based on 7UB + 1UB condition
            if ($ub7 > 90 && $ub1 > 90) {
                $overUtilizedCount7ub1ub++;
            } elseif ($ub7 < 70 && $ub1 < 70) {
                $underUtilizedCount7ub1ub++;
            } elseif ($ub7 >= 70 && $ub7 <= 90 && $ub1 >= 70 && $ub1 <= 90) {
                $correctlyUtilizedCount7ub1ub++;
            }
        }

        // Store in amazon_data_view table with date as SKU
        $today = now()->format('Y-m-d');
        $tomorrow = now()->copy()->addDay()->format('Y-m-d');
        
        // Data for today (with actual counts)
        $data = [
            // 7UB only condition
            'over_utilized_7ub' => $overUtilizedCount7ub,
            'under_utilized_7ub' => $underUtilizedCount7ub,
            'correctly_utilized_7ub' => $correctlyUtilizedCount7ub,
            // 7UB + 1UB condition
            'over_utilized_7ub_1ub' => $overUtilizedCount7ub1ub,
            'under_utilized_7ub_1ub' => $underUtilizedCount7ub1ub,
            'correctly_utilized_7ub_1ub' => $correctlyUtilizedCount7ub1ub,
            'date' => $today
        ];

        // Blank data for tomorrow (all counts as 0)
        $blankData = [
            // 7UB only condition
            'over_utilized_7ub' => 0,
            'under_utilized_7ub' => 0,
            'correctly_utilized_7ub' => 0,
            // 7UB + 1UB condition
            'over_utilized_7ub_1ub' => 0,
            'under_utilized_7ub_1ub' => 0,
            'correctly_utilized_7ub_1ub' => 0,
            'date' => $tomorrow
        ];

        // Use date as SKU identifier for this data with campaign type
        $skuKeyToday = 'AMAZON_UTILIZATION_' . $campaignType . '_' . $today;
        $skuKeyTomorrow = 'AMAZON_UTILIZATION_' . $campaignType . '_' . $tomorrow;

        // Insert/Update today's data
        $existingToday = AmazonDataView::where('sku', $skuKeyToday)->first();

        if ($existingToday) {
            $existingToday->update(['value' => $data]);
            $this->info("Updated {$campaignType} utilization counts for {$today}");
        } else {
            AmazonDataView::create([
                'sku' => $skuKeyToday,
                'value' => $data
            ]);
            $this->info("Created {$campaignType} utilization counts for {$today}");
        }

        // Insert/Update tomorrow's blank data (only if it doesn't exist)
        $existingTomorrow = AmazonDataView::where('sku', $skuKeyTomorrow)->first();

        if (!$existingTomorrow) {
            AmazonDataView::create([
                'sku' => $skuKeyTomorrow,
                'value' => $blankData
            ]);
            $this->info("Created blank {$campaignType} utilization counts for {$tomorrow}");
        } else {
            $this->info("Tomorrow's data already exists for {$tomorrow}, skipping blank data creation");
        }

        $this->info("{$campaignType} - 7UB Condition:");
        $this->info("  Over-utilized: {$overUtilizedCount7ub}");
        $this->info("  Under-utilized: {$underUtilizedCount7ub}");
        $this->info("  Correctly-utilized: {$correctlyUtilizedCount7ub}");
        $this->info("{$campaignType} - 7UB + 1UB Condition:");
        $this->info("  Over-utilized: {$overUtilizedCount7ub1ub}");
        $this->info("  Under-utilized: {$underUtilizedCount7ub1ub}");
        $this->info("  Correctly-utilized: {$correctlyUtilizedCount7ub1ub}");

        Log::info('StoreAmazonUtilizationCounts: amazon_utilization_counts upserts', [
            'campaign_family' => $campaignType,
            'campaign_type_key' => $typeKey,
            'distinct_campaigns' => count($campaignMap),
        ]);
    }

    private function detectCampaignType(string $campaignName, string $default = 'kw'): string
    {
        $name = strtoupper(trim(preg_replace('/\s+/', ' ', rtrim($campaignName, '.'))));
        if ($name === '') {
            return $default;
        }

        if (preg_match('/\bPT\b/', $name)) {
            return 'pt';
        }
        if (preg_match('/\bKW\b/', $name)) {
            return 'kw';
        }
        if (preg_match('/\b(HL|HEAD)\b/', $name)) {
            return 'hl';
        }

        return $default;
    }
}
