<?php

namespace App\Console\Commands;

use App\Models\TemuMetric;
use App\Services\TemuApiService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchTemuAdsData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'temu:fetch-ads-data 
                            {--period=L30 : Time period (L30 or L60)}
                            {--goods-id= : Fetch for specific goods ID only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Temu ads data and store in database';

    protected $temuApiService;

    public function __construct(TemuApiService $temuApiService)
    {
        parent::__construct();
        $this->temuApiService = $temuApiService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Temu Ads Data Fetch...');
        Log::info('Starting Temu Ads Data Fetch');

        $period = $this->option('period');
        $specificGoodsId = $this->option('goods-id');

        try {
            if ($specificGoodsId) {
                // Fetch for specific goods ID
                $this->fetchAdsForGoodsId($specificGoodsId, $period);
            } else {
                // Fetch for all goods IDs
                $this->fetchAdsForAllGoods($period);
            }

            $this->info('✅ Temu Ads Data Fetch completed successfully');
            Log::info('Temu Ads Data Fetch completed successfully');
        } catch (\Exception $e) {
            $this->error('Error fetching Temu ads data: ' . $e->getMessage());
            Log::error('Error fetching Temu ads data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Fetch ads data for all goods IDs
     */
    private function fetchAdsForAllGoods($period)
    {
        $this->info("Fetching ads data for period: {$period}");

        // Get all goods IDs from temu_metrics table
        $goodsIds = TemuMetric::whereNotNull('goods_id')
            ->pluck('goods_id')
            ->unique()
            ->toArray();

        if (empty($goodsIds)) {
            $this->warn('No goods IDs found in database. Please run app:fetch-temu-metrics first.');
            return;
        }

        $this->info("Found " . count($goodsIds) . " goods IDs to process");

        $ranges = [
            'L30' => [
                'startTs' => Carbon::now()->subDays(30)->startOfDay()->timestamp * 1000,
                'endTs' => Carbon::yesterday()->endOfDay()->timestamp * 1000,
            ],
            'L60' => [
                'startTs' => Carbon::now()->subDays(60)->startOfDay()->timestamp * 1000,
                'endTs' => Carbon::now()->subDays(31)->endOfDay()->timestamp * 1000,
            ],
        ];

        $range = $ranges[$period] ?? $ranges['L30'];
        $updatedCount = 0;
        $errorCount = 0;

        $bar = $this->output->createProgressBar(count($goodsIds));
        $bar->start();

        foreach ($goodsIds as $goodsId) {
            try {
                $data = $this->temuApiService->fetchAdsData(
                    $goodsId,
                    $range['startTs'],
                    $range['endTs']
                );

                if ($data) {
                    $summary = $data['reportInfo']['reportsSummary'] ?? null;

                    if ($summary) {
                        $updateData = [];
                        
                        if ($period === 'L30') {
                            $updateData = [
                                'product_impressions_l30' => $summary['imprCntAll']['val'] ?? 0,
                                'product_clicks_l30' => $summary['clkCntAll']['val'] ?? 0,
                            ];
                        } elseif ($period === 'L60') {
                            $updateData = [
                                'product_impressions_l60' => $summary['imprCntAll']['val'] ?? 0,
                                'product_clicks_l60' => $summary['clkCntAll']['val'] ?? 0,
                            ];
                        }

                        if (!empty($updateData)) {
                            TemuMetric::where('goods_id', $goodsId)->update($updateData);
                            $updatedCount++;
                        }
                    }
                } else {
                    $errorCount++;
                }
            } catch (\Exception $e) {
                Log::error("Error processing goods ID: {$goodsId}", [
                    'error' => $e->getMessage()
                ]);
                $errorCount++;
            }

            $bar->advance();
            
            // Rate limiting
            usleep(200000); // 0.2 seconds
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ Updated {$updatedCount} records");
        if ($errorCount > 0) {
            $this->warn("⚠️  {$errorCount} records had errors");
        }
    }

    /**
     * Fetch ads data for specific goods ID
     */
    private function fetchAdsForGoodsId($goodsId, $period)
    {
        $this->info("Fetching ads data for Goods ID: {$goodsId} (Period: {$period})");

        $ranges = [
            'L30' => [
                'startTs' => Carbon::now()->subDays(30)->startOfDay()->timestamp * 1000,
                'endTs' => Carbon::yesterday()->endOfDay()->timestamp * 1000,
            ],
            'L60' => [
                'startTs' => Carbon::now()->subDays(60)->startOfDay()->timestamp * 1000,
                'endTs' => Carbon::now()->subDays(31)->endOfDay()->timestamp * 1000,
            ],
        ];

        $range = $ranges[$period] ?? $ranges['L30'];

        $data = $this->temuApiService->fetchAdsData(
            $goodsId,
            $range['startTs'],
            $range['endTs']
        );

        if ($data) {
            $summary = $data['reportInfo']['reportsSummary'] ?? null;

            if ($summary) {
                $updateData = [];
                
                if ($period === 'L30') {
                    $updateData = [
                        'product_impressions_l30' => $summary['imprCntAll']['val'] ?? 0,
                        'product_clicks_l30' => $summary['clkCntAll']['val'] ?? 0,
                    ];
                } elseif ($period === 'L60') {
                    $updateData = [
                        'product_impressions_l60' => $summary['imprCntAll']['val'] ?? 0,
                        'product_clicks_l60' => $summary['clkCntAll']['val'] ?? 0,
                    ];
                }

                if (!empty($updateData)) {
                    $updated = TemuMetric::where('goods_id', $goodsId)->update($updateData);
                    
                    if ($updated) {
                        $this->info("✅ Successfully updated ads data for Goods ID: {$goodsId}");
                        $this->table(
                            ['Metric', 'Value'],
                            [
                                ['Impressions', $updateData['product_impressions_l30'] ?? $updateData['product_impressions_l60'] ?? 0],
                                ['Clicks', $updateData['product_clicks_l30'] ?? $updateData['product_clicks_l60'] ?? 0],
                            ]
                        );
                    } else {
                        $this->warn("No record found for Goods ID: {$goodsId}");
                    }
                }
            }
        } else {
            $this->error("Failed to fetch ads data for Goods ID: {$goodsId}");
        }
    }
}

