<?php

namespace App\Console\Commands;

use App\Services\Temu3AdsApiReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchTemu3AdsData extends Command
{
    protected $signature = 'temu3:fetch-ads-data
                            {--period=L30 : Time period (L7, L30 or L60)}
                            {--goods-id= : Fetch for specific goods ID only}
                            {--reparse : Re-extract Overall metrics from stored raw JSON (no API call)}';

    protected $description = 'Fetch Temu 3 ads data via API, store full raw in temu3_ads_api_reports, and sync impressions/clicks to temu3_metrics';

    public function handle(Temu3AdsApiReportService $service): int
    {
        $period = strtoupper((string) $this->option('period'));
        $specificGoodsId = $this->option('goods-id') ?: null;

        if ($this->option('reparse')) {
            $reparsePeriod = $this->input->hasParameterOption('--period') && in_array($period, ['L7', 'L30', 'L60'], true)
                ? $period
                : null;
            $this->info('Reparsing stored Temu 3 ads raw JSON'.($reparsePeriod ? " ({$reparsePeriod})" : ' (all periods)').($specificGoodsId ? " for goods {$specificGoodsId}" : '').'...');
            $stats = $service->reparseStored($reparsePeriod, $specificGoodsId);
            $this->info("Reparsed {$stats['ok']}/{$stats['total']} rows");
            if ($stats['fail'] > 0) {
                $this->warn("{$stats['fail']} rows failed");
            }

            return $stats['fail'] > 0 && $stats['ok'] === 0 ? 1 : 0;
        }

        if (! in_array($period, ['L7', 'L30', 'L60'], true)) {
            $this->error('Period must be L7, L30, or L60');

            return 1;
        }

        $this->info('Starting Temu 3 Ads Data Fetch...');
        Log::info('Starting Temu 3 Ads Data Fetch');

        try {
            $goodsIds = $service->resolveGoodsIds($specificGoodsId);
            if (empty($goodsIds)) {
                $this->warn('No goods IDs found in database. Please run app:fetch-temu3-metrics first.');

                return 1;
            }

            $this->info("Fetching ads data for period: {$period} (".count($goodsIds).' goods)');
            $bar = $this->output->createProgressBar(count($goodsIds));
            $bar->start();

            $stats = $service->fetchAll($period, $specificGoodsId, function () use ($bar) {
                $bar->advance();
            });

            $bar->finish();
            $this->newLine();
            $this->info("Updated {$stats['ok']} records (raw stored in temu3_ads_api_reports)");
            if ($stats['fail'] > 0) {
                $this->warn("{$stats['fail']} records had errors");
            }

            Log::info('Temu 3 Ads Data Fetch completed', $stats);
        } catch (\Exception $e) {
            $this->error('Error fetching Temu 3 ads data: '.$e->getMessage());
            Log::error('Error fetching Temu 3 ads data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }

        return 0;
    }
}
