<?php

namespace App\Console\Commands;

use App\Services\AlibabaPushedPricePullService;
use Illuminate\Console\Command;

/**
 * Same as amazon:pull-pushed-prices. Alibaba reviews price changes, so the live price keeps the old value for a
 * while after a push. Pushed SKUs are re-read until the Price column shows the pushed price.
 */
class PullAlibabaPushedPricesCommand extends Command
{
    protected $signature = 'alibaba:pull-pushed-prices
        {--limit=30 : Max due SKUs per run}
        {--delay-ms=400 : Pause between Alibaba lookups}';

    protected $description = 'Confirm live Alibaba listing price into the Price column after S PRC / Push Prc';

    public function handle(AlibabaPushedPricePullService $service): int
    {
        $stats = $service->pullDue(max(1, (int) $this->option('limit')), max(0, (int) $this->option('delay-ms')));

        $this->info(sprintf(
            'Alibaba pushed-price pull: due=%d pulled=%d matched=%d failed=%d',
            $stats['due'],
            $stats['pulled'],
            $stats['matched'],
            $stats['failed']
        ));

        return self::SUCCESS;
    }
}
