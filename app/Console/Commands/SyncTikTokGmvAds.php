<?php

namespace App\Console\Commands;

use App\Services\TikTokGmvAdsSyncService;
use Illuminate\Console\Command;

class SyncTikTokGmvAds extends Command
{
    protected $signature = 'tiktok:sync-gmv-ads {--force : Ignore the 2-hour cache and fetch again}';

    protected $description = 'Fetch TikTok Shop L30/L1 product performance into tiktok_gmv_ads (SKU-matched)';

    public function handle(TikTokGmvAdsSyncService $sync): int
    {
        $this->info('Syncing TikTok GMV ads from Shop API…');
        $result = $this->option('force') ? $sync->sync() : $sync->syncIfStale();
        $this->line(json_encode($result, JSON_PRETTY_PRINT));

        return ! empty($result['l30_rows']) || ! empty($result['skipped']) ? self::SUCCESS : self::FAILURE;
    }
}
