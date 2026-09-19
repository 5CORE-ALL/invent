<?php

namespace App\Console\Commands;

use App\Services\Temu3AdsApiReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshTemu3AdStatus extends Command
{
    protected $signature = 'temu3:refresh-ad-status
                            {--goods-id= : Refresh a single goods ID}
                            {--from-stored : Re-map Status from stored adDetail JSON (no Temu call)}';

    protected $description = 'Sync Temu 3 ad status from temu.searchrec.ad.detail.query (adsDetail.adShowStatus)';

    public function handle(Temu3AdsApiReportService $service): int
    {
        $goodsId = $this->option('goods-id') ?: null;
        if ($this->option('from-stored')) {
            $this->info('Re-mapping Temu 3 ad status from stored raw'.($goodsId ? " for goods {$goodsId}" : '').'...');
            $stats = $service->reapplyStatusesFromStoredAdDetail($goodsId ? (string) $goodsId : null);
            $this->info("Checked {$stats['ok']}/{$stats['total']} rows, changed {$stats['changed']}");

            return 0;
        }

        $this->info('Refreshing Temu 3 ad status'.($goodsId ? " for goods {$goodsId}" : ' for all goods').'...');

        $stats = $service->refreshAdStatuses($goodsId ? (string) $goodsId : null);
        Log::info('temu3:refresh-ad-status finished', $stats);

        $this->info("Updated {$stats['ok']}/{$stats['total']} goods");
        if (($stats['fail'] ?? 0) > 0) {
            $this->warn('Failed: '.$stats['fail'].(! empty($stats['error']) ? ' — '.$stats['error'] : ''));
        }

        if (($stats['ok'] ?? 0) === 0 && ($stats['total'] ?? 0) > 0) {
            $this->error($stats['error'] ?? 'Status not sync');

            return 1;
        }

        return 0;
    }
}
