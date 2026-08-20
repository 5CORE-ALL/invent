<?php

namespace App\Console\Commands;

use App\Services\Temu2AdsApiReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchTemu2AdsData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'temu2:fetch-ads-data 
                            {--period=L30 : Time period (L7, L30 or L60)}
                            {--goods-id= : Fetch for specific goods ID only}
                            {--reparse : Re-extract Overall metrics from stored raw JSON (no API call)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Temu 2 ads data via API, store in temu2_campaign_reports, and sync impressions/clicks to temu2_metrics';

    /**
     * Execute the console command.
     */
    public function handle(Temu2AdsApiReportService $service)
    {
        $period = strtoupper((string) $this->option('period'));
        $specificGoodsId = $this->option('goods-id') ?: null;

        if ($this->option('reparse')) {
            $reparsePeriod = $this->input->hasParameterOption('--period') && in_array($period, ['L7', 'L30', 'L60'], true)
                ? $period
                : null;
            $this->info('Reparsing stored Temu ads raw JSON' . ($reparsePeriod ? " ({$reparsePeriod})" : ' (all periods)') . ($specificGoodsId ? " for goods {$specificGoodsId}" : '') . '...');
            $stats = $service->reparseStored($reparsePeriod, $specificGoodsId);
            $this->info("✅ Reparsed {$stats['ok']}/{$stats['total']} rows");
            if ($stats['fail'] > 0) {
                $this->warn("⚠️  {$stats['fail']} rows failed");
            }

            return $stats['fail'] > 0 && $stats['ok'] === 0 ? 1 : 0;
        }

        if (! in_array($period, ['L7', 'L30', 'L60'], true)) {
            $this->error('Period must be L7, L30, or L60');

            return 1;
        }

        $this->info('Starting Temu 2 Ads Data Fetch...');
        Log::info('Starting Temu 2 Ads Data Fetch');

        try {
            $goodsIds = $service->resolveGoodsIds($specificGoodsId);
            if (empty($goodsIds)) {
                $this->warn('No goods IDs found in database. Please run app:fetch-temu2-metrics first.');

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
            $this->info("✅ Updated {$stats['ok']} records in temu2_campaign_reports");
            if ($stats['fail'] > 0) {
                $this->warn("⚠️  {$stats['fail']} records had errors");
            }

            $this->info('✅ Temu 2 Ads Data Fetch completed successfully');
            Log::info('Temu 2 Ads Data Fetch completed successfully', $stats);
        } catch (\Exception $e) {
            $this->error('Error fetching Temu 2 ads data: ' . $e->getMessage());
            Log::error('Error fetching Temu 2 ads data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }

        return 0;
    }
}
