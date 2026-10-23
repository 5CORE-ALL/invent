<?php

namespace App\Console\Commands;

use App\Services\TiktokPushedPricePullService;
use Illuminate\Console\Command;

/**
 * Same as amazon:pull-pushed-prices. Confirms the live TikTok price into the Price column after a push, re-reading
 * every 2 minutes (up to 6 attempts) until it shows the pushed S PRC.
 */
class PullTiktokPushedPricesCommand extends Command
{
    protected $signature = 'tiktok:pull-pushed-prices
        {--channel= : tiktok or tiktok2 (both when empty)}
        {--limit=30 : Max due SKUs per run and channel}
        {--delay-ms=500 : Pause between TikTok reads}';

    protected $description = 'Confirm live TikTok / TikTok 2 listing price into the Price column after S PRC push';

    public function handle(TiktokPushedPricePullService $service): int
    {
        $only = trim((string) $this->option('channel'));
        $channels = $only !== '' ? [$only] : ['tiktok', 'tiktok2'];

        foreach ($channels as $channel) {
            $stats = $service->pullDue(
                $channel,
                max(1, (int) $this->option('limit')),
                max(0, (int) $this->option('delay-ms'))
            );
            $this->info(sprintf(
                '%s pushed-price pull: due=%d pulled=%d retried=%d failed=%d',
                $channel,
                $stats['due'],
                $stats['pulled'],
                $stats['retried'],
                $stats['failed']
            ));
        }

        return self::SUCCESS;
    }
}
