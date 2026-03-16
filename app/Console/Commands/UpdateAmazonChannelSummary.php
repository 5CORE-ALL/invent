<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AmazonChannelSummary;
use App\Http\Controllers\MarketPlace\OverallAmazonController;
use App\Http\Controllers\MarketPlace\WalmartSheetUploadController;
use App\Http\Controllers\MarketPlace\TemuController;
use App\Http\Controllers\ApiController;
use App\Services\AmazonDataService;
use Illuminate\Http\Request;

class UpdateAmazonChannelSummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'amazon:save-summary {channel?} {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Save channel summary snapshot for today or specified date (channel: all, amazon, walmart, temu, ebay, etc.)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $channel = strtolower($this->argument('channel') ?? 'all');
        $date = $this->argument('date') ?? now('America/Los_Angeles')->toDateString();
        
        // Handle "all" channels
        if ($channel === 'all') {
            return $this->saveAllChannels($date);
        }
        
        return $this->saveSingleChannel($channel, $date);
    }

    /**
     * Save all supported channels
     */
    private function saveAllChannels($date)
    {
        $this->info("════════════════════════════════════════════");
        $this->info("Saving ALL channel summaries for {$date}");
        $this->info("════════════════════════════════════════════\n");
        
        $supportedChannels = ['amazon', 'walmart', 'temu', 'ebay', 'ebay2', 'ebay3'];
        $successCount = 0;
        $failCount = 0;
        $results = [];
        
        foreach ($supportedChannels as $channel) {
            $this->info("📊 Processing {$channel}...");
            
            try {
                $this->saveSingleChannelSilent($channel, $date);
                $successCount++;
                $results[$channel] = '✓ Success';
                $this->line("   ✓ {$channel} saved successfully");
            } catch (\Exception $e) {
                $failCount++;
                $results[$channel] = '✗ Failed: ' . $e->getMessage();
                $this->error("   ✗ {$channel} failed: " . $e->getMessage());
            }
            
            $this->newLine();
        }
        
        // Summary table
        $this->info("════════════════════════════════════════════");
        $this->info("SUMMARY REPORT");
        $this->info("════════════════════════════════════════════");
        
        $tableData = [];
        foreach ($results as $ch => $status) {
            $tableData[] = [ucfirst($ch), $status];
        }
        
        $this->table(['Channel', 'Status'], $tableData);
        
        $this->newLine();
        $this->info("✓ Completed: {$successCount} channels saved successfully");
        if ($failCount > 0) {
            $this->warn("⚠ Failed: {$failCount} channels had errors");
        }
        
        return $failCount > 0 ? 1 : 0;
    }

    /**
     * Save a single channel with user interaction
     */
    private function saveSingleChannel($channel, $date)
    {
        $this->info("Saving {$channel} summary for {$date} (California Time)...");
        
        // Check if already exists
        $existing = AmazonChannelSummary::where('channel', $channel)
            ->where('snapshot_date', $date)
            ->first();
        if ($existing) {
            if (!$this->confirm("Summary for {$channel} on {$date} already exists. Overwrite?")) {
                $this->info('Cancelled.');
                return 0;
            }
            $existing->delete();
        }
        
        try {
            $this->executeChannelSave($channel, $date);
            
            $this->info('✓ Summary saved successfully!');
            
            // Show summary
            $summary = AmazonChannelSummary::where('channel', $channel)
                ->where('snapshot_date', $date)
                ->first();
            
            if ($summary) {
                $data = $summary->summary_data;
                $this->displaySummaryTable($data, $channel);
            }
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Save a single channel without user interaction (for batch processing)
     */
    private function saveSingleChannelSilent($channel, $date)
    {
        // Delete existing if present (no confirmation in batch mode)
        AmazonChannelSummary::where('channel', $channel)
            ->where('snapshot_date', $date)
            ->delete();
        
        $this->executeChannelSave($channel, $date);
    }

    /**
     * Execute the actual channel save operation
     */
    private function executeChannelSave($channel, $date)
    {
        switch ($channel) {
            case 'amazon':
                $this->saveAmazonSummary($date);
                break;
            case 'walmart':
                $this->saveWalmartSummary($date);
                break;
            case 'temu':
                $this->saveTemuSummary($date);
                break;
            case 'ebay':
            case 'ebay1':
                $this->saveEbaySummary($date);
                break;
            case 'ebay2':
            case 'ebaytwo':
                $this->saveEbay2Summary($date);
                break;
            case 'ebay3':
            case 'ebaythree':
                $this->saveEbay3Summary($date);
                break;
            default:
                throw new \Exception("Unsupported channel: {$channel}. Supported: all, amazon, walmart, temu, ebay, ebay2, ebay3");
        }
    }

    /**
     * Save Amazon summary
     */
    private function saveAmazonSummary($date)
    {
        $apiController = new ApiController();
        $amazonDataService = new AmazonDataService();
        $controller = new OverallAmazonController($apiController, $amazonDataService);
        $request = new Request();
        $controller->amazonDataJson($request);
    }

    /**
     * Save Walmart summary
     */
    private function saveWalmartSummary($date)
    {
        $controller = new WalmartSheetUploadController();
        $request = new Request();
        $controller->getCombinedDataJson();
    }

    /**
     * Save Temu summary
     */
    private function saveTemuSummary($date)
    {
        $controller = new TemuController(new ApiController());
        $controller->getTemuDecreaseData();
    }

    /**
     * Save eBay summary
     */
    private function saveEbaySummary($date)
    {
        $controller = new \App\Http\Controllers\MarketPlace\EbayController(new ApiController());
        $controller->getEbayDecreaseData();
    }

    /**
     * Save eBay 2 summary
     */
    private function saveEbay2Summary($date)
    {
        $controller = new \App\Http\Controllers\MarketPlace\EbayTwoController(new ApiController());
        $controller->getEbayTwoDecreaseData();
    }

    /**
     * Save eBay 3 summary
     */
    private function saveEbay3Summary($date)
    {
        $controller = new \App\Http\Controllers\MarketPlace\EbayThreeController(new ApiController());
        $controller->getEbayThreeDecreaseData();
    }

    /**
     * Display summary table based on channel
     */
    private function displaySummaryTable($data, $channel)
    {
        $commonMetrics = [
            ['Total Products', number_format($data['total_products'] ?? 0)],
            ['Zero Sold', number_format($data['zero_sold_count'] ?? 0)],
            ['Missing Count', number_format($data['missing_count'] ?? 0)],
            ['Mapped Count', number_format($data['mapped_count'] ?? 0)],
            ['Total Revenue', '$' . number_format($data['total_revenue'] ?? 0, 2)],
            ['Total PFT', '$' . number_format($data['total_pft_amt'] ?? $data['total_profit'] ?? 0, 2)],
            ['Total COGS', '$' . number_format($data['total_cogs'] ?? $data['total_lp'] ?? 0, 2)],
            ['Total Spend', '$' . number_format($data['total_spend'] ?? $data['total_spend_l30'] ?? 0, 2)],
            ['Avg Price', '$' . number_format($data['avg_price'] ?? 0, 2)],
            ['Avg GPFT %', round($data['avg_gpft'] ?? $data['avg_gprft'] ?? 0, 2) . '%'],
            ['Avg ROI %', round($data['avg_roi'] ?? $data['avg_groi'] ?? 0, 2) . '%'],
            ['Avg CVR %', round($data['avg_cvr'] ?? 0, 2) . '%'],
        ];
        
        $this->table(['Metric', 'Value'], $commonMetrics);
    }
}
