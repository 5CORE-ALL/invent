<?php

namespace App\Console\Commands;

use App\Services\TemuAdsApiReportService;
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
                            {--period=L30 : Time period (L7, L30 or L60)}
                            {--goods-id= : Fetch for specific goods ID only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Temu ads data via API, store full raw in temu_ads_api_reports, and sync impressions/clicks to temu_metrics';

    /**
     * Execute the console command.
     */
    public function handle(TemuAdsApiReportService $service)
    {
        $this->info('Starting Temu Ads Data Fetch...');
        Log::info('Starting Temu Ads Data Fetch');

        $period = strtoupper((string) $this->option('period'));
        $specificGoodsId = $this->option('goods-id') ?: null;

        if (! in_array($period, ['L7', 'L30', 'L60'], true)) {
            $this->error('Period must be L7, L30, or L60');

            return 1;
        }

        try {
            $goodsIds = $service->resolveGoodsIds($specificGoodsId);
            if (empty($goodsIds)) {
                $this->warn('No goods IDs found in database. Please run app:fetch-temu-metrics first.');

                return 1;
            }

            $this->info("Fetching ads data for period: {$period} (" . count($goodsIds) . ' goods)');
            $bar = $this->output->createProgressBar(count($goodsIds));
            $bar->start();

            $stats = $service->fetchAll($period, $specificGoodsId, function () use ($bar) {
                $bar->advance();
            });

            $bar->finish();
            $this->newLine();
            $this->info("✅ Updated {$stats['ok']} records (raw stored in temu_ads_api_reports)");
            if ($stats['fail'] > 0) {
                $this->warn("⚠️  {$stats['fail']} records had errors");
            }

            $this->info('✅ Temu Ads Data Fetch completed successfully');
            Log::info('Temu Ads Data Fetch completed successfully', $stats);
        } catch (\Exception $e) {
            $this->error('Error fetching Temu ads data: ' . $e->getMessage());
            Log::error('Error fetching Temu ads data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }

        return 0;
    }
}
