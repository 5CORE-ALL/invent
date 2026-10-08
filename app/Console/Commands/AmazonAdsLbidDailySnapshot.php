<?php

namespace App\Console\Commands;

use App\Support\AmazonAdsLbidDaily;
use Illuminate\Console\Command;

class AmazonAdsLbidDailySnapshot extends Command
{
    protected $signature = 'amazon:ads-lbid-daily-snapshot
        {--channel=all : sp, sb, or all}
        {--campaign-id= : Only this campaign ID}';

    protected $description = 'Save today\'s Lbid (live Amazon bid) for every SP/SB campaign, one value per campaign per day, for the Lbid history dot';

    public function handle(): int
    {
        if (! AmazonAdsLbidDaily::available()) {
            $this->error(AmazonAdsLbidDaily::TABLE.' is missing. Run migrations.');

            return self::FAILURE;
        }

        $opt = strtolower((string) $this->option('channel'));
        $channels = $opt === 'sp' || $opt === 'sb' ? [$opt] : ['sp', 'sb'];
        $onlyCid = trim((string) $this->option('campaign-id'));

        foreach ($channels as $channel) {
            $n = AmazonAdsLbidDaily::snapshotFromReports($channel, $onlyCid);
            $this->info(strtoupper($channel).' Lbid saved for '.$n.' campaigns on '.AmazonAdsLbidDaily::today().'.');
        }

        return self::SUCCESS;
    }
}
