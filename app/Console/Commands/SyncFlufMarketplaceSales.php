<?php

namespace App\Console\Commands;

use App\Services\Support\FlufMarketplaceSalesSync;
use Illuminate\Console\Command;

class SyncFlufMarketplaceSales extends Command
{
    protected $signature = 'fluf:sync-sales
                            {channel=all : vinted, depop or all}
                            {--days=60 : How many days of Shopify orders to read}
                            {--inspect : Only list how Fluf/Vinted/Depop orders look in Shopify, change nothing}';

    protected $description = 'Copy Vinted and Depop sales (Shopify orders created by Fluf) into /vinted/sheet and /depop/sheet.';

    public function handle(FlufMarketplaceSalesSync $sync): int
    {
        $days = max(1, (int) $this->option('days'));

        if ($this->option('inspect')) {
            $rows = $sync->inspect($days);
            if ($rows === []) {
                $this->warn('No Shopify order lines mention fluf, vinted or depop in source_name or tags in the last '.$days.' days.');
                $this->line('Run php artisan shopify:sync-orders --days='.$days.' first if the orders are missing.');

                return self::SUCCESS;
            }
            foreach ($rows as $r) {
                $this->line(sprintf(
                    '[%s] orders=%d lines=%d %s..%s | source_name="%s" | tags="%s"',
                    $r['channel'], $r['orders'], $r['lines'], $r['first'], $r['last'], $r['source_name'], $r['tags']
                ));
            }

            return self::SUCCESS;
        }

        $arg = strtolower(trim((string) $this->argument('channel')));
        $channels = $arg === 'all' ? array_keys(FlufMarketplaceSalesSync::CHANNELS) : [$arg];
        $failed = 0;
        foreach ($channels as $channel) {
            try {
                $result = $sync->sync($channel, $days);
                $this->info($result['message']);
            } catch (\Throwable $e) {
                $failed++;
                $this->error($channel.': '.$e->getMessage());
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
