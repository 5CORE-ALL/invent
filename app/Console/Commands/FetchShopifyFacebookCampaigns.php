<?php

namespace App\Console\Commands;

use App\Jobs\FetchShopifyFacebookCampaignsJob;
use Illuminate\Console\Command;

class FetchShopifyFacebookCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:fetch-facebook-campaigns {--range=all : Date range (7_days, 30_days, 60_days, or all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Facebook campaign data from Shopify for sales and orders (7, 30, 60 days)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $range = $this->option('range');
        
        $validRanges = ['7_days', '30_days', '60_days', 'all'];
        
        if (!in_array($range, $validRanges)) {
            $this->error("Invalid range. Valid options: " . implode(', ', $validRanges));
            return 1;
        }

        $this->info("Starting to fetch Shopify Facebook campaigns data for: {$range}");
        
        try {
            // Dispatch the job
            FetchShopifyFacebookCampaignsJob::dispatch($range);
            
            $this->info("Job dispatched successfully!");
            $this->info("The data will be fetched and stored in the shopify_facebook_campaigns table.");
            $this->line("");
            $this->line("To run immediately (synchronously), use:");
            $this->line("  FetchShopifyFacebookCampaignsJob::dispatchSync('{$range}');");
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to dispatch job: " . $e->getMessage());
            return 1;
        }
    }
}
