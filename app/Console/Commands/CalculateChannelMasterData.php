<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ChannelMasterCalculatedData;
use App\Http\Controllers\Channels\ChannelMasterController;
use Illuminate\Http\Request;

class CalculateChannelMasterData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'channel:calculate-data 
                            {--force : Force recalculation even if already calculated today}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and store channel master data in pre-calculated table for fast page loads
                              
                              Reverb Calculations (synchronized with /reverb-sales badge):
                              - Uses L30 data including today (not yesterday)
                              - Excludes cancelled/refunded orders
                              - Excludes empty SKU/order_number
                              - Revenue: product_subtotal (fallback to amount)
                              - Profit: (Revenue × 85% margin) - COGS
                              - GPFT %: (Total Profit / L30 Sales) × 100';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = microtime(true);
        $this->info('Starting channel master data calculation...');
        
        // Check if already calculated today
        if (!$this->option('force') && ChannelMasterCalculatedData::isDataFresh()) {
            $lastCalc = ChannelMasterCalculatedData::getLastCalculationTime();
            $this->warn("Data already calculated today at {$lastCalc}");
            $this->info('Use --force flag to recalculate anyway.');
            return 0;
        }
        
        try {
            // Get data from the existing controller method
            $this->info('Fetching channel data from controller...');
            
            // Use Laravel's container to resolve the controller with its dependencies
            $controller = app(ChannelMasterController::class);
            $request = new Request();
            
            // Use the existing getViewChannelData method  
            // Note: This method was modified to return array instead of JSON response
            $responseData = $controller->getViewChannelData($request);
            
            // If it's a response object, get the data from it
            if ($responseData instanceof \Illuminate\Http\JsonResponse) {
                $response = $responseData->getData(true);
            } elseif (is_array($responseData)) {
                $response = $responseData;
            } else {
                $this->error('Unexpected response format from controller');
                return 1;
            }
            
            if (empty($response['data'])) {
                $this->error('No channel data found!');
                return 1;
            }
            
            $channels = $response['data'];
            $this->info('Found ' . count($channels) . ' channels to process.');
            
            // Start transaction for data consistency
            \DB::beginTransaction();
            
            try {
                $bar = $this->output->createProgressBar(count($channels));
                $bar->start();
                
                $calculatedAt = now();
                $dataAsOf = now();
                
                // Clear existing data (this will auto-commit/rollback, so we need to handle it)
                \DB::commit(); // Commit any pending transaction before truncate
                ChannelMasterCalculatedData::truncate();
                $this->newLine();
                $this->info('Cleared old calculated data.');
                
                // Start new transaction for inserts
                \DB::beginTransaction();
                
                // Insert new data
                foreach ($channels as $channelData) {
                    $channelName = $channelData['Channel '] ?? $channelData['Channel'] ?? 'Unknown';
                    
                    // Log Reverb-specific calculations
                    if ($channelName === 'Reverb') {
                        $this->newLine();
                        $this->info("Processing Reverb with updated calculations:");
                        $this->info("  - L30 Sales: " . ($channelData['L30 Sales'] ?? 'N/A'));
                        $this->info("  - GPFT %: " . ($channelData['Gprofit%'] ?? 'N/A'));
                        $this->info("  - G ROI: " . ($channelData['G Roi'] ?? 'N/A'));
                        $this->info("  - Ads % (Bump): " . ($channelData['Ads%'] ?? 'N/A'));
                        $this->info("  - N PFT %: " . ($channelData['N PFT'] ?? 'N/A'));
                    }
                    
                    $this->saveChannelData($channelData, $calculatedAt, $dataAsOf);
                    $bar->advance();
                }
                
                \DB::commit();
                $bar->finish();
                $this->newLine(2);
                
                $duration = round(microtime(true) - $startTime, 2);
                $this->info("✓ Successfully calculated and stored data for " . count($channels) . " channels");
                $this->info("✓ Calculation completed in {$duration} seconds");
                $this->info("✓ Data calculated at: {$calculatedAt}");
                
                // Store additional summary data
                $this->storeSummaryData($response, $calculatedAt);
                
                return 0;
                
            } catch (\Exception $e) {
                \DB::rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            $this->error('Error calculating channel data: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
    }
    
    /**
     * Save individual channel data
     */
    private function saveChannelData(array $data, $calculatedAt, $dataAsOf)
    {
        // Helper function to parse numeric values
        $parseNumber = function($value) {
            if (is_numeric($value)) {
                return (float) $value;
            }
            return (float) preg_replace('/[^0-9.-]/', '', (string) $value);
        };
        
        ChannelMasterCalculatedData::create([
            'channel' => $data['Channel '] ?? $data['Channel'] ?? '',
            'sheet_link' => $data['sheet_link'] ?? null,
            'channel_percentage' => $data['channel_percentage'] ?? null,
            'type' => $data['type'] ?? 'B2C',
            'base' => $data['base'] ?? null,
            'target' => $parseNumber($data['target'] ?? 0),
            'missing_link' => $data['missing_link'] ?? null,
            'addition_sheet' => $data['addition_sheet'] ?? null,
            
            // Sales
            'l60_sales' => $parseNumber($data['L-60 Sales'] ?? 0),
            'l30_sales' => $parseNumber($data['L30 Sales'] ?? 0),
            'yesterday_sales' => $parseNumber($data['Y Sales'] ?? 0),
            'l7_sales' => $parseNumber($data['L7 Sales'] ?? 0),
            'growth' => $parseNumber($data['Growth'] ?? 0),
            'l7_vs_30_pace' => $data['L7 vs 30 pace %'] ?? null,
            
            // Orders
            'l60_orders' => (int) ($data['L60 Orders'] ?? 0),
            'l30_orders' => (int) ($data['L30 Orders'] ?? 0),
            'total_quantity' => (int) ($data['Qty'] ?? 0),
            
            // Profit
            'gprofit_pct' => $parseNumber($data['Gprofit%'] ?? 0),
            'gprofit_l60' => $parseNumber($data['gprofitL60'] ?? 0),
            'g_roi' => $parseNumber($data['G Roi'] ?? 0),
            'g_roi_l60' => $parseNumber($data['G RoiL60'] ?? 0),
            'total_profit' => $parseNumber($data['Total PFT'] ?? 0),
            'n_pft' => $parseNumber($data['N PFT'] ?? 0),
            'n_roi' => $parseNumber($data['N ROI'] ?? 0),
            'tacos_percentage' => $parseNumber($data['TACOS'] ?? 0),
            'cogs' => $parseNumber($data['cogs'] ?? 0),
            
            // Ads
            'total_ad_spend' => $parseNumber($data['Total Ad Spend'] ?? 0),
            'ads_percentage' => $parseNumber($data['Ads%'] ?? 0),
            'clicks' => (int) ($data['Clicks'] ?? 0),
            'ad_sold' => (int) ($data['Ad Sold'] ?? 0),
            'ad_sales' => $parseNumber($data['Ad Sales'] ?? 0),
            'cvr' => $parseNumber($data['Ads CVR'] ?? 0),
            'acos' => $parseNumber($data['ACOS'] ?? 0),
            'missing_ads' => (int) ($data['Missing Ads'] ?? 0),
            
            // Ad breakdowns
            'kw_clicks' => (int) ($data['KW Clicks'] ?? 0),
            'pt_clicks' => (int) ($data['PT Clicks'] ?? 0),
            'hl_clicks' => (int) ($data['HL Clicks'] ?? 0),
            'pmt_clicks' => (int) ($data['PMT Clicks'] ?? 0),
            'shopping_clicks' => (int) ($data['Shopping Clicks'] ?? 0),
            'serp_clicks' => (int) ($data['SERP Clicks'] ?? 0),
            
            'kw_sales' => $parseNumber($data['KW Sales'] ?? 0),
            'pt_sales' => $parseNumber($data['PT Sales'] ?? 0),
            'hl_sales' => $parseNumber($data['HL Sales'] ?? 0),
            'pmt_sales' => $parseNumber($data['PMT Sales'] ?? 0),
            'shopping_sales' => $parseNumber($data['Shopping Sales'] ?? 0),
            'serp_sales' => $parseNumber($data['SERP Sales'] ?? 0),
            
            'kw_sold' => (int) ($data['KW Sold'] ?? 0),
            'pt_sold' => (int) ($data['PT Sold'] ?? 0),
            'hl_sold' => (int) ($data['HL Sold'] ?? 0),
            'pmt_sold' => (int) ($data['PMT Sold'] ?? 0),
            'shopping_sold' => (int) ($data['Shopping Sold'] ?? 0),
            'serp_sold' => (int) ($data['SERP Sold'] ?? 0),
            
            'kw_acos' => $parseNumber($data['KW ACOS'] ?? 0),
            'pt_acos' => $parseNumber($data['PT ACOS'] ?? 0),
            'hl_acos' => $parseNumber($data['HL ACOS'] ?? 0),
            'pmt_acos' => $parseNumber($data['PMT ACOS'] ?? 0),
            'shopping_acos' => $parseNumber($data['Shopping ACOS'] ?? 0),
            'serp_acos' => $parseNumber($data['SERP ACOS'] ?? 0),
            
            'kw_cvr' => $parseNumber($data['KW CVR'] ?? 0),
            'pt_cvr' => $parseNumber($data['PT CVR'] ?? 0),
            'hl_cvr' => $parseNumber($data['HL CVR'] ?? 0),
            'pmt_cvr' => $parseNumber($data['PMT CVR'] ?? 0),
            'shopping_cvr' => $parseNumber($data['Shopping CVR'] ?? 0),
            'serp_cvr' => $parseNumber($data['SERP CVR'] ?? 0),
            
            // Inventory
            'listed_count' => (int) ($data['listed_count'] ?? 0),
            'w_ads' => (int) ($data['W/Ads'] ?? 0),
            'map' => (int) ($data['Map'] ?? 0),
            'miss' => (int) ($data['Miss'] ?? 0),
            'nmap' => (int) ($data['NMap'] ?? 0),
            'total_views' => (int) ($data['Total Views'] ?? 0),
            
            // Other
            'nr' => (int) ($data['NR'] ?? 0),
            'update_flag' => (int) ($data['Update'] ?? 0),
            'red_margin' => $parseNumber($data['red_margin'] ?? 0),
            
            // Store account health and reviews as JSON if present
            'account_health' => isset($data['Account health']) ? ['data' => $data['Account health']] : null,
            'reviews_data' => isset($data['Reviews']) ? ['data' => $data['Reviews']] : null,
            
            'calculated_at' => $calculatedAt,
            'data_as_of' => $dataAsOf,
        ]);
    }
    
    /**
     * Store summary data (inventory values, etc.)
     */
    private function storeSummaryData(array $response, $calculatedAt)
    {
        // Store summary data in cache or separate table
        \Cache::put('channel_master_summary_data', [
            'inventory_value_amazon' => $response['inventory_value_amazon'] ?? 0,
            'inv_at_lp' => $response['inv_at_lp'] ?? 0,
            'shopify_inv_sum' => $response['shopify_inv_sum'] ?? 0,
            'shopify_weighted_avg_lp' => $response['shopify_weighted_avg_lp'] ?? 0,
            'inventory_by_color' => $response['inventory_by_color'] ?? [],
            'stock_availability' => $response['stock_availability'] ?? ['zero_stock' => 0, 'in_stock' => 0],
            'ad_spend_by_channel' => $response['ad_spend_by_channel'] ?? [],
            'sales_by_channel' => $response['sales_by_channel'] ?? [],
            'ad_spend_by_color_amazon' => $response['ad_spend_by_color_amazon'] ?? [],
            'ad_spend_by_color_by_channel' => $response['ad_spend_by_color_by_channel'] ?? [],
            'calculated_at' => $calculatedAt,
        ], 86400); // Cache for 24 hours
    }
}
