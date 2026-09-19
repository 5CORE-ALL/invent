<?php

namespace App\Console\Commands;

use App\Services\Temu3AdsApiReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchTemu3AdsApiReports extends Command
{
    protected $signature = 'temu3:fetch-ads-api-reports
                            {--period=L30 : Time period (L7, L30, or L60)}
                            {--goods-id= : Fetch for a specific goods ID only}
                            {--reparse : Re-extract Overall metrics from stored raw JSON (no API call)}';

    protected $description = 'Fetch Temu 3 ads goods reports via API and store full raw JSON in temu3_ads_api_reports';

    public function handle(Temu3AdsApiReportService $service): int
    {
        $period = strtoupper((string) $this->option('period'));
        $goodsId = $this->option('goods-id') ?: null;

        if ($this->option('reparse')) {
            $reparsePeriod = $this->input->hasParameterOption('--period') && in_array($period, ['L7', 'L30', 'L60'], true)
                ? $period
                : null;
            $this->info('Reparsing stored Temu 3 ads raw JSON'.($reparsePeriod ? " ({$reparsePeriod})" : ' (all periods)').($goodsId ? " for goods {$goodsId}" : '').'...');
            $stats = $service->reparseStored($reparsePeriod, $goodsId);
            $this->info("Reparsed {$stats['ok']}/{$stats['total']} rows");
            Log::info('temu3:fetch-ads-api-reports reparse finished', $stats);

            return $stats['fail'] > 0 && $stats['ok'] === 0 ? 1 : 0;
        }

        if (! in_array($period, ['L7', 'L30', 'L60'], true)) {
            $this->error('Period must be L7, L30, or L60');

            return 1;
        }

        $this->info("Fetching Temu 3 ads API reports ({$period})".($goodsId ? " for goods {$goodsId}" : ' for all goods').'...');
        Log::info('temu3:fetch-ads-api-reports started', ['period' => $period, 'goods_id' => $goodsId]);

        $goodsIds = $service->resolveGoodsIds($goodsId);
        if (empty($goodsIds)) {
            $this->warn('No goods IDs found. Run app:fetch-temu3-metrics first.');

            return 1;
        }

        $bar = $this->output->createProgressBar(count($goodsIds));
        $bar->start();

        $stats = $service->fetchAll($period, $goodsId, function () use ($bar) {
            $bar->advance();
        });

        $bar->finish();
        $this->newLine();
        $this->info("Done. Total: {$stats['total']}, OK: {$stats['ok']}, Fail: {$stats['fail']}");
        Log::info('temu3:fetch-ads-api-reports finished', $stats);

        return $stats['fail'] > 0 && $stats['ok'] === 0 ? 1 : 0;
    }
}
