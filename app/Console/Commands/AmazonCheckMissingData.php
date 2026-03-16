<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\AmazonSpCampaignReport;

class AmazonCheckMissingData extends Command
{
    protected $signature = 'amazon:check-missing-data {--ad-type=SPONSORED_PRODUCTS : Ad type to check}';
    protected $description = 'List campaigns that have L30 spend but missing L7 and/or L1 data in amazon_sp_campaign_reports';

    public function handle()
    {
        $adType = $this->option('ad-type');

        $this->info("Checking for campaigns with L30 spend but missing L7/L1 data (ad_type: {$adType})");
        $this->info(str_repeat('=', 80));

        // Get distinct campaigns with L30 spend > 0
        $l30Rows = AmazonSpCampaignReport::where('ad_type', $adType)
            ->where('report_date_range', 'L30')
            ->where('spend', '>', 0)
            ->whereNotNull('campaign_id')
            ->get(['campaign_id', 'campaignName', 'spend', 'campaignStatus']);

        if ($l30Rows->isEmpty()) {
            $this->warn('No campaigns found with L30 spend > 0.');
            return 0;
        }

        $missing = [];
        $seen = [];
        foreach ($l30Rows as $l30) {
            $campaignId = $l30->campaign_id;
            if (isset($seen[$campaignId])) continue;
            $seen[$campaignId] = true;

            $hasL7 = AmazonSpCampaignReport::where('campaign_id', $campaignId)
                ->where('ad_type', $adType)
                ->where('report_date_range', 'L7')
                ->exists();
            $hasL1 = AmazonSpCampaignReport::where('campaign_id', $campaignId)
                ->where('ad_type', $adType)
                ->where('report_date_range', 'L1')
                ->exists();

            if (!$hasL7 || !$hasL1) {
                $missing[] = [
                    'campaign_id' => $campaignId,
                    'campaignName' => $l30->campaignName ?? 'N/A',
                    'l30_spend' => floatval($l30->spend ?? 0),
                    'has_l7' => $hasL7,
                    'has_l1' => $hasL1,
                ];
            }
        }

        if (empty($missing)) {
            $this->info('All campaigns with L30 spend have L7 and L1 data.');
            return 0;
        }

        $this->warn('Found ' . count($missing) . ' campaign(s) with L30 spend but missing L7 and/or L1 data:');
        $this->line('');

        foreach ($missing as $m) {
            $missingParts = [];
            if (!$m['has_l7']) $missingParts[] = 'L7';
            if (!$m['has_l1']) $missingParts[] = 'L1';
            $this->line("  Campaign ID: {$m['campaign_id']}");
            $this->line("  Name: {$m['campaignName']}");
            $this->line("  L30 Spend: $" . number_format($m['l30_spend'], 2));
            $this->line("  Missing: " . implode(', ', $missingParts));
            $this->line(str_repeat('-', 80));
        }

        $this->line('');
        $this->info('These campaigns may be skipped by under/over-utilized jobs until L7/L1 data syncs.');
        $this->info('The campaign report sync job (app:amazon-sp-campaign-reports) fetches L30, L7, L1 daily.');

        return 0;
    }
}
