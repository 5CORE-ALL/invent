<?php

namespace App\Console\Commands;

use App\Services\AmazonPushedPricePullService;
use Illuminate\Console\Command;

class PullAmazonPushedPricesCommand extends Command
{
    protected $signature = 'amazon:pull-pushed-prices
        {--limit=30 : Max due SKUs per run}
        {--delay-ms=500 : Pause between SP-API listing reads}';

    protected $description = '15 min after S PRC / Push Prc: pull live Amazon listing price into the Price column';

    public function handle(AmazonPushedPricePullService $service): int
    {
        $stats = $service->pullDue(
            max(1, (int) $this->option('limit')),
            max(0, (int) $this->option('delay-ms'))
        );

        $this->info(sprintf(
            'Amazon pushed-price pull: due=%d pulled=%d retried=%d failed=%d',
            $stats['due'],
            $stats['pulled'],
            $stats['retried'],
            $stats['failed']
        ));

        return self::SUCCESS;
    }
}
