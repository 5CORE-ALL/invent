<?php

namespace App\Console\Commands;

use App\Services\TemuAdsApiReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchTemuAdsApiReports extends Command
{
    protected $signature = 'temu:fetch-ads-api-reports
                            {--period=L30 : Time period (L7, L30, or L60)}
                            {--goods-id= : Fetch for a specific goods ID only}';

    protected $description = 'Fetch Temu ads goods reports via API and store full raw JSON in temu_ads_api_reports';

    public function handle(TemuAdsApiReportService $service): int
    {
        $period = strtoupper((string) $this->option('period'));
        $goodsId = $this->option('goods-id') ?: null;

        if (! in_array($period, ['L7', 'L30', 'L60'], true)) {
            $this->error('Period must be L7, L30, or L60');

            return 1;
        }

        $this->info("Fetching Temu ads API reports ({$period})" . ($goodsId ? " for goods {$goodsId}" : ' for all goods') . '...');
        Log::info('temu:fetch-ads-api-reports started', ['period' => $period, 'goods_id' => $goodsId]);

        $goodsIds = $service->resolveGoodsIds($goodsId);
        if (empty($goodsIds)) {
            $this->warn('No goods IDs found. Run app:fetch-temu-metrics first.');

            return 1;
        }

        $bar = $this->output->createProgressBar(count($goodsIds));
        $bar->start();

        $stats = $service->fetchAll($period, $goodsId, function () use ($bar) {
            $bar->advance();
        });

        $bar->finish();
        $this->newLine();
        $this->info("✅ Done. Total: {$stats['total']}, OK: {$stats['ok']}, Fail: {$stats['fail']}");
        Log::info('temu:fetch-ads-api-reports finished', $stats);

        return $stats['fail'] > 0 && $stats['ok'] === 0 ? 1 : 0;
    }
}
