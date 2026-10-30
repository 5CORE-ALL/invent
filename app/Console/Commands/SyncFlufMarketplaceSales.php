<?php

namespace App\Console\Commands;

use App\Services\Support\FlufMarketplaceSalesSync;
use Illuminate\Console\Command;

class SyncFlufMarketplaceSales extends Command
{
    protected $signature = 'fluf:sync-sales
                            {channel=all : vinted, depop or all}
                            {--days=60 : How many days of Shopify orders to read}
                            {--replace-uploads : Also delete CSV-uploaded rows dated on/after the first Shopify sale (they would double count)}
                            {--check-duplicates : Only report sales entered twice on the sheet, change nothing}
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

        if ($this->option('check-duplicates')) {
            foreach ($channels as $channel) {
                $r = $sync->duplicateReport($channel);
                $this->info('== '.$r['label'].' ==');
                if (isset($r['error'])) {
                    $this->error($r['error']);

                    continue;
                }
                $this->line('Shopify rows: '.$r['shopify_rows'].' | uploaded rows: '.$r['upload_rows']);
                foreach ([
                    'upload_vs_shopify' => 'Uploaded row has the same date + SKU + qty as a Shopify row',
                    'shopify_vs_shopify' => 'Two Shopify orders with the same date + SKU + qty + price',
                    'upload_vs_upload' => 'Uploaded rows repeated (same date + buyer + SKU + price)',
                ] as $key => $title) {
                    $list = $r[$key];
                    $this->line($title.': '.count($list));
                    foreach (array_slice($list, 0, 15) as $line) {
                        $this->line('   '.$line);
                    }
                    if (count($list) > 15) {
                        $this->line('   … and '.(count($list) - 15).' more');
                    }
                }
            }

            return self::SUCCESS;
        }
        $failed = 0;
        foreach ($channels as $channel) {
            try {
                $result = $sync->sync($channel, $days, (bool) $this->option('replace-uploads'));
                $this->info($result['message']);
            } catch (\Throwable $e) {
                $failed++;
                $this->error($channel.': '.$e->getMessage());
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
