<?php

namespace App\Console\Commands;

use App\Services\TemuAdsApiReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshTemuAdStatus extends Command
{
    protected $signature = 'temu:refresh-ad-status
                            {--goods-id= : Refresh a single goods ID}';

    protected $description = 'Sync /temu/ads Status from temu.searchrec.ad.detail.query (adsDetail.adShowStatus)';

    public function handle(TemuAdsApiReportService $service): int
    {
        $goodsId = $this->option('goods-id') ?: null;
        $this->info('Refreshing Temu ad status'.($goodsId ? " for goods {$goodsId}" : ' for all goods').'...');

        $stats = $service->refreshAdStatuses($goodsId ? (string) $goodsId : null);
        Log::info('temu:refresh-ad-status finished', $stats);

        $this->info("Updated {$stats['ok']}/{$stats['total']} goods");
        if (($stats['fail'] ?? 0) > 0) {
            $this->warn("Failed: {$stats['fail']}".(! empty($stats['error']) ? ' — '.$stats['error'] : ''));
        }

        if (($stats['ok'] ?? 0) === 0 && ($stats['total'] ?? 0) > 0) {
            $this->error($stats['error'] ?? 'Status not sync');

            return 1;
        }

        return 0;
    }
}
