<?php

namespace App\Console\Commands;

use App\Services\ChannelPushedPricePullService;
use Illuminate\Console\Command;

class PullTemuPushedPricesCommand extends Command
{
    protected $signature = 'temu:pull-pushed-prices
        {--channel=all : temu, temu2, or all}
        {--limit=80 : Max due SKUs per channel}
        {--force : Pull pending SKUs even if the 2-hour wait is not over}';

    protected $description = 'Pull live Temu / Temu 2 API prices 2 hours after an S PRC push (no S PRC rewrite)';

    public function handle(ChannelPushedPricePullService $service): int
    {
        $stats = $service->pullDueTemu(
            max(1, (int) $this->option('limit')),
            (bool) $this->option('force'),
            (string) $this->option('channel')
        );

        $this->info(sprintf(
            'Temu pushed-price API pull: due=%d pulled=%d failed=%d',
            $stats['due'],
            $stats['pulled'],
            $stats['failed']
        ));

        return self::SUCCESS;
    }
}
