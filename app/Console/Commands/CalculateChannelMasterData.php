<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Models\ChannelMasterCalculatedData;
use App\Http\Controllers\Channels\ChannelMasterController;
use App\Support\Marketplace\ChannelMasterViewsGuard;
use App\Models\LqsHistory;
use App\Models\ProductMaster;
use App\Services\CronMonitor\CronExecutionContext;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CalculateChannelMasterData extends Command
{
    use MonitorsCronExecution;
    use ProcessesUpdatesInChunks;

    protected $signature = 'channel:calculate-data 
                            {--force : Force recalculation even if already calculated today}
                            {--chunk= : Override chunk size (default from cron-monitor config)}';

    protected $description = 'Calculate and store channel master data in pre-calculated table for fast page loads
                              
                              Reverb Calculations (synchronized with /reverb-sales badge):
                              - Uses L30 data including today (not yesterday)
                              - Excludes cancelled/refunded orders
                              - Excludes empty SKU/order_number
                              - Revenue: product_subtotal (fallback to amount)
                              - Profit: (Revenue × 85% margin) - COGS
                              - GPFT %: (Total Profit / L30 Sales) × 100
                              
                              LQS Calculations:
                              - Calculates Total INV, Total OV L30, Avg DIL%, Avg LQS
                              - Stores daily snapshot in lqs_history table
                              - Data displayed in dashboard LQS badges and trend charts';

    protected string $monitorJobName = 'Channel Calculate Data';

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeCalculate($m),
            $this->monitorJobName
        );
    }

    protected function executeCalculate(CronExecutionContext $monitor): int
    {
        $startTime = microtime(true);
        $this->info('Starting channel master data calculation...');
        $chunkSize = $this->monitoredChunkSize();

        if (!$this->option('force') && ChannelMasterCalculatedData::isDataFresh()) {
            $lastCalc = ChannelMasterCalculatedData::getLastCalculationTime();
            $this->warn("Data already calculated today at {$lastCalc}");
            $this->info('Use --force flag to recalculate anyway.');
            $monitor->setExpected(0);
            return self::SUCCESS;
        }

        try {
            $this->info('Fetching channel data from controller...');

            $controller = app(ChannelMasterController::class);
            $request = new Request();

            $responseData = $controller->getViewChannelData($request);

            if ($responseData instanceof \Illuminate\Http\JsonResponse) {
                $response = $responseData->getData(true);
            } elseif (is_array($responseData)) {
                $response = $responseData;
            } else {
                $this->error('Unexpected response format from controller');
                return self::FAILURE;
            }

            if (empty($response['data'])) {
                $this->error('No channel data found!');
                return self::FAILURE;
            }

            $channels = $response['data'];
            $this->info('Found ' . count($channels) . ' channels to process.');
            $monitor->markApiConnected();
            $monitor->setFetched(count($channels));
            $monitor->setExpected(count($channels));

            try {
                $bar = $this->output->createProgressBar(count($channels));
                $bar->start();

                $calculatedAt = now();
                $dataAsOf = now();

                // DELETE (not TRUNCATE) so a failed run can roll back and keep the
                // previous page-serving rows. MySQL TRUNCATE is DDL and cannot roll back.
                DB::transaction(function () use ($channels, $chunkSize, $calculatedAt, $dataAsOf, $bar, $monitor) {
                    ChannelMasterCalculatedData::query()->delete();
                    $this->newLine();
                    $this->info('Cleared old calculated data.');

                    foreach (array_chunk($channels, $chunkSize) as $chunkIndex => $chunk) {
                        foreach ($chunk as $channelData) {
                            $channelName = $channelData['Channel '] ?? $channelData['Channel'] ?? 'Unknown';

                            if ($channelName === 'Reverb') {
                                $this->newLine();
                                $this->info("Processing Reverb with updated calculations:");
                                $this->info("  - L30 Sales: " . ($channelData['L30 Sales'] ?? 'N/A'));
                                $this->info("  - GPFT %: " . ($channelData['Gprofit%'] ?? 'N/A'));
                                $this->info("  - G ROI: " . ($channelData['G Roi'] ?? 'N/A'));
                                $this->info("  - Ads % (Bump): " . ($channelData['Ads%'] ?? 'N/A'));
                                $this->info("  - N PFT %: " . ($channelData['N PFT'] ?? 'N/A'));
                            }

                            if (strcasecmp($channelName, 'Shopify') === 0 || strcasecmp($channelName, 'Shopify B2C') === 0) {
                                $this->newLine();
                                $this->info("Processing {$channelName} listing CVR (/shopify-b2c-pricing):");
                                $this->info("  - Total Views: " . ($channelData['Total Views'] ?? 'N/A'));
                                $this->info("  - Listing CVR: " . (isset($channelData['CVR']) ? $channelData['CVR'] . '%' : 'N/A'));
                            }

                            if (in_array($channelName, ['EbayTwo', 'Ebay 2', 'eBay 2', 'eBay Two'], true)) {
                                $this->newLine();
                                $this->info("Processing {$channelName} listing CVR (/ebay2-tabulator-view):");
                                $this->info("  - Total Views: " . ($channelData['Total Views'] ?? 'N/A'));
                                $this->info("  - Listing CVR: " . (isset($channelData['CVR']) ? $channelData['CVR'] . '%' : 'N/A'));
                            }

                            if ($channelName === 'PLS') {
                                $plsPercentage = \App\Models\MarketplacePercentage::where('marketplace', 'LIKE', '%PLS%')->value('percentage') ?? 100;
                                $this->newLine();
                                $this->info("Processing PLS with actual sales data:");
                                $this->info("  - L30 Sales: " . ($channelData['L30 Sales'] ?? 'N/A'));
                                $this->info("  - Y Sales: " . ($channelData['Y Sales'] ?? 'N/A'));
                                $this->info("  - L60 Sales: " . ($channelData['L-60 Sales'] ?? 'N/A'));
                                $this->info("  - GPFT %: " . ($channelData['Gprofit%'] ?? 'N/A'));
                                $this->info("  - G ROI: " . ($channelData['G Roi'] ?? 'N/A'));
                                $this->info("  - Orders: " . ($channelData['L30 Orders'] ?? 'N/A'));
                                $this->info("  - Using {$plsPercentage}% marketplace percentage from marketplace_percentages table");
                            }

                            // Temu / Temu 2: Spend from temu(_2)_campaign_reports (L30 ads upload)
                            if ($channelName === 'Temu' || $channelName === 'Temu 2') {
                                $this->newLine();
                                $this->info("Processing {$channelName} with campaign report spend:");
                                $this->info("  - L30 Sales: " . ($channelData['L30 Sales'] ?? 'N/A'));
                                $this->info("  - Total Ad Spend: " . ($channelData['Total Ad Spend'] ?? 'N/A'));
                                $this->info("  - KW Spent: " . ($channelData['KW Spent'] ?? 'N/A'));
                                $this->info("  - Ads %: " . ($channelData['Ads%'] ?? 'N/A'));
                                $this->info("  - TACOS %: " . ($channelData['TACOS %'] ?? $channelData['TACOS'] ?? 'N/A'));
                                $this->info("  - N PFT %: " . ($channelData['N PFT'] ?? 'N/A'));
                                $this->info("  - N ROI: " . ($channelData['N ROI'] ?? 'N/A'));
                            }

                            $this->saveChannelData($channelData, $calculatedAt, $dataAsOf);
                            $bar->advance();
                        }
                        $monitor->incrementUpdated(count($chunk));
                        $monitor->incrementProcessed(count($chunk));
                        $monitor->checkpoint(['phase' => 'channels', 'chunk' => $chunkIndex], $monitor->processedRecords);
                    }
                });

                $bar->finish();
                $this->newLine(2);

                $duration = round(microtime(true) - $startTime, 2);
                $this->info("✓ Successfully calculated and stored data for " . count($channels) . " channels");
                $this->info("✓ Calculation completed in {$duration} seconds");
                $this->info("✓ Data calculated at: {$calculatedAt}");

                $this->storeSummaryData($response, $calculatedAt);
                $this->calculateAndStoreLqsData($calculatedAt, $chunkSize);
                try {
                    $this->info('Warming /all-marketplace-master dot-trend cache...');
                    app(ChannelMasterController::class)->warmChannelMetricDotTrends();
                } catch (\Throwable $e) {
                    $this->warn('Dot-trend cache warm failed: '.$e->getMessage());
                }
                $this->calculateAndStoreOnSeaTransitData($calculatedAt);
                ChannelMasterCalculatedData::bumpFastPayloadCache();

                return self::SUCCESS;
            } catch (\Exception $e) {
                throw $e;
            }

        } catch (\Exception $e) {
            $this->error('Error calculating channel data: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            $monitor->classifyAndRecord($e);
            return self::FAILURE;
        }
    }

    private function saveChannelData(array $data, $calculatedAt, $dataAsOf)
    {
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

            'l60_sales' => $parseNumber($data['L-60 Sales'] ?? 0),
            'l30_sales' => $parseNumber($data['L30 Sales'] ?? 0),
            'yesterday_sales' => $parseNumber($data['Y Sales'] ?? 0),
            'l7_sales' => $parseNumber($data['L7 Sales'] ?? 0),
            'growth' => $parseNumber($data['Growth'] ?? 0),
            'l7_vs_30_pace' => $data['L7 vs 30 pace %'] ?? null,

            'l60_orders' => (int) ($data['L60 Orders'] ?? 0),
            'l30_orders' => (int) ($data['L30 Orders'] ?? 0),
            'total_quantity' => (int) ($data['Qty'] ?? 0),

            'gprofit_pct' => $parseNumber($data['Gprofit%'] ?? 0),
            'gprofit_l60' => $parseNumber($data['gprofitL60'] ?? 0),
            'g_roi' => $parseNumber($data['G Roi'] ?? 0),
            'g_roi_l60' => $parseNumber($data['G RoiL60'] ?? 0),
            'total_profit' => $parseNumber($data['Total PFT'] ?? 0),
            'n_pft' => $parseNumber($data['N PFT'] ?? 0),
            'n_roi' => $parseNumber($data['N ROI'] ?? 0),
            // Channel rows use "TACOS %" (with space); fast-path cache uses "TACOS"
            'tacos_percentage' => $parseNumber($data['TACOS %'] ?? $data['TACOS'] ?? $data['Ads%'] ?? 0),
            'cogs' => $parseNumber($data['cogs'] ?? 0),

            // Prefer Total Ad Spend; fall back to KW Spent (Temu / Temu 2 ads upload)
            'total_ad_spend' => $parseNumber($data['Total Ad Spend'] ?? $data['KW Spent'] ?? 0),
            'ads_percentage' => $parseNumber($data['Ads%'] ?? $data['TACOS %'] ?? $data['TACOS'] ?? 0),
            'clicks' => (int) ($data['Clicks'] ?? 0),
            'ad_sold' => (int) ($data['Ad Sold'] ?? 0),
            'ad_sales' => $parseNumber($data['Ad Sales'] ?? 0),
            'cvr' => $parseNumber($data['Ads CVR'] ?? 0),
            'acos' => $parseNumber($data['ACOS'] ?? 0),
            'missing_ads' => (int) ($data['Missing Ads'] ?? 0),

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

            'listed_count' => (int) ($data['listed_count'] ?? 0),
            'w_ads' => (int) ($data['W/Ads'] ?? 0),
            'map' => (int) ($data['Map'] ?? 0),
            'miss' => (int) ($data['Miss'] ?? 0),
            'nmap' => (int) ($data['NMap'] ?? 0),
            'total_views' => (int) $this->stabilizeChannelViews(
                strtolower(str_replace([' ', '-', '&', '/'], '', trim((string) ($data['Channel '] ?? $data['Channel'] ?? '')))),
                $parseNumber($data['Total Views'] ?? 0),
                (float) ($data['Qty'] ?? 0)
            ),
            'listing_cvr' => array_key_exists('CVR', $data) && $data['CVR'] !== null && $data['CVR'] !== ''
                ? $parseNumber($data['CVR'])
                : null,

            'nr' => (int) ($data['NR'] ?? 0),
            'update_flag' => $this->normalizeUpdateFlag($data['Update'] ?? null),
            'red_margin' => $parseNumber($data['red_margin'] ?? 0),

            'account_health' => isset($data['Account health']) ? ['data' => $data['Account health']] : null,
            'reviews_data' => isset($data['Reviews']) ? ['data' => $data['Reviews']] : null,

            'calculated_at' => $calculatedAt,
            'data_as_of' => $dataAsOf,
        ]);
    }

    /**
     * Temu / Temu 2 Views come from /temu1-data and /temu2-decrease sheet data.
     * Do not carry a stale ChannelMasterViewsGuard number.
     */
    private function stabilizeChannelViews(string $channelKey, float $candidateViews, float $candidateQty): float
    {
        if ($channelKey === 'temu' || $channelKey === 'temu2') {
            return $candidateViews;
        }

        return ChannelMasterViewsGuard::stabilize($channelKey, $candidateViews, $candidateQty);
    }

    private function normalizeUpdateFlag($raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $val = trim((string) $raw);
        if ($val === '' || $val === '0') {
            return null;
        }
        $val = strtoupper($val);
        return in_array($val, ['A', 'S'], true) ? $val : null;
    }

    private function storeSummaryData(array $response, $calculatedAt)
    {
        \Cache::put('channel_master_summary_data', [
            'inventory_value_amazon' => $response['inventory_value_amazon'] ?? 0,
            'inv_at_lp' => $response['inv_at_lp'] ?? 0,
            'inv_at_sp' => $response['inv_at_sp'] ?? 0,
            'shopify_inv_sum' => $response['shopify_inv_sum'] ?? 0,
            'shopify_weighted_avg_lp' => $response['shopify_weighted_avg_lp'] ?? 0,
            'inventory_by_color' => $response['inventory_by_color'] ?? [],
            'stock_availability' => $response['stock_availability'] ?? ['zero_stock' => 0, 'in_stock' => 0],
            'ad_spend_by_channel' => $response['ad_spend_by_channel'] ?? [],
            'sales_by_channel' => $response['sales_by_channel'] ?? [],
            'ad_spend_by_color_amazon' => $response['ad_spend_by_color_amazon'] ?? [],
            'ad_spend_by_color_by_channel' => $response['ad_spend_by_color_by_channel'] ?? [],
            'calculated_at' => $calculatedAt,
        ], 86400);
    }

    private function calculateAndStoreLqsData($calculatedAt, int $chunkSize)
    {
        $this->info('Calculating LQS data...');

        try {
            $total_inv = 0;
            $total_ov = 0;
            $total_dil_weighted = 0;
            $total_lqs_sum = 0;
            $lqs_count = 0;

            ProductMaster::query()
                ->from('product_master')
                ->select('product_master.id')
                ->selectRaw('product_master.parent as parent_sku')
                ->selectRaw('product_master.sku as sku')
                ->leftJoin('shopify_skus', function ($join) {
                    $join->on(DB::raw('TRIM(REPLACE(UPPER(product_master.sku), " ", ""))'), '=',
                              DB::raw('TRIM(REPLACE(UPPER(shopify_skus.sku), " ", ""))'));
                })
                ->leftJoin('junglescout_product_data', function ($join) {
                    $join->on(DB::raw('TRIM(REPLACE(UPPER(product_master.sku), " ", ""))'), '=',
                              DB::raw('TRIM(REPLACE(UPPER(junglescout_product_data.sku), " ", ""))'));
                })
                ->selectRaw('COALESCE(shopify_skus.inv, 0) as inv')
                ->selectRaw('COALESCE(shopify_skus.quantity, 0) as ov_l30')
                ->selectRaw('CAST(JSON_UNQUOTE(JSON_EXTRACT(junglescout_product_data.data, "$.listing_quality_score")) AS DECIMAL(10,2)) as lqs')
                ->whereNotNull('product_master.parent')
                ->orderBy('product_master.id')
                ->chunkById($chunkSize, function ($skuData) use (
                    &$total_inv,
                    &$total_ov,
                    &$total_dil_weighted,
                    &$total_lqs_sum,
                    &$lqs_count
                ) {
                    foreach ($skuData as $row) {
                        $inv = floatval($row->inv);
                        $ov = floatval($row->ov_l30);
                        $lqs = floatval($row->lqs);

                        $total_inv += $inv;
                        $total_ov += $ov;

                        if ($ov > 0) {
                            $dil = ($inv / $ov) * 100;
                            $total_dil_weighted += ($dil * $inv);
                        }

                        if ($lqs > 0) {
                            $total_lqs_sum += $lqs;
                            $lqs_count++;
                        }
                    }
                }, 'product_master.id', 'id');

            $avg_dil = $total_inv > 0 ? $total_dil_weighted / $total_inv : 0;
            $avg_lqs = $lqs_count > 0 ? $total_lqs_sum / $lqs_count : 0;

            $today = now()->toDateString();

            LqsHistory::updateOrCreate(
                ['date' => $today],
                [
                    'total_inv' => round($total_inv, 2),
                    'total_ov' => round($total_ov, 2),
                    'avg_dil' => round($avg_dil, 2),
                    'avg_lqs' => round($avg_lqs, 2),
                    'updated_at' => $calculatedAt
                ]
            );

            $this->info("✓ LQS data stored: Total INV={$total_inv}, Total OV={$total_ov}, Avg DIL={$avg_dil}%, Avg LQS={$avg_lqs}");

        } catch (\Exception $e) {
            $this->error('Error calculating LQS data: ' . $e->getMessage());
            \Log::error('LQS calculation error: ' . $e->getMessage());
        }
    }

    private function calculateAndStoreOnSeaTransitData($calculatedAt)
    {
        $this->info('Calculating On Sea Transit data...');

        try {
            $onSeaPlanningCount = \App\Models\OnSeaTransit::where('status', 'Planning')->count();
            $onSeaTotalCount = \App\Models\OnSeaTransit::count();
            $onSeaArrivedCount = \App\Models\OnSeaTransit::where('status', 'Arrived')->count();
            $onSeaRemainingCount = $onSeaTotalCount - ($onSeaArrivedCount + $onSeaPlanningCount);

            $onSeaTotalValue = \App\Models\OnSeaTransit::sum('invoice_value') ?? 0;
            $onSeaPendingAmount = \App\Models\OnSeaTransit::sum('balance') ?? 0;

            \Cache::put('on_sea_transit_stats', [
                'planning_count' => $onSeaPlanningCount,
                'remaining_count' => $onSeaRemainingCount,
                'total_value' => round($onSeaTotalValue, 2),
                'pending_amount' => round($onSeaPendingAmount, 2),
                'calculated_at' => $calculatedAt
            ], 86400);

            $this->info("✓ On Sea Transit data cached: Planning={$onSeaPlanningCount}, Remaining={$onSeaRemainingCount}, Total Value=\${$onSeaTotalValue}, Pending=\${$onSeaPendingAmount}");

        } catch (\Exception $e) {
            $this->error('Error calculating On Sea Transit data: ' . $e->getMessage());
            \Log::error('On Sea Transit calculation error: ' . $e->getMessage());
        }
    }
}
