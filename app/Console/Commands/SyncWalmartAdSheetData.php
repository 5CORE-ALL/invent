<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\WalmartCampaignReport;

class SyncWalmartAdSheetData extends Command
{
    protected $signature = 'sync:walmart-ad-sheet-data';
    protected $description = 'Sync Walmart campaign performance from Google Apps Script API';

    public function handle()
    {
        try {
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

            $url = "https://script.google.com/macros/s/AKfycbxWwC98yCcPDcXjXfKpbE0dMC74L0YfF0fx2HdG_i3G7BzSjuhD8H9X98byGQymFNbx/exec";

            $response = Http::get($url);

            if (!$response->ok()) {
                $this->error('API Request Failed');
                return 1;
            }

            $json = $response->json();

            if (empty($json)) {
                $this->warn('⚠️ No data received from API');
                return 0;
            }

            // Expected keys: L1, L7, L30
            foreach (['L1', 'L7', 'L30', 'L90'] as $range) {

                if (!isset($json[$range]['data'])) {
                    $this->warn("$range not found");
                    continue;
                }

                $data = $json[$range]['data'] ?? [];
                if (empty($data)) {
                    $this->warn("$range data is empty");
                    continue;
                }

                // Process in chunks to avoid too many connections
                $chunks = array_chunk($data, 100, true);
                foreach ($chunks as $chunk) {
                    foreach ($chunk as $row) {
                $campaignId = $this->idval($row['campaign_id'] ?? null);

                // If no campaign_id → skip
                // if (!$campaignId) {
                //     $this->warn("Skipping due to missing campaign_id → campaign: " . ($row['campaign_name'] ?? '-'));
                //     continue;
                // }

                // Use attributed_sales from Google Sheet (Total Attributed Sales column)
                // Try different possible field name formats
                $sales = $this->num(
                    $row['attributed_sales'] ?? 
                    $row['total_attributed_sales'] ?? 
                    $row['Attributed Sales'] ?? 
                    $row['Total Attributed Sales'] ??
                    null
                );

                        WalmartCampaignReport::updateOrCreate(
                            [
                                'campaign_id'  => $campaignId,
                                'report_range' => $range,
                            ],
                            [
                                'campaignName'  => $row['campaign_name'] ?? null,
                                'budget'        => $this->num($row['daily_budget'] ?? null),
                                'spend'         => $this->num($row['ad_spend'] ?? null),
                                'sales'         => $sales,
                                'cpc'           => $this->num($row['average_cpc'] ?? null),
                                'impressions'   => $this->num($row['impressions'] ?? null),
                                'clicks'        => $this->num($row['clicks'] ?? null),
                                'sold'          => $this->num($row['units_sold'] ?? null),
                                'status'        => $row['campaign_status'] ?? null,
                            ]
                        );
                    }
                    DB::connection()->disconnect();
                }

                $this->info("✅ Synced for $range");
            }

            $this->info("✅ All report ranges processed successfully.");
            return 0;
        } catch (\Exception $e) {
            $this->error("✗ Error occurred: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        } finally {
            DB::connection()->disconnect();
        }
    }

    function num($v) {
        return ($v === "" || $v === null) ? null : $v;
    }

    function idval($v) {
        return ($v === "" || $v === null) ? null : $v;
    }
}
