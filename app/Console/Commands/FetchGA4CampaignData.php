<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GA4ApiService;
use App\Models\GoogleAdsCampaign;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FetchGA4CampaignData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ga4:fetch-campaign-data {--days=30 : Number of days to fetch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch actual GA4 campaign data (purchases and revenue) to match GA4 exactly';

    protected $ga4Service;

    public function __construct(GA4ApiService $ga4Service)
    {
        parent::__construct();
        $this->ga4Service = $ga4Service;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $endDate = Carbon::now()->subDays(1)->format('Y-m-d'); // Yesterday (GA4 data is usually 1 day behind)
        $startDate = Carbon::now()->subDays($days)->format('Y-m-d');

        $this->info("Fetching GA4 campaign data for {$days} days...");
        $this->info("Date range: {$startDate} to {$endDate}");

        // Check if GA4 API is configured
        if (empty(config('services.ga4.property_id'))) {
            $this->error('GA4 API not configured!');
            $this->info('Please set the following in .env:');
            $this->info('  GA4_PROPERTY_ID=your-property-id');
            $this->info('  GA4_CLIENT_ID=your-client-id');
            $this->info('  GA4_CLIENT_SECRET=your-client-secret');
            $this->info('  GA4_REFRESH_TOKEN=your-refresh-token');
            $this->info('');
            $this->info('For now, the system will use Google Ads API data.');
            $this->info('Once GA4 API is configured, run this command to fetch actual GA4 data.');
            return 0;
        }

        try {
            // Fetch GA4 data
            $this->info('Fetching data from GA4 API...');
            $ga4Data = $this->ga4Service->getCampaignMetrics($startDate, $endDate);

            // Fetch daily GA4 data directly (more efficient)
            $this->info('Fetching daily GA4 data...');
            $ga4DailyData = $this->ga4Service->getCampaignMetricsDaily($startDate, $endDate);

            if (empty($ga4DailyData)) {
                $this->error('No GA4 data returned.');
                $this->info('');
                $this->info('Common issues:');
                $this->warn('  1. Permission Error: OAuth account needs access to GA4 property');
                $this->warn('     → Go to GA4 → Admin → Property Access Management');
                $this->warn('     → Add the OAuth account email with Viewer/Editor role');
                $this->warn('  2. Property ID: Verify Property ID is correct');
                $this->warn('     → Current: ' . config('services.ga4.property_id'));
                $this->warn('  3. Check storage/logs/laravel.log for detailed errors');
                $this->info('');
                return 1;
            }

            $this->info('Found ' . count($ga4DailyData) . ' campaigns in GA4');

            $updated = 0;
            $notFound = 0;
            $totalRecords = 0;

            // Update database with GA4 daily data
            foreach ($ga4DailyData as $campaignName => $dailyRecords) {
                $campaignNameUpper = strtoupper(trim($campaignName));
                $campaignNameClean = trim($campaignName);
                
                // Find matching campaign in database - try multiple matching strategies
                $dbCampaign = DB::table('google_ads_campaigns')
                    ->where('advertising_channel_type', 'SHOPPING')
                    ->where(function($query) use ($campaignNameUpper, $campaignNameClean) {
                        // Exact match (case-insensitive, trimmed)
                        $query->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$campaignNameUpper])
                              // Partial match from GA4 name
                              ->orWhere('campaign_name', 'LIKE', '%' . $campaignNameClean . '%')
                              // Reverse partial match (database name contains GA4 name)
                              ->orWhereRaw('UPPER(TRIM(campaign_name)) LIKE ?', ['%' . $campaignNameUpper . '%']);
                    })
                    ->select('campaign_id', 'campaign_name')
                    ->distinct()
                    ->first();

                if (!$dbCampaign) {
                    $notFound++;
                    $this->warn("  Not found in DB: {$campaignName}");
                    continue;
                }

                // Update each day's data
                foreach ($dailyRecords as $date => $metrics) {
                    $updatedCount = DB::table('google_ads_campaigns')
                        ->where('campaign_id', $dbCampaign->campaign_id)
                        ->where('date', $date)
                        ->where('advertising_channel_type', 'SHOPPING')
                        ->update([
                            'ga4_actual_sold_units' => $metrics['purchases'],
                            'ga4_actual_revenue' => $metrics['revenue'],
                        ]);

                    if ($updatedCount > 0) {
                        $totalRecords += $updatedCount;
                    }
                }
                
                $totalPurchases = array_sum(array_column($dailyRecords, 'purchases'));
                $totalRevenue = array_sum(array_column($dailyRecords, 'revenue'));
                $updated++;
                $this->info("Updated: {$dbCampaign->campaign_name} - Purchases: {$totalPurchases}, Revenue: \${$totalRevenue}");
            }

            $this->info("\n" . str_repeat('=', 60));
            $this->info("Summary:");
            $this->info("  - Campaigns updated: {$updated}");
            $this->info("  - Total records updated: {$totalRecords}");
            $this->info("  - Campaigns not found in DB: {$notFound}");
            $this->info("  - Total GA4 campaigns: " . count($ga4DailyData));
            $this->info(str_repeat('=', 60));
            $this->info("\n✅ GA4 data has been stored. The view will now use GA4 actual data when available.");

            return 0;
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error('Error in FetchGA4CampaignData', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}
