<?php

namespace App\Console\Commands;

use App\Services\TikTokAdsApiService;
use Illuminate\Console\Command;

class TikTokAdsSyncCommand extends Command
{
    protected $signature = 'tiktok-ads:sync
        {--days=30 : Number of days (ending today) to pull}
        {--advertiser= : Only this advertiser id}';

    protected $description = 'Pull daily TikTok Ads Manager campaign spend/clicks/impressions into tiktok_ads_daily_reports';

    public function handle(TikTokAdsApiService $ads): int
    {
        try {
            $r = $ads->syncCampaignReports(
                max(1, (int) $this->option('days')),
                $this->option('advertiser') ?: null,
                fn (string $l) => $this->line($l)
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Done: {$r['rows']} campaign-day rows for {$r['advertisers']} advertiser(s).");

        return self::SUCCESS;
    }
}
