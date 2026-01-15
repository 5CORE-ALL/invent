<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AmazonChannelSummary;
use App\Http\Controllers\MarketPlace\OverallAmazonController;
use Illuminate\Http\Request;

class UpdateAmazonChannelSummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'amazon:save-summary {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Save Amazon channel summary snapshot for today or specified date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->argument('date') ?? now()->toDateString();
        
        $this->info("Saving Amazon summary for {$date}...");
        
        // Check if already exists
        $existing = AmazonChannelSummary::where('snapshot_date', $date)->first();
        if ($existing) {
            if (!$this->confirm("Summary for {$date} already exists. Overwrite?")) {
                $this->info('Cancelled.');
                return 0;
            }
            $existing->delete();
        }
        
        try {
            // Get controller and fetch data
            $controller = new OverallAmazonController();
            $request = new Request();
            $response = $controller->amazonDataJson($request);
            
            $this->info('✓ Summary saved successfully!');
            
            // Show summary
            $summary = AmazonChannelSummary::where('snapshot_date', $date)->first();
            if ($summary) {
                $data = $summary->summary_data;
                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['Total SKUs', number_format($data['total_sku_count'] ?? 0)],
                        ['Sold Count', number_format($data['sold_count'] ?? 0)],
                        ['Zero Sold', number_format($data['zero_sold_count'] ?? 0)],
                        ['Prc > LMP', number_format($data['prc_gt_lmp_count'] ?? 0)],
                        ['Total Sales', '$' . number_format($data['total_sales_amt'] ?? 0, 2)],
                        ['Total PFT', '$' . number_format($data['total_pft_amt'] ?? 0, 2)],
                        ['Spend L30', '$' . number_format($data['total_spend_l30'] ?? 0, 2)],
                        ['GROI %', ($data['groi_percent'] ?? 0) . '%'],
                        ['NROI %', ($data['nroi_percent'] ?? 0) . '%'],
                        ['TCOS %', ($data['tcos_percent'] ?? 0) . '%'],
                        ['CVR %', ($data['cvr_percent'] ?? 0) . '%'],
                        ['Avg Price', '$' . number_format($data['avg_price'] ?? 0, 2)],
                    ]
                );
            }
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
